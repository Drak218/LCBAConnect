<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$user = \App\Models\User::where('role', 'alumni')->first();
if (!$user) { echo "No alumni user found\n"; exit; }
\Illuminate\Support\Facades\Auth::login($user);
$request = \Illuminate\Http\Request::create('/api/users?role=alumni', 'GET');
$response = app()->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
