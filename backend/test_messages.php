<?php

$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

$request = \Illuminate\Http\Request::create('/api/messages', 'GET');
try {
    $response = app()->handle($request);
    
    if ($response->getStatusCode() === 500) {
        echo "500 Error Generated in Messages API:\n";
        echo $response->getContent() . "\n";
    } else {
        echo "Messages API Success: " . $response->getStatusCode() . "\n";
        $data = json_decode($response->getContent(), true);
        echo "Keys: " . implode(', ', array_keys($data)) . "\n";
    }
} catch (\Exception $e) {
    echo "Exception Caught: " . $e->getMessage() . "\n";
}
