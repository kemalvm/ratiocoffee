<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SetupController extends Controller
{
    /**
     * Check if the application has completed initial setup (i.e. CEO exists).
     */
    public function status(): JsonResponse
    {
        $ceoRole = Role::where('slug', 'ceo')->first();
        $isSetup = $ceoRole ? User::where('role_id', $ceoRole->id)->exists() : false;

        return response()->json([
            'is_setup' => $isSetup,
        ]);
    }

    /**
     * Complete Installation Wizard: Create Coffee Shop Outlet, Default Warehouse, and CEO Account.
     */
    public function install(Request $request): JsonResponse
    {
        $ceoRole = Role::where('slug', 'ceo')->first();

        if ($ceoRole && User::where('role_id', $ceoRole->id)->exists()) {
            return response()->json([
                'message' => 'Sistem sudah dikonfigurasi. Silakan login ke dashboard.',
            ], 422);
        }

        $validated = $request->validate([
            // Shop Details
            'shop_name'     => ['required', 'string', 'max:200'],
            'address'       => ['nullable', 'string'],
            'phone'         => ['nullable', 'string'],
            'email'         => ['nullable', 'email'],
            'timezone'      => ['required', 'string'],
            // CEO Credentials
            'name'          => ['required', 'string', 'max:250'],
            'username'      => ['required', 'string', 'min:3', 'max:50', 'unique:users,username', 'alpha_dash'],
            'ceo_email'     => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', Password::defaults()],
        ]);

        return DB::transaction(function () use ($validated, $ceoRole) {
            // 1. Create Primary HQ Outlet
            $outlet = Outlet::create([
                'code'     => 'HQ-01',
                'name'     => $validated['shop_name'],
                'address'  => $validated['address'] ?? 'Alamat Utama',
                'phone'    => $validated['phone'] ?? null,
                'email'    => $validated['email'] ?? null,
                'timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
                'active'   => true,
            ]);

            // 2. Create Default Warehouse
            $warehouse = Warehouse::create([
                'outlet_id'  => $outlet->id,
                'code'       => 'WH-HQ',
                'name'       => 'Gudang Utama',
                'address'    => $validated['address'] ?? null,
                'is_default' => true,
                'active'     => true,
            ]);

            // 3. Create CEO User
            $user = User::create([
                'role_id'     => $ceoRole->id,
                'outlet_id'   => $outlet->id,
                'name'        => $validated['name'],
                'username'    => $validated['username'],
                'email'       => $validated['ceo_email'],
                'password'    => Hash::make($validated['password']),
                'position'    => 'Chief Executive Officer',
                'status'      => 'active',
                'employee_id' => 'EMP-001',
            ]);

            // Issue Sanctum Token
            $token = $user->createToken('auth-token')->plainTextToken;

            ActivityLog::record(
                action:      'system_initialization',
                module:      'system',
                entity:      'User',
                entityId:    $user->id,
                description: "Initial installation completed. CEO account {$user->username} created for {$outlet->name}.",
            );

            return response()->json([
                'message'      => 'Instalasi Ratio Coffee ERP Berhasil!',
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'user'         => $user->load('role', 'outlet'),
                'outlet'       => $outlet,
                'warehouse'    => $warehouse,
            ], 201);
        });
    }
}
