<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Delegation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DelegationController extends Controller
{
    // ─── GET /api/v1/delegations ──────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $delegations = Delegation::with(['user', 'grantedBy'])
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->module, fn($q) => $q->where('module', $request->module))
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($delegations);
    }

    // ─── POST /api/v1/delegations ─────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'     => ['required', 'exists:users,id'],
            'module'      => ['required', 'string'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'in:create,read,update,delete,approve,export,import'],
            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'notes'       => ['nullable', 'string'],
        ]);

        $data['granted_by'] = auth()->id();
        $data['active']     = true;

        $delegation = Delegation::create($data);

        $target = User::find($data['user_id']);

        ActivityLog::record(
            action:      'create_delegation',
            module:      'delegation',
            entity:      'Delegation',
            entityId:    $delegation->id,
            newData:     $data,
            description: "Delegated module '{$data['module']}' to user: {$target->name}",
        );

        return response()->json([
            'message'    => 'Delegasi berhasil dibuat.',
            'delegation' => $delegation->load('user', 'grantedBy'),
        ], 201);
    }

    // ─── PATCH /api/v1/delegations/{id} ───────────────────────────────────

    public function update(Request $request, Delegation $delegation): JsonResponse
    {
        $old = $delegation->toArray();

        $data = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'in:create,read,update,delete,approve,export,import'],
            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'active'      => ['sometimes', 'boolean'],
            'notes'       => ['nullable', 'string'],
        ]);

        $delegation->update($data);

        ActivityLog::record(
            action:      'update_delegation',
            module:      'delegation',
            entity:      'Delegation',
            entityId:    $delegation->id,
            oldData:     $old,
            newData:     $data,
        );

        return response()->json([
            'message'    => 'Delegasi berhasil diperbarui.',
            'delegation' => $delegation->fresh()->load('user', 'grantedBy'),
        ]);
    }

    // ─── DELETE /api/v1/delegations/{id} (revoke) ─────────────────────────

    public function destroy(Delegation $delegation): JsonResponse
    {
        ActivityLog::record(
            action:      'revoke_delegation',
            module:      'delegation',
            entity:      'Delegation',
            entityId:    $delegation->id,
            oldData:     $delegation->toArray(),
            description: "Revoked delegation for module: {$delegation->module}",
        );

        $delegation->delete();

        return response()->json(['message' => 'Delegasi berhasil dicabut.']);
    }
}
