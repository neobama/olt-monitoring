<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Closure;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        Log::info('Authenticate middleware', [
            'session_id' => session()->getId(),
            'is_authenticated' => auth()->check(),
            'user' => auth()->user(),
            'url' => $request->url(),
            'method' => $request->method()
        ]);

        return $request->expectsJson() ? null : route('login');
    }

    public function handle($request, Closure $next, ...$guards)
    {
        Log::info('Authenticate middleware', [
            'session_id' => session()->getId(),
            'is_authenticated' => auth()->check(),
            'user' => auth()->user(),
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'cookies' => $request->cookies->all()
        ]);

        if ($this->auth->guard($guards)->guest()) {
            Log::warning('Authentication failed', [
                'session_id' => session()->getId(),
                'url' => $request->url(),
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'cookies' => $request->cookies->all()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest($this->redirectTo($request));
        }

        return $next($request);
    }
} 