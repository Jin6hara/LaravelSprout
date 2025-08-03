<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role): Response
    {
        $roles = explode('|', $role); // 'admin|general' → ['admin', 'general']

        if (!Auth::check() || !in_array(Auth::user()->role, $roles)) {
            return redirect()->route('welcome')->with('status', '指定されたページにアクセスする権限がありません。');
            //abort(403); // または redirect('/unauthorized')
        }

        return $next($request);
    }
}
