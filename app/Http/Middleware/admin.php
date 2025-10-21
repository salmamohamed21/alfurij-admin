<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;


class admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
         // Check if the user is authenticated and has the admin role
         if (!Auth::check()) {
            // User is not authenticated
            return response()->json(['message' => 'Unauthorized: User not authenticated'], 401);
        }

        if (Auth::user()->role !== 1) {
            // User is authenticated but not an admin
            return response()->json(['message' => 'Forbidden: You do not have admin access'], 403);
        }


        // Allow the request to proceed
        return $next($request);
    }
}