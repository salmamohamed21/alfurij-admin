<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Admin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
            return response()->json(['message' => 'Access denied. Admins only.'], 403);
        }

        return $next($request);
    }
}
