<?php

// Simple standalone DB check - no Eloquent, uses raw PDO
$env = file_get_contents(__DIR__ . '/.env');
preg_match('/DB_PORT=(\d+)/', $env, $m);
$port = $m[1] ?? 3306;
preg_match('/DB_DATABASE=(.+)/', $env, $m);
$db = trim($m[1] ?? 'lcbaconnect_db');
preg_match('/DB_USERNAME=(.+)/', $env, $m);
$user = trim($m[1] ?? 'root');
preg_match('/DB_PASSWORD=(.*)/', $env, $m);
$pass = trim($m[1] ?? '');

$pdo = new PDO("mysql:host=127.0.0.1;port={$port};dbname={$db}", $user, $pass);
$stmt = $pdo->query("SELECT id, first_name, last_name, program, batch FROM users LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $firstNameEncrypted = str_starts_with($r['first_name'] ?? '', 'eyJ') ? '✓ ENCRYPTED' : '✗ PLAINTEXT';
    $programEncrypted = str_starts_with($r['program'] ?? '', 'eyJ') ? '✓ ENCRYPTED' : '✗ PLAINTEXT';
    echo "ID: {$r['id']}\n";
    echo "  first_name: {$firstNameEncrypted}\n";
    echo "  program:    {$programEncrypted}\n";
}
echo "\nDone.\n";
