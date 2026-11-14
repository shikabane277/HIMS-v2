<?php
require __DIR__ . '/../hims-app/vendor/autoload.php';
$app = require_once __DIR__ . '/../hims-app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? 'claudiokhyelandrei@gmail.com';
$u = App\Models\User::where('email', $email)->first();
if ($u) {
    $code = '777888';
    $u->two_factor_code = hash('sha256', $code);
    $u->two_factor_expires_at = now()->addMinutes(5);
    $u->save();
    echo $code;
} else {
    echo "NO_USER";
}
