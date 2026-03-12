<?php

$usersCount = \App\Models\User::where('role', 'alumni')->count();
echo "Users table alumni count: " . $usersCount . "\n";

$legacyCount = \Illuminate\Support\Facades\Schema::hasTable('alumni') ? \App\Models\Alumni::count() : 0;
echo "Legacy alumni table count: " . $legacyCount . "\n";

$total = $usersCount + $legacyCount;
echo "Total theoretical alumni: " . $total . "\n";

// Let's also run the API again and see what exactly is getting filtered out
$request = \Illuminate\Http\Request::create('/api/users', 'GET', ['role' => 'alumni']);
$response = app()->handle($request);
$data = json_decode($response->getContent(), true);
$apiCount = count($data['data'] ?? []);
echo "API returned count: " . $apiCount . "\n";

if ($apiCount < $total) {
    echo "\nLet's find out who was filtered out!\n";
    
    // Get all theoretical IDs
    $userAlumni = \App\Models\User::where('role', 'alumni')->get();
    
    $passed = 0;
    $failed = 0;
    
    foreach ($userAlumni as $user) {
        try {
            $user->toArray();
            $passed++;
        } catch (\Throwable $e) {
            $failed++;
            echo "Failed User ID: " . $user->id . " - Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nPassed Users: $passed\n";
    echo "Failed Users: $failed\n";
}
