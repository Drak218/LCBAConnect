<?php
$user = \App\Models\User::where('role', 'alumni')->first();
if (!$user) {
    echo "No alumni user found\n";
    exit;
}

\Illuminate\Support\Facades\Auth::login($user);
$request = \Illuminate\Http\Request::create('/api/users', 'GET');
$response = app()->handle($request);
echo "Users response status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() >= 500) {
    echo $response->getContent() . "\n";
}

$request = \Illuminate\Http\Request::create('/api/job-posts', 'GET');
$response = app()->handle($request);
echo "Job posts response status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() >= 500) {
    echo $response->getContent() . "\n";
}
