<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Genre;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    private array $userStatuses = ['active', 'inactive', 'suspended'];
    private array $trackStatuses = ['processing', 'published', 'rejected'];
    private array $albumStatuses = ['draft', 'published', 'archived'];

    public function login(): View|RedirectResponse
    {
        if (Auth::check() && $this->isAdmin(Auth::user())) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = strtolower($validated['username']);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $login, 'password' => $validated['password']], true)) {
            $request->session()->regenerate();

            if ($this->isAdmin(Auth::user()) && Auth::user()->status !== 'suspended') {
                return redirect()->route('admin.dashboard');
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return back()
            ->withErrors(['username' => 'Invalid admin credentials.'])
            ->onlyInput('username');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function dashboard(): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return view('admin.dashboard', [
            'stats' => $this->dashboardStats(),
        ]);
    }

    public function users(): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return view('admin.users.index', [
            'users' => $this->userAccountsQuery()->latest()->paginate(10),
            'userStatuses' => $this->userStatuses,
        ]);
    }

    public function genres(): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return view('admin.genres.index', [
            'genres' => Genre::withCount('tracks')->orderBy('name')->paginate(10),
        ]);
    }

    public function tracks(): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return view('admin.tracks.index', [
            'tracks' => Track::with(['genre', 'user'])->latest()->paginate(10),
            'trackStatuses' => $this->trackStatuses,
        ]);
    }

    public function albums(): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return view('admin.albums.index', [
            'albums' => Album::with(['user'])->withCount('tracks')->latest()->paginate(10),
            'albumStatuses' => $this->albumStatuses,
        ]);
    }

    public function createUser(): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return view('admin.users.form', [
            'user' => new User(['status' => 'active']),
            'statuses' => $this->userStatuses,
            'roles' => $this->accountRoles(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $validated = $this->validateUser($request);
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_password_set'] = true;

        User::create($validated);

        return redirect()->route('admin.users.index')->with('success', 'Account added.');
    }

    public function editUser(User $user): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        if ($this->isAdmin($user)) {
            return redirect()->route('admin.users.index')->withErrors(['user' => 'Admin accounts are not shown in account management.']);
        }

        return view('admin.users.form', [
            'user' => $user,
            'statuses' => $this->userStatuses,
            'roles' => $this->accountRoles(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        if ($this->isAdmin($user)) {
            return redirect()->route('admin.users.index')->withErrors(['user' => 'Admin accounts are not shown in account management.']);
        }

        $validated = $this->validateUser($request, $user);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['is_password_set'] = true;
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('success', 'Account updated.');
    }

    public function updateUserStatus(Request $request, User $user): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', $this->userStatuses),
        ]);

        $user->update($validated);

        return back()->with('success', 'User status updated.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $newStatus = $user->status === 'suspended' ? 'active' : 'suspended';
        $user->update(['status' => $newStatus]);

        if ($newStatus === 'suspended') {
            // Log out the user from all devices immediately
            $user->tokens()->delete();
        }

        return back()->with('success', $newStatus === 'suspended' ? 'User suspended.' : 'User unsuspended.');
    }

    public function storeGenre(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $validated = $request->validate(['name' => 'required|string|max:80|unique:genres,name']);
        Genre::create($validated);

        Cache::forget('genres_list');

        return redirect()->route('admin.genres.index')->with('success', 'Genre added.');
    }

    public function updateGenre(Request $request, Genre $genre): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $validated = $request->validate(['name' => 'required|string|max:80|unique:genres,name,' . $genre->id]);
        $genre->update($validated);

        Cache::forget('genres_list');

        return back()->with('success', 'Genre updated.');
    }

    public function destroyGenre(Genre $genre): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $genre->delete();

        Cache::forget('genres_list');

        return back()->with('success', 'Genre deleted.');
    }

    public function createTrack(): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return $this->trackForm(new Track(['status' => 'processing']));
    }

    public function storeTrack(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        Track::create($this->validateTrack($request));

        return redirect()->route('admin.tracks.index')->with('success', 'Track added.');
    }

    public function editTrack(Track $track): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return $this->trackForm($track);
    }

    public function updateTrack(Request $request, Track $track): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $track->update($this->validateTrack($request, $track));

        return redirect()->route('admin.tracks.index')->with('success', 'Track updated.');
    }

    public function updateTrackStatus(Request $request, Track $track): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', $this->trackStatuses),
        ]);

        $track->update($validated);

        return back()->with('success', 'Track status updated.');
    }

    public function destroyTrack(Track $track): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $track->delete();

        return back()->with('success', 'Track deleted.');
    }

    public function createAlbum(): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return $this->albumForm(new Album(['status' => 'draft']));
    }

    public function storeAlbum(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        Album::create($this->validateAlbum($request));

        return redirect()->route('admin.albums.index')->with('success', 'Album added.');
    }

    public function editAlbum(Album $album): View|RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        return $this->albumForm($album);
    }

    public function updateAlbum(Request $request, Album $album): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $album->update($this->validateAlbum($request));

        return redirect()->route('admin.albums.index')->with('success', 'Album updated.');
    }

    public function updateAlbumStatus(Request $request, Album $album): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', $this->albumStatuses),
        ]);

        $album->update($validated);

        return back()->with('success', 'Album status updated.');
    }

    public function destroyAlbum(Album $album): RedirectResponse
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $album->delete();

        return back()->with('success', 'Album deleted.');
    }

    private function guard(): ?RedirectResponse
    {
        return Auth::check() && $this->isAdmin(Auth::user()) && Auth::user()->status !== 'suspended'
            ? null
            : redirect()->route('admin.login');
    }

    private function isAdmin(User $user): bool
    {
        return $user->role_id === 1 || $user->role?->role_name === 'admin';
    }

    private function dashboardStats(): array
    {
        return [
            'users' => $this->userAccountsQuery()->count(),
            'genres' => Genre::count(),
            'tracks' => Track::count(),
            'albums' => Album::count(),
        ];
    }

    private function userAccountsQuery()
    {
        return User::with('role')->whereHas('role', function ($query) {
            $query->where('role_name', 'user');
        });
    }

    private function accountRoles()
    {
        return Role::where('role_name', 'user')->orderBy('id')->get();
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $passwordRule = $user ? 'nullable|min:8|max:64' : 'required|min:8|max:64';

        $usernameRule = $user
            ? 'required|string|max:32|unique:users,username,' . $user->id
            : 'required|string|max:32|unique:users,username';
        $emailRule = $user
            ? 'required|email|max:64|unique:users,email,' . $user->id
            : 'required|email|max:64|unique:users,email';

        return $request->validate([
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where('role_name', 'user'),
            ],
            'username' => $usernameRule,
            'email' => $emailRule,
            'password' => $passwordRule,
            'bio' => 'nullable|string|max:255',
            'status' => 'required|in:' . implode(',', $this->userStatuses),
            'is_verified' => 'nullable|boolean',
        ]);
    }

    private function validateTrack(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:120',
            'user_id' => 'nullable|exists:users,id',
            'artist_name' => 'required|string|max:120',
            'album' => 'nullable|string|max:120',
            'album_id' => 'nullable|exists:albums,id',
            'genre_id' => 'nullable|exists:genres,id',
            'audio_file' => 'required|string|max:255',
            'cover_image' => 'nullable|string|max:255',
            'status' => 'required|in:' . implode(',', $this->trackStatuses),
        ]);
    }

    private function validateAlbum(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:120',
            'user_id' => 'nullable|exists:users,id',
            'artist_name' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:1000',
            'cover_image' => 'nullable|string|max:255',
            'status' => 'required|in:' . implode(',', $this->albumStatuses),
        ]);
    }

    private function trackForm(Track $track): View
    {
        return view('admin.tracks.form', [
            'track' => $track,
            'statuses' => $this->trackStatuses,
            'genres' => Genre::orderBy('name')->get(),
            'albums' => Album::orderBy('title')->get(),
            'users' => User::orderBy('username')->get(),
        ]);
    }

    private function albumForm(Album $album): View
    {
        return view('admin.albums.form', [
            'album' => $album,
            'statuses' => $this->albumStatuses,
            'users' => User::orderBy('username')->get(),
        ]);
    }
}
