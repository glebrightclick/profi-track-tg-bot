<?php

namespace App\Conversation;

use App\Storage\PDO;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

class Start extends Conversation
{
    public function __construct(private PDO $storage) {}

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "Добро пожаловать в чат бота ПрофиТрек:\n" .
            "Бот создан для отправки анонимных сообщений в форум ПрофиТрек VIP\n" .
            "Здесь вы можете выбрать себе уникальный никнейм для общения на форуме\n" .
            "Для обеспечения полной анонимности, убедитесь, что никто не может видеть, когда Вы находитесь *онлайн*\n" .
            "Укажите ветку форума в которой вы хотите продолжить общение использовав команду /anonymous\n" .
            "После выбора настроек, станет доступна возможность отправлять анонимные сообщения в выбранную ветку форума",
        );

        // add user into db by hash only if it's first request
        $userHash = encrypt($bot->userId());
        if (is_null($this->storage->getUserSettings($userHash))) {
            $this->storage->addEmptyUserSettings($userHash, CHAT_ID);

            // send message to admin
            $bot->sendMessage(
                "Новая регистрация на платформе - @{$bot->user()->username}\n" .
                "Токен - {$bot->userId()}\n" .
                "/manage для подтверждения/отказа",
                chat_id: ADMIN_ID,
            );
        }

        $this->end();
    }
}