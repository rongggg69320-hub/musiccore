<?php

namespace App\Livewire\Pages\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public $username = '';
    public $password = '';
    public $loginError = '';

    public function login()
    {
        $this->loginError = '';

        $validated = $this->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = strtolower($validated['username']);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $login, 'password' => $validated['password']], true)) {
            session()->regenerate();

            if ($this->isAdmin(Auth::user()) && Auth::user()->status !== 'suspended') {
                return redirect()->route('admin.dashboard');
            }

            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        }

        $this->loginError = 'Invalid admin credentials.';
    }

    private function isAdmin(User $user): bool
    {
        return $user->role_id === 1 || $user->role?->role_name === 'admin';
    }

    public function render()
    {
        return view('livewire.pages.auth.login')
            ->layout('layouts.app', [
                'title' => 'Login'
            ]);
    }
}
