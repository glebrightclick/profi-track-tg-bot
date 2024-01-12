<?php

namespace App\Storage;

abstract class AbstractStorage
{
    // gets all users settings
    abstract public function getQuickUsers(): array;
    // get users by filter
    abstract public function getUsersByFilter(array $filterData): ?array;
    // add empty user to storage
    abstract public function addEmptyUserSettings(string $userHash): void;
    // updates user settings in storage
    abstract public function updateUserSettings(string $userHash, array $updateData): bool;
    // get single user settings or null if settings are empty
    abstract public function getUserSettings(string $userHash): ?array;
    // confirm all pending users
    abstract public function approveAll(): void;
    // reject user by user hash
    abstract public function blockByUserHash(string $userHash): bool;
    // get all available topics to post anonymous post
    abstract public function getTopics(): array;
}