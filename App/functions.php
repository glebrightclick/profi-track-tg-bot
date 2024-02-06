<?php

function encryptUserId(int $userId): string
{
    // SALT is just a string that we use to hash user id
    return md5($userId . SALT);
}

// Encryption function
function encrypt(int $userId): string {
    $cipher = "aes-256-cbc"; // AES encryption with a 256-bit key in CBC mode
    $encrypted = openssl_encrypt($userId, $cipher, SALT, OPENSSL_RAW_DATA, IV);
    return base64_encode(IV . $encrypted);
}

// Decryption function
function decrypt(string $encryptedUserId): int {
    $cipher = "aes-256-cbc"; // AES encryption with a 256-bit key in CBC mode
    $data = base64_decode($encryptedUserId);
    $iv_length = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $iv_length);
    $data = substr($data, $iv_length);
    return openssl_decrypt($data, $cipher, SALT, OPENSSL_RAW_DATA, $iv);
}

function output(string $text): void
{
    $date = date('Y-m-d H:i:s');
    echo "[$date]: $text\n";
}
