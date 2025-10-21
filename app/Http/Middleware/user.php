<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class user
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
if(Auth::user()->role == 0){
    // User is a regular user
    return $next($request);
} 
else
{
    // User is not a regular user (admin or seller)
    return response()->json(['message' => 'Unauthorized'], 403);
}


}
}

