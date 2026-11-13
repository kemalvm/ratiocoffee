<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BrandSetting;

echo "--- Current Brand Settings ---\n";
$settings = BrandSetting::allAsArray();
print_r($settings['module_icons']);

BrandSetting::where('key', 'module_icons')->delete();
echo "Module icons reset to default in DB successfully.\n";
