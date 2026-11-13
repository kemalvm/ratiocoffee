<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Usage: Route::middleware('permission:inventory.create')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user() ?? auth()->guard('sanctum')->user() ?? auth()->guard('web')->user() ?? auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->can($permission)) {
            return response()->json([
                'message'    => 'Forbidden. You do not have the required permission.',
                'permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
