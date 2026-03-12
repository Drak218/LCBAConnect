<?php
$user = \App\Models\User::where('role', 'alumni')->first();
if ($user) {
    \Illuminate\Support\Facades\Auth::login($user);
}

$request = \Illuminate\Http\Request::create('/api/job-posts', 'GET');
try {
    $response = app()->handle($request);
    $data = json_decode($response->getContent(), true);

    if ($response->getStatusCode() === 500) {
        echo "500 Error Generated in Jobs API:\n";
        echo $response->getContent() . "\n";
    } else {
        echo "Jobs API Success: " . (isset($data['success']) && $data['success'] ? 'true' : 'false') . "\n";
        if (isset($data['success']) && $data['success']) {
            echo "Total elements: " . count($data['data']['data'] ?? $data['data'] ?? []) . "\n";
        } else {
            print_r($data);
        }
    }
} catch (\Exception $e) {
    echo "Exception Caught: " . $e->getMessage() . "\n";
}
