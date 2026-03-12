<?php

$user = \App\Models\User::where('role', 'alumni')->first();
if ($user) {
    \Illuminate\Support\Facades\Auth::login($user);
}

$request = \Illuminate\Http\Request::create('/api/users/filter-options', 'GET');
$response = app()->handle($request);
$content = $response->getContent();
$data = json_decode($content, true);

if (isset($data['success']) && $data['success']) {
    echo "Success: true\n";
    foreach (['cities', 'industries', 'programs', 'batches'] as $key) {
        $count = count($data['data'][$key] ?? []);
        echo ucfirst($key) . " count: " . $count . "\n";
        if ($count > 0) {
            echo "  First " . $key . ": " . print_r(array_slice($data['data'][$key], 0, 1), true) . "\n";
        }
    }
} else {
    echo "Success: false\n";
    echo "Message: " . ($data['message'] ?? 'Unknown Error') . "\n";
}
