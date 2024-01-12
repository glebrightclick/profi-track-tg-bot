<?php

function encryptUserId(int $userId): string
{
    // SALT is just a string that we use to hash user id
    return md5($userId . SALT);
}

function getStorage(): \App\Storage\AbstractStorage
{
    return new \App\Storage\PDO(); // new \App\Storage\PDOPlusMemcache();
}
