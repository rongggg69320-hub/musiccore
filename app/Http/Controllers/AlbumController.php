<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Album;
use App\Models\Track;
use App\Support\SupabaseStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AlbumController extends Controller
{
    /**
     * List all albums for the authenticated user.
     */
    public function index()
    {
        $albums = Album::with('user')->where('user_id', Auth::id())->latest()->get();

        $mapped = $albums->map(function ($album) {
            $data = $album->toArray();
            $data['cover_url'] = SupabaseStorage::imageUrl($album->cover_image);
            $data['user'] = $album->user ? [
                'id' => $album->user->id,
                'username' => $album->user->username,
                'name' => $album->user->username, // Use username as name
            ] : null;
            $data['username'] = $album->user?->username;
            return $data;
        });

        return response()->json($mapped->values()->all());
    }

    /**
     * Store a new album.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'artist_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:10240',
            'status' => 'nullable|in:draft,published,archived',
            'track_ids' => 'nullable|array',
            'track_ids.*' => 'integer|exists:tracks,id',
        ]);

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('album_covers', 'supabase_images');
        }

        $album = Album::create([
            'title' => $request->input('title'),
            'user_id' => Auth::id(),
            'artist_name' => $request->input('artist_name'),
            'description' => $request->input('description'),
            'cover_image' => $coverPath,
            'status' => $request->input('status', 'draft'),
        ]);

        // Link tracks to album
        if ($request->has('track_ids')) {
            Track::whereIn('id', $request->input('track_ids'))
                ->where('user_id', Auth::id())
                ->update(['album_id' => $album->id, 'album' => $album->title]);
        }

        $data = $album->toArray();
        $data['cover_url'] = SupabaseStorage::imageUrl($coverPath);

        return response()->json($data, 201);
    }

    /**
     * Show a single album.
     */
    public function show($id)
    {
        $album = Album::with(['tracks.user', 'user'])->findOrFail($id);
        if ($album->status !== 'published' && $album->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $album->toArray();
        $data['cover_url'] = SupabaseStorage::imageUrl($album->cover_image);
        $data['user'] = $album->user ? [
            'id' => $album->user->id,
            'username' => $album->user->username,
            'name' => $album->user->username, // Use username as name
        ] : null;
        $data['username'] = $album->user?->username;
        $data['tracks'] = $album->tracks->map(function ($track) {
            $trackData = $track->toArray();
            $trackData['audio_url'] = SupabaseStorage::musicUrl($track->audio_file);
            $trackData['cover_url'] = SupabaseStorage::imageUrl($track->cover_image);
            $trackData['user'] = $track->user ? [
                'id' => $track->user->id,
                'username' => $track->user->username,
                'name' => $track->user->username, // Use username as name
            ] : null;
            $trackData['username'] = $track->user?->username;
            return $trackData;
        })->values()->all();

        return response()->json($data);
    }

    /**
     * Get tracks for a specific album with pagination.
     */
    public function tracks(Request $request, $id)
    {
        $limit = (int) $request->query('limit', 10);
        $offset = (int) $request->query('offset', 0);

        $album = Album::findOrFail($id);

        // Security: If album is not published, only the owner can see the tracks
        if ($album->status !== 'published' && $album->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tracks = Track::with('user')
            ->where('album_id', $id)
            ->latest('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $mapped = $tracks->map(function (Track $t) {
            $arr = $t->toArray();
            $arr['audio_url'] = SupabaseStorage::musicUrl($t->audio_file);
            $arr['cover_url'] = SupabaseStorage::imageUrl($t->cover_image);
            $arr['user'] = $t->user ? [
                'id' => $t->user->id,
                'username' => $t->user->username,
                'name' => $t->user->username, // Use username as name
            ] : null;
            $arr['username'] = $t->user?->username;
            return $arr;
        });

        return response()->json($mapped->values()->all());
    }

    /**
     * Get all published albums for discovery.
     */
    public function publicAlbums(Request $request)
    {
        $limit = $request->query('limit', 20);
        $offset = $request->query('offset', 0);

        $albums = Album::with('user')
            ->where('status', 'published')
            ->latest('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $mapped = $albums->map(function ($album) {
            $data = $album->toArray();
            $data['cover_url'] = SupabaseStorage::imageUrl($album->cover_image);
            $data['user'] = $album->user ? [
                'id' => $album->user->id,
                'username' => $album->user->username,
                'name' => $album->user->username, // Use username as name
            ] : null;
            $data['username'] = $album->user?->username;
            return $data;
        });

        return response()->json($mapped->values()->all());
    }

    /**
     * Update a single album.
     */
    public function update(Request $request, $id)
    {
        $album = Album::findOrFail($id);
        if ($album->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'artist_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:10240',
            'status' => 'nullable|in:draft,published,archived',
            'track_ids' => 'nullable|array',
            'track_ids.*' => 'integer|exists:tracks,id',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($album->cover_image) {
                Storage::disk('supabase_images')->delete($album->cover_image);
            }
            $album->cover_image = $request->file('cover_image')->store('album_covers', 'supabase_images');
        }

        $album->title = $request->input('title');
        $album->artist_name = $request->input('artist_name');
        $album->description = $request->input('description');
        $album->status = $request->input('status', $album->status);
        $album->save();

        // Update tracks: first unlink old tracks, then link new ones
        if ($request->has('track_ids')) {
            // Unlink tracks that were previously in this album but are not anymore
            Track::where('album_id', $album->id)
                ->whereNotIn('id', $request->input('track_ids'))
                ->update(['album_id' => null, 'album' => null]);

            // Link new tracks
            Track::whereIn('id', $request->input('track_ids'))
                ->where('user_id', Auth::id())
                ->update(['album_id' => $album->id, 'album' => $album->title]);
        }

        $data = $album->toArray();
        $data['cover_url'] = SupabaseStorage::imageUrl($album->cover_image);

        return response()->json($data);
    }

    /**
     * Delete an album.
     */
    public function destroy($id)
    {
        $album = Album::findOrFail($id);
        if ($album->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($album->cover_image) {
            Storage::disk('supabase_images')->delete($album->cover_image);
        }

        // Unlink tracks from the deleted album
        Track::where('album_id', $album->id)->update(['album_id' => null, 'album' => null]);

        $album->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get new released albums
     */
    public function newReleases()
    {
        $limit = request('limit', 10);
        $albums = Album::where('status', 'published')
            ->latest('created_at')
            ->limit($limit)
            ->get();

        $mapped = $albums->map(function ($album) {
            $data = $album->toArray();
            $data['cover_url'] = SupabaseStorage::imageUrl($album->cover_image);
            $data['user'] = $album->user ? [
                'id' => $album->user->id,
                'username' => $album->user->username,
                'name' => $album->user->username, // Use username as name
            ] : null;
            $data['username'] = $album->user?->username;
            return $data;
        });

        return response()->json($mapped->values()->all());
    }
}
