<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Otp;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Issue a new Sanctum token and return user data
     */
    private function issueToken(User $user, ?Request $request = null): array
    {
        $user->forceFill(['last_login' => now()])->save();
        $token = $user->createToken('auth_token');

        if ($request) {
            $token->accessToken->forceFill([
                'device_name' => $request->input('device_name'),
                'platform' => $request->input('platform'),
                'platform_version' => $request->input('platform_version'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])->save();
        }

        return [
            'user' => $user->makeVisible(['profile_image_url', 'name']),
            'token' => $token->plainTextToken,
        ];
    }

    private function validOtp(string $email, string $code): ?Otp
    {
        $record = Otp::where('email', strtolower($email))
            ->where('otp', $code)
            ->first();

        if (!$record || now()->gt($record->expires_at)) return null;
        return $record;
    }

    private function sendOtpTo(string $email, string $successMessage)
    {
        $email = strtolower($email);
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Otp::updateOrCreate(
            ['email' => $email],
            ['otp' => $otp, 'expires_at' => now()->addMinutes(15)]
        );

        try {
            Mail::to($email)->send(new OtpMail($otp, $email));
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to send email.'], 500);
        }

        return response()->json(['message' => $successMessage]);
    }

    private function verifySocialToken(string $provider, string $token): ?array
    {
        if (!in_array($provider, ['google', 'facebook'], true)) return null;

        $projectId = config('services.firebase.project_id');
        if (!$projectId) return null;

        $segments = explode('.', $token);
        if (count($segments) !== 3) return null;

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = json_decode(base64_decode(strtr($encodedHeader, '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($encodedPayload, '-_', '+/')), true);
        $signature = base64_decode(strtr($encodedSignature, '-_', '+/'));

        if (!$header || !$payload || !$signature || ($header['alg'] ?? null) !== 'RS256') return null;

        $certs = Cache::remember('firebase_certs', 21600, function () {
            return Http::get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com')->json();
        });

        $kid = $header['kid'] ?? null;
        if (!$kid || empty($certs[$kid])) return null;

        if (openssl_verify($encodedHeader . '.' . $encodedPayload, $signature, $certs[$kid], OPENSSL_ALGO_SHA256) !== 1) return null;

        if (($payload['aud'] ?? null) !== $projectId || ($payload['iss'] ?? null) !== 'https://securetoken.google.com/' . $projectId || ($payload['exp'] ?? 0) < time()) return null;

        return [
            'id' => $payload['sub'],
            'email' => $payload['email'],
            'name' => $payload['name'] ?? null,
            'picture' => $payload['picture'] ?? null,
        ];
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:32|unique:users,username',
            'email' => 'required|email|max:64|unique:users,email',
            'password' => 'required|min:8|max:64|confirmed',
        ]);

        $user = User::create([
            'role_id' => 2,
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_password_set' => true,
            'status' => 'active',
        ]);

        $session = $this->issueToken($user, $request);
        return response()->json(['message' => 'Registered successfully', 'user' => $session['user'], 'token' => $session['token']], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:64',
            'password' => 'required|min:6|max:64',
        ]);

        $user = User::where('email', strtolower($validated['email']))->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->status !== 'active') return response()->json(['message' => 'Account is ' . $user->status], 403);

        $session = $this->issueToken($user, $request);
        return response()->json(['message' => 'Login successful', 'user' => $session['user'], 'token' => $session['token']]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function checkEmail(Request $request)
    {
        $exists = User::where('email', strtolower($request->email))->exists();
        return response()->json(['exists' => $exists]);
    }

    public function checkUsername(Request $request)
    {
        $exists = User::where('username', strtolower($request->username))->exists();
        return response()->json(['exists' => $exists]);
    }

    public function forgotPassword(Request $request)
    {
        $user = User::where('email', strtolower($request->email))->first();
        if (!$user) return response()->json(['message' => 'User not found.'], 404);
        return $this->sendOtpTo($request->email, 'Code sent.');
    }

    public function verifyCode(Request $request)
    {
        if (!$this->validOtp($request->email, $request->code)) return response()->json(['message' => 'Invalid code.'], 400);
        return response()->json(['message' => 'Verified.']);
    }

    public function resetPassword(Request $request)
    {
        $user = User::where('email', strtolower($request->email))->first();
        if (!$user || !$this->validOtp($request->email, $request->code)) return response()->json(['message' => 'Invalid request.'], 400);

        $user->password = Hash::make($request->password);
        $user->is_password_set = true;
        $user->save();
        Otp::where('email', strtolower($request->email))->delete();

        return response()->json(['message' => 'Password reset.']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'username' => 'required|string|max:32|unique:users,username,' . $user->id,
            'bio' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $user->username = $validated['username'];
        $user->bio = $validated['bio'] ?? $user->bio;

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) Storage::disk('public')->delete($user->profile_image);
            $user->profile_image = $request->file('profile_image')->store('profile_images', 'public');
        }

        $user->save();
        return response()->json(['message' => 'Updated.', 'user' => $user]);
    }

    public function socialLogin(Request $request)
    {
        $request->validate(['provider' => 'required|in:google,facebook', 'provider_token' => 'required']);
        $profile = $this->verifySocialToken($request->provider, $request->provider_token);

        if (!$profile) return response()->json(['message' => 'Verification failed.'], 401);

        $email = strtolower($profile['email']);
        $column = $request->provider === 'google' ? 'google_id' : 'facebook_id';

        $user = User::where($column, $profile['id'])->orWhere('email', $email)->first();

        if ($user) {
            if ($user->status !== 'active') return response()->json(['message' => 'Forbidden'], 403);
            $user->$column = $profile['id'];
            $user->save();
        } else {
            $user = User::create([
                'role_id' => 2,
                'username' => $this->generateUniqueUsername($profile['name'] ?? explode('@', $email)[0]),
                'email' => $email,
                'password' => Hash::make(random_bytes(16)),
                'is_password_set' => false,
                $column => $profile['id'],
                'status' => 'active',
            ]);
        }

        $session = $this->issueToken($user, $request);
        return response()->json(['message' => 'Login successful.', 'user' => $session['user'], 'token' => $session['token']]);
    }

    private function generateUniqueUsername(string $name): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower($name)) ?: 'user';
        $username = $base;
        $count = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base . $count++;
        }
        return $username;
    }
}
