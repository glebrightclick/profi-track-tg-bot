<?php

function encryptUserId(int $userId): string
{
    // SALT is just a string that we use to hash user id
    return md5($userId . SALT);
}

function output(string $text): void
{
    $date = date('Y-m-d H:i:s');
    echo "[$date]: $text\n";
}
