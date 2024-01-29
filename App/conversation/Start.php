<?php

namespace App\Conversation;

use App\Storage\PDO;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

class Start extends Conversation
{
    public string $userHash;

    public function __construct(private PDO $storage) {}

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "Добро пожаловать в чат бота ПрофиТрек:\n" .
            "Бот создан для отправки анонимных сообщений в форум ПрофиТрек VIP\n" .
            "Здесь вы можете выбрать себе уникальный никнейм для общения на форуме\n" .
            "Для обеспечения полной анонимности, убедитесь, что никто не может видеть, когда Вы находитесь *онлайн*\n" .
            "Укажите ветку форума в которой вы хотите продолжить общение использовав команду /anonymous\n" .
            "После выбора настроек, все сообщения в бота будут транслироваться в выбранную ветку форума",
        );

        // add user into db by hash only if it's first request
        $this->userHash = encryptUserId($bot->userId());
        if (is_null($this->storage->getUserSettings($this->userHash))) {
            $this->storage->addEmptyUserSettings($this->userHash, CHAT_ID);

            // send message to admin
            $bot->sendMessage(
                "Новая регистрация на платформе - @" . $bot->user()->username . "\n" .
                "Токен - " . $this->userHash . "\n" .
                "/manage для подтверждения/отказа",
                chat_id: ADMIN_ID,
            );
        }

        $this->end();
    }
}