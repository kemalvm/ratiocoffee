<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * Logs every mutating request (POST / PUT / PATCH / DELETE) automatically.
     * Usage: Route::middleware('log.activity:module,action')
     */
    public function handle(Request $request, Closure $next, string $module = null, string $action = null): Response
    {
        $response = $next($request);

        // Only log mutating methods (POST, PUT, PATCH, DELETE)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $path = $request->path();

            // Skip internal polling or setup routes
            if (str_contains($path, 'notifications') || str_contains($path, 'setup/status')) {
                return $response;
            }

            $method = $request->method();
            $actionVerb = match ($method) {
                'POST'   => 'Menambahkan / Membuat',
                'PUT', 'PATCH' => 'Mengubah / Perbarui',
                'DELETE' => 'Menghapus',
                default  => 'Mengakses'
            };

            $segments = $request->segments(); // e.g. ['api', 'v1', 'inventory', 'ingredients']
            $resolvedModule = $module ?? ($segments[2] ?? $segments[1] ?? 'System');
            $resourceName   = $segments[3] ?? $segments[2] ?? 'Data';

            $resolvedAction = $action ?? (strtolower($method) . '_' . $resourceName);
            $description    = "{$actionVerb} data {$resourceName} ({$method} /{$path})";

            ActivityLog::record(
                action:      $resolvedAction,
                module:      ucfirst($resolvedModule),
                description: $description,
            );
        }

        return $response;
    }
}
