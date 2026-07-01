<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! $request->user()?->hasRole($roles)) {
            abort(403);
        }

        return $next($request);
    }
}
