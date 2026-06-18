<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

// Bootstrap the application
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Simulate forgotPassword call
    $email = 'rong69320@gmail.com';
    $otp = '123456';
    
    echo "Testing OTP Mailable...\n";
    echo "- Email: $email\n";
    echo "- OTP: $otp\n\n";
    
    // Send using OtpMail
    \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\OtpMail($otp, $email));
    
    echo "✓ OTP Email sent successfully with Mailable!\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}
