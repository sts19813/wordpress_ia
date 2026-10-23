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
        $routeName = $request->route()?->getName();

        if ($user?->isOperator()) {
            $allowedRoutes = [
                'admin.manual-posts.',
                'admin.quick-posts.',
                'admin.scheduler.status',
                'admin.ai-images.file',
                'admin.account.',
            ];

            abort_unless(str($routeName)->startsWith($allowedRoutes), 403);

            if (str($routeName)->startsWith([
                'admin.manual-posts.',
                'admin.quick-posts.',
                'admin.scheduler.status',
                'admin.ai-images.file',
            ])) {
                $permission = 'post-rapido.gestionar';
            }
        }

        $permission ??= SystemPermissions::forRoute($routeName);

        abort_unless($user && (! $permission || $user->isAdmin() || $user->can($permission)), 403);

        return $next($request);
    }
}
