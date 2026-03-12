<?php

use App\Models\User;

$failedIds = [31, 32];

foreach ($failedIds as $id) {
    echo "\nTesting User ID: $id\n";
    $user = User::find($id);
    if (!$user) {
        echo "Not found!\n";
        continue;
    }
    
    try {
        $user->toArray();
        echo "toArray() SUCCESS!\n";
    } catch (\Throwable $e) {
        echo "toArray() FAILED: " . $e->getMessage() . "\n";
        echo "Let's check each field via Eloquent access:\n";
        
        $attributes = array_keys($user->getAttributes());
        foreach ($attributes as $attr) {
            try {
                $val = $user->$attr; // This triggers the cast
            } catch (\Throwable $e2) {
                echo "  -> CRASH on attribute: $attr (" . $e2->getMessage() . ")\n";
                // Fix it
                $raw = \Illuminate\Support\Facades\DB::table('users')->where('id', $id)->first()->$attr;
                echo "     Raw value: $raw\n";
                
                \Illuminate\Support\Facades\DB::table('users')->where('id', $id)->update([
                    $attr => \Illuminate\Support\Facades\Crypt::encryptString((string)$raw)
                ]);
                echo "     Fixed!\n";
            }
        }
    }
}
