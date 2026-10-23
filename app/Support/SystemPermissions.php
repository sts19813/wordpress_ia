<?php

namespace App\Support;

class SystemPermissions
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'post-rapido.gestionar' => 'Crear y publicar desde Post rápido',
            'contenido.gestionar' => 'Gestionar noticias obtenidas',
            'empresas.gestionar' => 'Gestionar empresas y fuentes',
            'ia.gestionar' => 'Gestionar contenido generado por IA',
            'publicaciones.gestionar' => 'Gestionar publicaciones y programación',
            'logs.ver' => 'Consultar y depurar logs del sistema',
            'configuracion.gestionar' => 'Gestionar configuración y perfiles',
            'usuarios.gestionar' => 'Gestionar usuarios, roles y permisos',
        ];
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::labels());
    }

    /** @return list<string> */
    public static function operatorDefaults(): array
    {
        return ['post-rapido.gestionar'];
    }

    public static function label(string $permission): string
    {
        return self::labels()[$permission] ?? str($permission)->replace(['.', '_'], ' ')->headline()->toString();
    }

    public static function forRoute(?string $routeName): ?string
    {
        $routes = [
            'post-rapido.gestionar' => [
                'admin.manual-posts.',
                'admin.quick-posts.',
            ],
            'contenido.gestionar' => ['admin.news.', 'admin.source-post-media.'],
            'empresas.gestionar' => ['admin.companies.', 'admin.source-sites.', 'admin.source-scan-logs.'],
            'ia.gestionar' => ['admin.ai-articles.', 'admin.ai-images.', 'admin.ai-production-report.'],
            'publicaciones.gestionar' => ['admin.publications.', 'admin.wordpress-sites.', 'admin.scheduler.', 'admin.x-oauth.'],
            'logs.ver' => ['admin.system-logs.'],
            'configuracion.gestionar' => ['admin.settings.'],
            'usuarios.gestionar' => ['admin.users.', 'admin.roles.', 'admin.permissions.'],
        ];

        foreach ($routes as $permission => $prefixes) {
            if (str($routeName)->startsWith($prefixes)) {
                return $permission;
            }
        }

        return null;
    }
}
