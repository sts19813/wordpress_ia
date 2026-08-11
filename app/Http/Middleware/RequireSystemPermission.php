<?php

namespace App\Http\Middleware;

use App\Support\SystemPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSystemPermission
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        $permission ??= SystemPermissions::forRoute($request->route()?->getName());

        abort_unless($user && (! $permission || $user->isAdmin() || $user->can($permission)), 403);

        return $next($request);
    }
}
