<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // ─── GET /api/v1/users ────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['role', 'outlet'])
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('username', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->role_id, fn($q) => $q->where('role_id', $request->role_id))
            ->when($request->outlet_id, fn($q) => $q->where('outlet_id', $request->outlet_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        $users = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json($users);
    }

    // ─── POST /api/v1/users ───────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id'     => ['required', 'exists:roles,id'],
            'outlet_id'   => ['nullable', 'exists:outlets,id'],
            'name'        => ['required', 'string', 'max:255'],
            'username'    => ['nullable', 'string', 'unique:users,username', 'min:3'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'password'    => ['required', 'string', 'min:6'],
            'phone'       => ['nullable', 'string'],
            'position'    => ['nullable', 'string'],
            'employee_id' => ['nullable', 'string', 'unique:users,employee_id'],
            'status'      => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        if (empty($data['username'])) {
            $baseUsername = explode('@', $data['email'])[0];
            $username = \Illuminate\Support\Str::slug($baseUsername, '_');
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = \Illuminate\Support\Str::slug($baseUsername, '_') . '_' . $counter++;
            }
            $data['username'] = $username;
        }

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        ActivityLog::record(
            action:      'create_user',
            module:      'users',
            entity:      'User',
            entityId:    $user->id,
            newData:     $data,
            description: "Created user: {$user->name} ({$user->username})",
        );

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'user'    => $user->load('role', 'outlet'),
        ], 201);
    }

    // ─── GET /api/v1/users/{id} ───────────────────────────────────────────

    public function show(User $user): JsonResponse
    {
        return response()->json(['user' => $user->load('role', 'outlet', 'delegations.grantedBy')]);
    }

    // ─── PATCH /api/v1/users/{id} ─────────────────────────────────────────

    public function update(Request $request, User $user): JsonResponse
    {
        $old = $user->toArray();

        $data = $request->validate([
            'role_id'   => ['sometimes', 'exists:roles,id'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'name'      => ['sometimes', 'string', 'max:255'],
            'username'  => ['sometimes', 'string', Rule::unique('users', 'username')->ignore($user->id)],
            'email'     => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'     => ['nullable', 'string'],
            'position'  => ['nullable', 'string'],
            'status'    => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $user->update($data);

        ActivityLog::record(
            action:      'update_user',
            module:      'users',
            entity:      'User',
            entityId:    $user->id,
            oldData:     $old,
            newData:     $data,
            description: "Updated user: {$user->name}",
        );

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'user'    => $user->load('role', 'outlet'),
        ]);
    }

    // ─── DELETE /api/v1/users/{id} ────────────────────────────────────────

    public function destroy(User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Anda tidak dapat menghapus akun sendiri.'], 422);
        }

        // Prevent deletion of CEO role if only one CEO exists
        if ($user->isCeo() && User::where('role_id', $user->role_id)->count() === 1) {
            return response()->json(['message' => 'Setidaknya harus ada satu CEO aktif.'], 422);
        }

        ActivityLog::record(
            action:      'delete_user',
            module:      'users',
            entity:      'User',
            entityId:    $user->id,
            oldData:     $user->toArray(),
            description: "Soft-deleted user: {$user->name}",
        );

        $user->delete();

        return response()->json(['message' => 'User berhasil dihapus.']);
    }

    // ─── POST /api/v1/users/{id}/reset-password ───────────────────────────

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        $user->tokens()->delete(); // revoke all sessions

        ActivityLog::record(
            action:      'reset_password',
            module:      'users',
            entity:      'User',
            entityId:    $user->id,
            description: "Password reset for user: {$user->name}",
        );

        return response()->json(['message' => 'Password berhasil direset.']);
    }
}
