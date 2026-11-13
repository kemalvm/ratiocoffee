<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TaxSetting;
use Illuminate\Http\Request;

class TaxSettingController extends Controller
{
    /**
     * Display the current tax settings. (Available to all logged-in staff for POS & Billing)
     */
    public function show()
    {
        $settings = TaxSetting::getSettings();
        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update restaurant tax settings. (STRICTLY RESTRICTED TO CEO ONLY!)
     */
    public function update(Request $request)
    {
        $user = auth()->user();
        
        // Strict role verification: Only CEO can access and modify restaurant tax settings!
        $isCeo = ($user->role?->slug === 'ceo') || (strtolower($user->role?->name ?? '') === 'ceo') || ($user->is_ceo ?? false);

        if (!$isCeo) {
            return response()->json([
                'success' => false,
                'message' => 'Akses Ditolak. Modul Pengaturan Pajak Resto hanya dapat diakses dan diubah oleh CEO.',
            ], 403);
        }

        $validated = $request->validate([
            'tax_name' => 'required|string|max:100',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'is_enabled' => 'required|boolean',
            'tax_type' => 'nullable|string',
            'service_charge_name' => 'nullable|string|max:100',
            'service_charge_rate' => 'nullable|numeric|min:0|max:100',
            'is_service_charge_enabled' => 'nullable|boolean',
        ]);

        $settings = TaxSetting::getSettings();
        $validated['tax_type'] = 'charged_to_hpp'; // Always enforce charged_to_hpp for Ratio Coffee rule
        $validated['updated_by'] = $user->id;
        $settings->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan Pajak Resto berhasil diperbarui oleh CEO! (Metode: Dibebankan ke HPP/Margin Toko)',
            'data' => $settings,
        ]);
    }
}
