<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$roles = DB::table('roles')->get();
echo "ROLES COUNT: " . count($roles) . "\n";
foreach ($roles as $r) {
    echo "ID: {$r->id} | Name: {$r->name} | Slug: {$r->slug}\n";
}

$users = DB::table('users')->select('id', 'name', 'email', 'role_id')->get();
echo "\nUSERS:\n";
foreach ($users as $u) {
    echo "ID: {$u->id} | Name: {$u->name} | Role ID: {$u->role_id}\n";
}
