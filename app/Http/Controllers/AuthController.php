<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Otp;
use App\Mail\OtpMail;
use App\Support\SupabaseStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function userPayload(User $user): array
    {
        $userArray = $user->toArray();
        $userArray['name'] = $user->username;

        $profileImage = $user->profile_image;
        if ($profileImage && filter_var($profileImage, FILTER_VALIDATE_URL)) {
            $url = $profileImage;
        } else {
            $url = SupabaseStorage::imageUrl($profileImage);
        }

        $userArray['profile_image_url'] = $url;
        $userArray['profile_pic_url'] = $url; // Compatibility with frontend keys
        $userArray['is_password_set'] = (bool) $user->is_password_set;

        // Return connection status for each provider
        $userArray['connected_providers'] = [
            'google' => !empty($user->google_id),
            'facebook' => !empty($user->facebook_id),
        ];

        return $userArray;
    }

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
            'user' => $this->userPayload($user),
            'token' => $token->plainTextToken,
        ];
    }

    private function validOtp(string $email, string $code): ?Otp
    {
        $record = Otp::where('email', strtolower($email))
            ->where('otp', $code)
            ->first();

        if (!$record || now()->gt($record->expires_at)) {
            return null;
        }

        return $record;
    }

    private function sendOtpTo(string $email, string $successMessage)
    {
        $email = strtolower($email);
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Otp::updateOrCreate(
            ['email' => $email],
            [
                'email' => $email,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(15),
            ]
        );

        try {
            Mail::to($email)->send(new OtpMail($otp, $email));
            Log::info('OTP email sent successfully to ' . $email);
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to send email. Please try again later.',
            ], 500);
        }

        return response()->json(['message' => $successMessage]);
    }

    private function verifySocialToken(string $provider, string $token): ?array
    {
        if (in_array($provider, ['google', 'facebook'], true)) {
            return $this->verifyFirebaseSocialToken($provider, $token);
        }

        return null;
    }

    private function verifyFirebaseSocialToken(string $provider, string $token): ?array
    {
        $projectId = config('services.firebase.project_id');

        if (!$projectId) {
            Log::warning('Firebase project id is not configured.');
            return null;
        }

        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = $this->decodeJwtSegment($encodedHeader);
        $payload = $this->decodeJwtSegment($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (!$header || !$payload || !$signature || ($header['alg'] ?? null) !== 'RS256') {
            return null;
        }

        $certs = $this->firebasePublicCerts();
        $kid = $header['kid'] ?? null;

        if (!$kid || empty($certs[$kid])) {
            return null;
        }

        $verified = openssl_verify(
            $encodedHeader . '.' . $encodedPayload,
            $signature,
            $certs[$kid],
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            return null;
        }

        $now = time();
        $issuer = 'https://securetoken.google.com/' . $projectId;

        if (($payload['aud'] ?? null) !== $projectId ||
            ($payload['iss'] ?? null) !== $issuer ||
            empty($payload['sub']) ||
            ($payload['exp'] ?? 0) < $now ||
            ($payload['iat'] ?? $now + 1) > $now
        ) {
            return null;
        }

        $firebaseProvider = $payload['firebase']['sign_in_provider'] ?? null;
        $expectedFirebaseProvider = $provider === 'google' ? 'google.com' : 'facebook.com';

        if ($firebaseProvider !== $expectedFirebaseProvider) {
            return null;
        }

        if ($provider === 'google' && ($payload['email_verified'] ?? false) !== true) {
            return null;
        }

        return [
            'id' => $payload['sub'] ?? null,
            'email' => $payload['email'] ?? null,
            'name' => $payload['name'] ?? null,
            'picture' => $payload['picture'] ?? null,
        ];
    }

    private function firebasePublicCerts(): array
    {
        return Cache::remember('firebase_securetoken_certs', now()->addHours(6), function () {
            $response = Http::timeout(10)->get(
                'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com'
            );

            if (!$response->ok()) {
                return [];
            }

            return $response->json() ?? [];
        });
    }

    private function decodeJwtSegment(string $segment): ?array
    {
        $decoded = $this->base64UrlDecode($segment);

        if (!$decoded) {
            return null;
        }

        $data = json_decode($decoded, true);

        return is_array($data) ? $data : null;
    }

    private function base64UrlDecode(string $value): string|false
    {
        $remainder = strlen($value) % 4;

        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:32',
            'email' => 'required|email|max:64',
            'password' => 'required|min:8|max:64|confirmed',
        ]);

        $errors = [];

        if (User::where('username', strtolower($validated['username']))->exists()) {
            $errors['username'][] = 'The username has already been taken.';
        }

        if (User::where('email', strtolower($validated['email']))->exists()) {
            $errors['email'][] = 'The email has already been taken.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $user = User::create([
            'role_id' => 2, // user role default
            'username' => strtolower($validated['username']),
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'is_password_set' => true,
            'status' => 'active',
        ]);

        $session = $this->issueToken($user, $request);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $session['user'],
            'token' => $session['token'],
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:64',
            'password' => 'required|min:6|max:64',
            'device_name' => 'nullable|string|max:120',
            'platform' => 'nullable|string|max:60',
            'platform_version' => 'nullable|string|max:120',
        ]);

        $user = User::where('email', strtolower($validated['email']))->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials. Please check your email and password.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is ' . $user->status . '.',
            ], 403);
        }

        $session = $this->issueToken($user, $request);

        return response()->json([
            'message' => 'Login successful',
            'user' => $session['user'],
            'token' => $session['token'],
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:64',
        ]);

        $exists = User::where('email', strtolower($request->email))->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Email is already taken.' : 'Email is available.',
        ]);
    }

    public function checkUsername(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:32',
        ]);

        $exists = User::where('username', strtolower($request->username))->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Username is already taken.' : 'Username is available.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:64'
        ]);

        $user = User::where('email', strtolower($request->email))->first();

        if (!$user) {
            return response()->json([
                'message' => 'We couldn\'t find a user with that email address.',
            ], 404);
        }

        return $this->sendOtpTo($request->email, 'Verification code has been sent to your email.');
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:64',
            'code' => 'required|string|size:6',
        ]);

        if (!$this->validOtp($request->email, $request->code)) {
            return response()->json([
                'message' => 'Invalid verification code.'
            ], 400);
        }

        return response()->json([
            'message' => 'Code verified successfully.'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:64',
            'code' => 'required|string|size:6',
            'password' => 'required|min:6|max:64|confirmed',
        ]);

        $user = User::where('email', strtolower($request->email))->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (!$this->validOtp($request->email, $request->code)) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->is_password_set = true;
        $user->is_verified = true;
        $user->save();

        Otp::where('email', strtolower($request->email))->delete();

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $validated = $request->validate([
            'username' => 'required|string|max:32',
            'bio' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $username = strtolower($validated['username']);

        if (User::where('username', $username)
            ->where('id', '!=', $user->id)
            ->exists()
        ) {
            return response()->json([
                'message' => 'The username has already been taken.',
            ], 422);
        }

        $user->username = $username;
        $user->bio = $validated['bio'] ?? $user->bio;

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('supabase_images')->delete($user->profile_image);
            }

            $user->profile_image = $request->file('profile_image')->store('profile_images', 'supabase_images');
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->userPayload($user),
        ], 200);
    }

    public function sendSecurityCode(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $this->sendOtpTo($user->email, 'Security code has been sent to your email.');
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $validated = $request->validate([
            'current_password' => 'nullable|string|max:64',
            'code' => 'nullable|string|size:6',
            'password' => 'required|min:8|max:64|confirmed',
        ]);

        $currentPasswordOk = !empty($validated['current_password'])
            && Hash::check($validated['current_password'], $user->password);
        $codeOk = !empty($validated['code'])
            && $this->validOtp($user->email, $validated['code']);

        if (!$currentPasswordOk && !$codeOk) {
            return response()->json([
                'message' => 'Enter your current password or a valid email security code.',
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->is_password_set = true;
        $user->is_verified = true;
        $user->save();

        Otp::where('email', strtolower($user->email))->delete();

        return response()->json([
            'message' => 'Password changed successfully.',
            'user' => $this->userPayload($user),
        ]);
    }

    public function connectSocialAccount(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $request->validate([
            'provider' => 'required|string|in:google,facebook',
            'provider_token' => 'required|string',
        ]);

        $provider = $request->provider;
        $profile = $this->verifySocialToken($provider, $request->provider_token);

        if (!$profile || empty($profile['id']) || empty($profile['email'])) {
            return response()->json([
                'message' => 'Could not verify your social account. Please try again.',
            ], 401);
        }

        if (strtolower($profile['email']) !== strtolower($user->email)) {
            return response()->json([
                'message' => 'This social account email must match your music account email.',
            ], 422);
        }

        $column = $provider === 'google' ? 'google_id' : 'facebook_id';

        $alreadyLinked = User::where($column, $profile['id'])
            ->where('id', '!=', $user->id)
            ->exists();

        if ($alreadyLinked) {
            return response()->json([
                'message' => 'This social account is already connected to another user.',
            ], 409);
        }

        $user->$column = $profile['id'];
        // Keep legacy fields for fallback, though we should transition to multi-column
        $user->social_provider = $provider;
        $user->social_id = $profile['id'];

        $user->is_verified = true;
        $user->save();

        return response()->json([
            'message' => ucfirst($provider) . ' account connected successfully.',
            'user' => $this->userPayload($user),
        ]);
    }

    public function disconnectSocialAccount(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $request->validate([
            'provider' => 'required|string|in:google,facebook',
        ]);

        $provider = $request->provider;
        $column = $provider === 'google' ? 'google_id' : 'facebook_id';

        if (empty($user->$column)) {
            return response()->json([
                'message' => 'That social account is not connected.',
            ], 422);
        }

        $user->$column = null;

        // Update legacy fields if it matches the one we are disconnecting
        if ($user->social_provider === $provider) {
            $user->social_provider = null;
            $user->social_id = null;
        }

        $user->save();

        return response()->json([
            'message' => ucfirst($request->provider) . ' account disconnected.',
            'user' => $this->userPayload($user),
        ]);
    }

    public function securityOverview(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $currentTokenId = $user->currentAccessToken()?->id;
        $tokens = $user->tokens()
            ->orderByDesc('created_at')
            ->get();

        $sessions = $tokens->map(function ($token) use ($currentTokenId) {
            $expiresAt = $token->expires_at;
            $isExpired = $expiresAt ? now()->greaterThan($expiresAt) : false;

            return [
                'id' => $token->id,
                'name' => $token->name,
                'device_name' => $token->device_name,
                'platform' => $token->platform,
                'platform_version' => $token->platform_version,
                'ip_address' => $token->ip_address,
                'is_current' => $currentTokenId === $token->id,
                'is_active' => !$isExpired,
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $expiresAt?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'active_sessions' => $sessions->where('is_active', true)->values(),
            'login_history' => $sessions->take(20)->values(),
        ]);
    }

    public function revokeSession(Request $request, int $tokenId)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $token = $user->tokens()->whereKey($tokenId)->first();

        if (!$token) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $wasCurrentSession = $user->currentAccessToken()?->id === $token->id;
        $token->delete();

        return response()->json([
            'message' => 'Session revoked successfully.',
            'revoked_current_session' => $wasCurrentSession,
        ]);
    }

    public function revokeAllSessions(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $user->tokens()->delete();

        return response()->json([
            'message' => 'All sessions have been logged out.',
            'revoked_current_session' => true,
        ]);
    }

    public function socialLogin(Request $request)
    {
        $request->validate([
            'provider' => 'required|string|in:google,facebook',
            'provider_token' => 'required|string',
            'device_name' => 'nullable|string|max:120',
            'platform' => 'nullable|string|max:60',
            'platform_version' => 'nullable|string|max:120',
        ]);

        $provider = $request->provider;
        $profile = $this->verifySocialToken($provider, $request->provider_token);

        if (!$profile || empty($profile['id']) || empty($profile['email'])) {
            return response()->json([
                'message' => 'Could not verify your social account. Please try again.',
            ], 401);
        }

        $email = strtolower($profile['email']);
        $socialId = $profile['id'];
        $name = $profile['name'] ?? null;
        $column = $provider === 'google' ? 'google_id' : 'facebook_id';

        // 1. Try to find user by the provider-specific ID
        $user = User::where($column, $socialId)->first();

        // 2. If not found by ID, try by email
        if (!$user) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            if ($user->status !== 'active') {
                return response()->json([
                    'message' => 'Your account is ' . $user->status . '.',
                ], 403);
            }

            // Update the specific provider ID and legacy fields
            $user->$column = $socialId;
            $user->social_provider = $provider;
            $user->social_id = $socialId;
            $user->is_verified = true;
            $user->save();
        } else {
            $baseUsername = preg_replace('/[^a-z0-9_]/', '', strtolower($name ?? explode('@', $email)[0]));
            $username = $baseUsername ?: 'user';
            $count = 1;

            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . $count;
                $count++;
            }

            $user = User::create([
                'role_id' => 2,
                'username' => $username,
                'email' => $email,
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'is_password_set' => false,
                'social_provider' => $provider,
                'social_id' => $socialId,
                'profile_image' => $profile['picture'] ?? null,
                $column => $socialId,
                'is_verified' => true,
                'status' => 'active',
            ]);
        }

        $session = $this->issueToken($user, $request);

        return response()->json([
            'message' => 'Social login successful.',
            'user' => $session['user'],
            'token' => $session['token'],
        ], 200);
    }
}
