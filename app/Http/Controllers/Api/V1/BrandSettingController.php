<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BrandSetting;
use Illuminate\Http\Request;

if (!class_exists(\App\Http\Controllers\Api\V1\BrandSettingController::class)) {
    class BrandSettingController extends Controller
    {
        /**
         * GET /api/v1/brand-settings
         * Returns all brand settings as key→value map.
         */
        public function show()
        {
            return response()->json(BrandSetting::allAsArray());
        }

        /**
         * POST/PUT /api/v1/brand-settings
         * Accepts: { brand_name, brand_tagline, brand_logo, brand_logo_file, operating_hours_open, operating_hours_close }
         */
        public function update(Request $request)
        {
            $data = $request->validate([
                'brand_name'            => 'nullable|string|max:100',
                'brand_tagline'         => 'nullable|string|max:100',
                'tagline'               => 'nullable|string|max:100',
                'brand_logo'            => 'nullable|string',
                'operating_hours_open'  => 'nullable|string|max:30',
                'operating_hours_close' => 'nullable|string|max:30',
                'address'               => 'nullable|string|max:255',
                'module_icons'          => 'nullable',
            ]);

            if (isset($data['module_icons'])) {
                $icons = $data['module_icons'];
                if (is_array($icons)) {
                    $val = json_encode($icons, JSON_UNESCAPED_UNICODE);
                } elseif (is_string($icons)) {
                    $decoded = json_decode($icons, true);
                    $val = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : $icons;
                } else {
                    $val = (string) $icons;
                }
                BrandSetting::set('module_icons', $val);
                unset($data['module_icons']);
            }

            if (isset($data['tagline']) && !isset($data['brand_tagline'])) {
                $data['brand_tagline'] = $data['tagline'];
            }
            unset($data['tagline']);

            if ($request->hasFile('brand_logo_file')) {
                $file = $request->file('brand_logo_file');
                if ($file->isValid()) {
                    $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
                    $filename = 'logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $dest = public_path('uploads/brand');
                    if (!file_exists($dest)) {
                        mkdir($dest, 0777, true);
                    }
                    $file->move($dest, $filename);
                    $data['brand_logo'] = '/uploads/brand/' . $filename;
                }
            }
            unset($data['brand_logo_file']);

            foreach ($data as $key => $value) {
                if ($value !== null) {
                    BrandSetting::set($key, $value);
                }
            }

            return response()->json([
                'message'  => 'Brand settings updated successfully.',
                'settings' => BrandSetting::allAsArray(),
            ]);
        }

        /**
         * POST /api/v1/brand-settings/logo
         * Handles multipart & base64 logo image upload from HQ Control Center & settings
         */
        public function uploadLogo(Request $request)
        {
            $file = $request->file('logo') ?? $request->file('brand_logo_file') ?? $request->file('file');

            if ($file && $file->isValid()) {
                $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
                $filename = 'logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
                
                $dest = public_path('uploads/brand');
                if (!file_exists($dest)) {
                    mkdir($dest, 0777, true);
                }
                $file->move($dest, $filename);
                
                $logoUrl = '/uploads/brand/' . $filename;
                BrandSetting::set('brand_logo', $logoUrl);

                return response()->json([
                    'message'  => 'Logo brand berhasil diunggah.',
                    'logo_url' => $logoUrl,
                    'settings' => BrandSetting::allAsArray(),
                ]);
            }

            // Support base64 upload or string URL
            $logoInput = $request->input('logo') ?? $request->input('brand_logo') ?? $request->input('logo_url');
            if ($logoInput) {
                if (str_starts_with($logoInput, 'data:image')) {
                    @list($type, $data) = explode(';', $logoInput);
                    @list(, $data)      = explode(',', $data);
                    if ($data) {
                        $decoded = base64_decode($data);
                        $ext = 'png';
                        if (str_contains($type, 'jpeg') || str_contains($type, 'jpg')) $ext = 'jpg';
                        if (str_contains($type, 'webp')) $ext = 'webp';
                        
                        $filename = 'logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $dest = public_path('uploads/brand');
                        if (!file_exists($dest)) {
                            mkdir($dest, 0777, true);
                        }
                        file_put_contents($dest . '/' . $filename, $decoded);
                        $logoInput = '/uploads/brand/' . $filename;
                    }
                }

                BrandSetting::set('brand_logo', $logoInput);
                return response()->json([
                    'message'  => 'Logo brand berhasil diperbarui.',
                    'logo_url' => $logoInput,
                    'settings' => BrandSetting::allAsArray(),
                ]);
            }

            return response()->json(['message' => 'Tidak ada berkas logo yang valid diunggah.'], 400);
        }
    }
}
