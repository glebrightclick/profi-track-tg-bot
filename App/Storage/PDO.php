<?php

namespace App\Storage;

use PDO as Library;
use SergiX44\Nutgram\Exception\InvalidDataException;

class PDO extends AbstractStorage
{
    public const STATUS_NEW = 'new';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_BLOCKED = 'blocked';

    private Library $pdo;

    public function __construct()
    {
        $this->pdo = new Library("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $this->pdo->setAttribute(Library::ATTR_ERRMODE, Library::ERRMODE_EXCEPTION);
    }

    public function getQuickUsers(): array
    {
        // cached users info isn't available
        return [];
    }

    /**
     * CREATE TABLE `user_settings` (
     * `user_hash` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
     * `nickname` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
     * `chosen_topic_id` int(64) DEFAULT NULL,
     * `status` enum('new','approved','blocked') COLLATE utf8_unicode_ci DEFAULT 'new',
     * PRIMARY KEY (`user_hash`),
     * KEY `chosen_topic_id` (`chosen_topic_id`),
     * CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`chosen_topic_id`) REFERENCES `topics` (`id`)
     * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
     */
    public function getUserSettings(string $userHash): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_settings WHERE user_hash = :userHash");
        $stmt->bindParam(':userHash', $userHash);
        $stmt->execute();

        return $stmt->fetch(Library::FETCH_ASSOC) ?: null;
    }

    public function getUsersByFilter(array $filterData): ?array
    {
        $sql = "SELECT * FROM user_settings WHERE 1";

        if (isset($filterData['status'])) {
            $statuses = implode(', ', array_fill(0, count($filterData['status']), '?'));
            $sql .= " AND status IN ($statuses)";
        }

        $sql .= " GROUP BY user_hash";
        $stmt = $this->pdo->prepare($sql);
        if (isset($filterData['status'])) {
            foreach ($filterData['status'] as $index => $status) {
                $stmt->bindValue($index + 1, $status);
            }
        }
        $stmt->execute();

        return $stmt->fetchAll(Library::FETCH_ASSOC) ?: null;
    }

    public function addEmptyUserSettings(string $userHash): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO user_settings (user_hash) VALUES (:userHash)");
        $stmt->bindParam(':userHash', $userHash);
        $stmt->execute();
    }

    public function updateUserSettings(string $userHash, array $updateData): bool
    {
        $sql = "UPDATE user_settings SET";

        if (isset($updateData['nickname'])) {
            $sql .= " nickname = :newNickname,";
        }
        if (isset($updateData['chosen_topic_id'])) {
            $sql .= " chosen_topic_id = :newChosenTopicId,";
        }

        $sql = rtrim($sql, ',');
        $sql .= " WHERE user_hash = :userHash";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':userHash', $userHash);
        if (isset($updateData['nickname'])) {
            $stmt->bindParam(':newNickname', $updateData['nickname']);
        }
        if (isset($updateData['chosen_topic_id'])) {
            $stmt->bindParam(':newChosenTopicId', $updateData['chosen_topic_id'], Library::PARAM_INT);
        }
        $stmt->execute();

        // Return the number of affected rows (if needed)
        return $stmt->rowCount() > 0;
    }

    public function approveAll(): void
    {
        $stmt = $this->pdo->prepare("UPDATE user_settings SET status = :approved WHERE status = :new");
        $approved = self::STATUS_APPROVED;
        $new = self::STATUS_NEW;
        $stmt->bindParam(":new", $new);
        $stmt->bindParam(":approved", $approved);
        $stmt->execute();
    }

    public function blockByUserHash(string $userHash): bool
    {
        $stmt = $this->pdo->prepare("UPDATE user_settings SET status = :rejected WHERE user_hash = :userHash");
        $status = self::STATUS_BLOCKED;
        $stmt->bindParam(':rejected', $status);
        $stmt->bindParam(':userHash', $userHash);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * CREATE TABLE `topics` (
     * `id` int(64) NOT NULL,
     * `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
     * `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
     * PRIMARY KEY (`id`)
     * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
     */
    public function getTopics(): array
    {
        $stmt = $this->pdo->prepare("select id, name, date_created from topics order by date_created asc");
        $stmt->execute();

        if (!$topics = $stmt->fetchAll(Library::FETCH_ASSOC)) {
            return [];
        }

        return array_combine(array_column($topics, 'id'), array_column($topics, 'name'));
    }
}