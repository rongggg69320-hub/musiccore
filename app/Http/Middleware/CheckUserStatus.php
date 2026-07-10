<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->status === 'suspended' || $user->status === 'inactive')) {
            // Revoke current token to force logout on next request if they somehow bypass this
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Your account has been ' . $user->status . '. Please contact support.',
                'status' => $user->status
            ], 403);
        }

        return $next($request);
    }
}
