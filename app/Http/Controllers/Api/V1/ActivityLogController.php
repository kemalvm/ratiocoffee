<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    // ─── GET /api/v1/activity-logs ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        // Auto-seed initial system log if empty
        if (ActivityLog::count() === 0) {
            ActivityLog::record(
                action: 'system_init',
                module: 'System',
                description: 'Ratio Coffee ERP Activity Log service initialized successfully.'
            );
        }

        $user = $request->user() ?? auth('sanctum')->user() ?? auth('web')->user() ?? auth()->user();

        if ($user && !$user->relationLoaded('role')) {
            $user->load('role');
        }

        $roleSlug = strtolower($user?->role?->slug ?? '');
        
        // Restricted roles (Employee, Consultant, Partner) only see their own logs.
        // Everyone else (CEO, Owner, Admin, Manager, etc.) sees ALL logs across roles.
        $isRestrictedUser = in_array($roleSlug, ['employee', 'consultant', 'partner']);
        $isGlobalAdmin    = !$user || !$isRestrictedUser;

        $query = ActivityLog::with(['user:id,name,username,email,avatar', 'role:id,name,slug'])
            ->where(function ($q) {
                $q->whereDoesntHave('role', fn($r) => $r->where('slug', 'operator'))
                  ->whereDoesntHave('user.role', fn($r) => $r->where('slug', 'operator'));
            })
            ->when(!$isGlobalAdmin, fn($q) => $q->where('user_id', $user?->id ?? 0))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('action', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
                });
            })
            ->when($request->module, fn($q) => $q->where('module', $request->module))
            ->when($request->action, fn($q) => $q->where('action', 'like', "%{$request->action}%"))
            ->when($request->user_id && $isGlobalAdmin, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at');

        $logs = $query->paginate($request->per_page ?? 50);

        return response()->json($logs);
    }
}
