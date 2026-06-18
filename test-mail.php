<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

// Bootstrap the application
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Get configuration
    $mailConfig = config('mail');
    echo "Mail Configuration:\n";
    echo "- MAILER: " . $mailConfig['default'] . "\n";
    echo "- HOST: " . $mailConfig['mailers']['smtp']['host'] . "\n";
    echo "- PORT: " . $mailConfig['mailers']['smtp']['port'] . "\n";
    echo "- SCHEME: " . $mailConfig['mailers']['smtp']['scheme'] . "\n";
    echo "\n";
    
    // Try sending test email
    \Illuminate\Support\Facades\Mail::raw('Test OTP Email', function ($message) {
        $message->to('rong69320@gmail.com')->subject('Test OTP');
    });
    
    echo "✓ Email sent successfully!\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}
