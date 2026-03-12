<?php

$u = App\Models\User::where('role', 'alumni')->first();
echo "first_name: " . $u->first_name . "\n";
echo "city: " . $u->city . "\n";
echo "program: " . $u->program . "\n";
