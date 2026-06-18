#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Cloudinary\Cloudinary;

$cloudName = 'dvcvxg01m'; // YOUR_CLOUD_NAME ← replace this if you use a different cloud
$apiKey = '244261758928465'; // YOUR_API_KEY ← replace this if you use a different key
$apiSecret = '<INSERT_API_SECRET>'; // YOUR_API_SECRET ← replace this

$imageUrl = 'https://cloudinary-devs.github.io/cld-docs-assets/assets/images/coffee_cup.jpg';

if ($apiSecret === '<INSERT_API_SECRET>') {
    fwrite(STDERR, "Please replace YOUR_API_SECRET in this script before running it.\n");
    exit(1);
}

$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => $cloudName,
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
    ],
    'url' => [
        'secure' => true,
    ],
]);

$upload = $cloudinary->uploadApi()->upload($imageUrl, [
    'folder' => 'codex_cloudinary_demo',
    'public_id' => 'optimized_image_' . time(),
    'overwrite' => true,
]);

$publicId = $upload['public_id'];
$uploadedUrl = $upload['secure_url'];

// f_auto lets Cloudinary pick the best image format for the browser, such as AVIF, WebP, or JPG.
// q_auto lets Cloudinary automatically balance visual quality and file size.
$transformedUrl = sprintf(
    'https://res.cloudinary.com/%s/image/upload/f_auto,q_auto/%s',
    rawurlencode($cloudName),
    implode('/', array_map('rawurlencode', explode('/', $publicId)))
);

echo "Upload successful!\n";
echo "Uploaded image URL: {$uploadedUrl}\n";
echo "Public ID: {$publicId}\n";
echo "Format: {$upload['format']}\n";
echo "Dimensions: {$upload['width']} x {$upload['height']}\n";
echo "Bytes: {$upload['bytes']}\n";
echo "\n";
echo "Done! Click link below to see optimized version of the image. Check the size and the format.\n";
echo "{$transformedUrl}\n";
