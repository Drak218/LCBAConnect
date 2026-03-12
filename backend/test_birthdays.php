<?php

// Ensure we are logged in so API routes work
$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

// Test Birthdays API
$endpoints = [
    '/api/birthdays/today',
    '/api/birthdays/this-week',
    '/api/birthdays/upcoming'
];

foreach ($endpoints as $uri) {
    echo "Testing $uri...\n";
    $request = \Illuminate\Http\Request::create($uri, 'GET');
    
    try {
        $response = app()->handle($request);
        $data = json_decode($response->getContent(), true);

        if ($response->getStatusCode() === 500) {
            echo "-> 500 Error Generated:\n";
            echo $response->getContent() . "\n";
        } else {
            if ($response->getStatusCode() !== 200) {
                echo "-> Error Code: " . $response->getStatusCode() . "\n";
                echo $response->getContent() . "\n";
                continue;
            }
            
            echo "-> Success: " . (isset($data['success']) && $data['success'] ? 'true' : 'false') . "\n";
            if (isset($data['success']) && $data['success']) {
                $count = count($data['data'] ?? []);
                echo "-> Elements returned: $count\n";
            } else {
                echo "-> Response keys: " . implode(', ', array_keys($data)) . "\n";
            }
        }
    } catch (\Exception $e) {
        echo "Exception Caught: " . $e->getMessage() . "\n";
    }
    echo "---------------------------\n";
}
