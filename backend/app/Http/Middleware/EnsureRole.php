<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel guard by role kind: `role:superadmin`, `role:staff,superadmin`, `role:user`.
 * Fine-grained access is checked per route with `can:<permission>` (DECISIONS D-42).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$kinds): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasKind(...$kinds)) {
            abort(403, 'You do not have access to this area.');
        }

        return $next($request);
    }
}
