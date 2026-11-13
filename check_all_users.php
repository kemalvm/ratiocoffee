<?php
require 'c:/xampp/htdocs/ratio_coffee/vendor/autoload.php';
$app = require_once 'c:/xampp/htdocs/ratio_coffee/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$allUsers = \App\Models\User::withTrashed()->with('role')->get();
echo "ALL USERS (INCLUDING TRASHED): " . $allUsers->count() . "\n\n";

foreach ($allUsers as $u) {
    echo "ID: {$u->id}\n";
    echo "  Name: {$u->name}\n";
    echo "  Username: {$u->username}\n";
    echo "  Email: {$u->email}\n";
    echo "  Role ID: {$u->role_id} (" . ($u->role ? $u->role->name : 'No Role') . ")\n";
    echo "  Deleted At: " . ($u->deleted_at ?? 'Active') . "\n";
    echo "----------------------------------------\n";
}
