<?php

namespace App\Conversation;

use App\Storage\PDO;
use SergiX44\Nutgram\Conversations\InlineMenu;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;

class Manage extends InlineMenu
{
    public function __construct(private PDO $storage)
    {
        parent::__construct();
    }

    public function start(Nutgram $bot): void
    {
        // get users with status 'new'
        $emptyStatusUsers = $this->storage->getUsersByFilter(['status' => [PDO::STATUS_NEW]]) ?? [];

        $pendingUsersCount = count($emptyStatusUsers);
        $this->menuText("Пользователей на подтверждении: " . $pendingUsersCount)->clearButtons();
        if ($pendingUsersCount > 0) {
            /** @see self::approveAll() */
            $this->addButtonRow(InlineKeyboardButton::make('Подтвердить всех', callback_data: '@approveAll'));
        }

        foreach ($emptyStatusUsers as $user) {
            /** @see self::handleBlock() */
            $this->addButtonRow(InlineKeyboardButton::make("Отказать " . $user['user_hash'], callback_data: $user['user_hash'] . '@handleBlock'));
        }

        /** @see self::finish() */
        $this->addButtonRow(InlineKeyboardButton::make('Закрыть меню', callback_data: '@finish'));
        $this->showMenu();
    }

    public function approveAll(Nutgram $bot): void
    {
        $this->storage->approveAll();

        $bot->sendMessage("Все текущие заявки были подтверждены!");
        $this->end();
    }

    public function handleBlock(Nutgram $bot): void
    {
        $userHash = $bot->callbackQuery()->data;
        $this->storage->blockByUserHash($userHash);

        $this->start($bot);
    }

    public function finish(Nutgram $bot): void
    {
        $this->end();
    }
}