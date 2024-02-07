<?php

namespace App\Conversation;

use App\Conversation\Helpers\CheckNicknameDecision;
use App\Storage\PDO;
use SergiX44\Nutgram\Conversations\InlineMenu;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

class Anonymous extends InlineMenu
{
    public string $userHash;
    public ?string $nickname = null;
    public ?int $chosenTopicId = null;
    public ?string $nicknameUpdatedAt = null;
    public ?array $topics = null;

    public function __construct(private PDO $storage)
    {
        parent::__construct();
    }

    private function getText(): string
    {
        return "Никнейм: " . ($this->nickname ?: '-') . "\n" .
            "Ветка форума: " . ($this->topics[$this->chosenTopicId] ?? '-');
    }

    private function refreshMenu(?string $customText = null): InlineMenu
    {
        $text = ($customText ? $customText . "\n\n" : '') . $this->getText();
        $this->menuText($text)
            ->orNext('finishSilent')
            ->clearButtons();
        return $this;
    }

    private function applyDefaultButtons(): static
    {
        $this->addButtonRow(
            /** @see self::handleChooseNickname() */
            InlineKeyboardButton::make('Выбрать никнейм', callback_data: '@handleChooseNickname'),
            /** @see self::handleChooseForumTopic() */
            InlineKeyboardButton::make('Выбрать ветку форума', callback_data: '@handleChooseForumTopic')
        );
        // when both nickname and chosen_topic_id is provided, let user send anonymous message
        if (!is_null($this->nickname) && !is_null($this->chosenTopicId)) {
            /** @see self::handleChooseMessage() */
            $this->addButtonRow(
                InlineKeyboardButton::make('Отправить анонимное сообщение', callback_data: '@handleChooseMessage')
            );
        }
        $this->applyFinishButton();
        return $this;
    }

    private function applyBackButton(): static
    {
        /** @see self::back() */
        $this->addButtonRow(InlineKeyboardButton::make('Назад', callback_data: '@back'));
        return $this;
    }

    private function applyFinishButton(): static
    {
        /** @see self::finish() */
        $this->addButtonRow(InlineKeyboardButton::make('Закрыть меню', callback_data: '@finish'));
        return $this;
    }

    public function start(Nutgram $bot): void
    {
        if (!$bot->userId()) {
            $this->end();
            return;
        }

        $topics = $this->storage->getTopics(CHAT_ID);
        if (count($topics) < 1) {
            $bot->sendMessage('Не найдено доступных веток канала');
            $this->end();
            return;
        }

        $this->userHash = encrypt($bot->userId());
        $oldHash = encryptUserId($bot->userId());
        $user = null;
        // try to get users from cache
        if ($quickUsers = $this->storage->getQuickUsers()) {
            $user = $quickUsers[$this->userHash] ?? null;
        }
        // if nothing in cache, use direct storage request
        if (is_null($user)) {
            if ($this->storage->getUserSettings($oldHash)) {
                $this->storage->updateUserHash($oldHash, $this->userHash);
                output("hash was changed automatically: $oldHash -> {$this->userHash}");
            }

            if (!$user = $this->storage->getUserSettings($this->userHash)) {
                $bot->sendMessage("Не найдены настройки пользователя в базе ПрофиТрек " . $this->userHash);
                $this->end();
                return;
            }
        }

        // we check for "approved by admin" status
        if ($user['status'] != PDO::STATUS_APPROVED) {
            $bot->sendMessage(
                "Возможность отправки анонимных сообщений не активирована.\n" .
                "Повторите попытку позднее"
            );
            $this->end();
            return;
        }

        $this->nickname = $user['nickname'];
        $this->chosenTopicId = $user['chosen_topic_id'];
        $this->nicknameUpdatedAt = $user['nickname_updated_at'] ?? null;
        $this->topics = $topics;

        // get current open topic
        $this->refreshMenu();
        $this->applyDefaultButtons()->showMenu();
    }

    public function back(Nutgram $bot): void
    {
        $this->refreshMenu();
        $this->applyDefaultButtons()->showMenu();
    }

    public function handleChooseNickname(Nutgram $bot): void
    {
        if ($this->nicknameUpdatedAt) {
            // add 30 days to nickname_updated_at timestamp and convert to datetime string
            $nicknameLockedUntil = strtotime($this->nicknameUpdatedAt) + 30 * 24 * 60 * 60;
            $now = date('Y-m-d H:i:s');

            if ($now < date('Y-m-d H:i:s', $nicknameLockedUntil)) {
                $this->refreshMenu("Редактирование никнейма недоступно до " . date('d.m.Y', $nicknameLockedUntil));
                $this->applyBackButton()
                    ->showMenu();
                return;
            }
        }

        $text = "Пожалуйста, выберите никнейм.\n" .
            "Никнейм должен быть не длиннее 15 символов и содержать только буквы и цифры для удобства отображения.\n" .
            "Будьте аккуратны при выборе никнейма: повторное изменение будет доступно через месяц!";

        $this->refreshMenu($text);
        $this
            ->applyBackButton()
            ->applyFinishButton()
            /** @see self::handleChosenNickname() */
            ->orNext('handleChosenNickname')
            ->showMenu();
    }

    public function handleChosenNickname(Nutgram $bot): void
    {
        if (!$message = $bot->message()) {
            $this->end();
            return;
        }

        // remove both messages from conversation
        $bot->deleteMessage(chat_id: $bot->userId(), message_id: $message->message_id);

        $text = $message->text;
        $checkNickname = $this->checkNickname($text);
        if ($checkNickname->isRejected()) {
            $this->menuText($checkNickname->reason())->showMenu();
            return;
        }

        $this->nickname = $text;
        $this->storage->updateUserSettings(
            $this->userHash,
            ['nickname' => $this->nickname, 'nickname_updated_at' => date('Y-m-d H:i:s')],
        );

        $this->refreshMenu();
        $this->applyDefaultButtons()->showMenu();
    }

    private function checkNickname(?string $nickname): CheckNicknameDecision
    {
        $decision = new CheckNicknameDecision();
        if (is_null($nickname)) {
            return $decision->rejected("Некорректный формат");
        }

        if (preg_match('/^[a-zA-Zа-яА-Я0-9]+$/u', $nickname) !== 1) {
            return $decision->rejected("Используйте только буквы и цифры");
        }

        $strlen = mb_strlen($nickname);
        if ($strlen > 15) {
            return $decision->rejected("Никнейм должен быть не длиннее 15 символов");
        }

        if (count($this->storage->getUsersByFilter(['nickname' => $nickname]) ?? []) > 0) {
            return $decision->rejected("Никнейм занят");
        }

        return $decision->confirmed();
    }

    public function handleChooseForumTopic(Nutgram $bot): void
    {
        $this->refreshMenu("Выберите ветку форума для отправки анонимного сообщения.");

        $chunks = array_chunk($this->topics, 2, true);
        foreach ($chunks as $chunk) {
            $buttons = [];
            foreach ($chunk as $topic_id => $topic) {
                /** @see self::handleChosenForumTopic() */
                $buttons[] = InlineKeyboardButton::make($topic, callback_data: $topic_id . '@handleChosenForumTopic');
            }
            $this->addButtonRow(...$buttons);
        }

        $this
            ->applyBackButton()
            ->applyFinishButton()
            ->showMenu();
    }

    public function handleChosenForumTopic(Nutgram $bot): void
    {
        $this->chosenTopicId = $bot->callbackQuery()->data;
        $this->storage->updateUserSettings($this->userHash, ['chosen_topic_id' => $this->chosenTopicId]);

        $this->refreshMenu("Ветка форума успешно выбрана!");
        $this->applyDefaultButtons()->showMenu();
    }

    public function handleChooseMessage(Nutgram $bot): void
    {
        if (is_null($this->nickname) || is_null($this->chosenTopicId)) {
            return;
        }

        $this->refreshMenu("Введите анонимное сообщение.");
        $this
            ->applyBackButton()
            ->applyFinishButton()
            /** @see self::handleSendMessage() */
            ->orNext('handleSendMessage')
            ->showMenu();
    }

    public function handleSendMessage(Nutgram $bot): void
    {
        // verify that bot has message
        if (!$message = $bot->message()) {
            $this->end();
            return;
        }

        $bot->deleteMessage(chat_id: $bot->userId(), message_id: $message->message_id);

        $text = $message->text;
        if (is_null($text)) {
            $this->menuText("Формат анонимных сообщений может включать в себя только текст и текстовые эмоджи. Повторите попытку.")->showMenu();
            return;
        }

        // CHAT_ID is ProfiTrack chat identifier
        if ($bot->sendMessage("$this->nickname: $text", chat_id: CHAT_ID, message_thread_id: $this->chosenTopicId)) {

            $this->refreshMenu("Анонимное сообщение успешно отправлено!");
            $this->applyDefaultButtons()->showMenu();
        }
    }

    public function showMenu(bool $reopen = false, bool $noHandlers = false, bool $noMiddlewares = false): Message|null
    {
        $this->storage->close();

        return parent::showMenu($reopen, $noHandlers, $noMiddlewares);
    }

    public function finish(Nutgram $bot): void
    {
        $bot->sendMessage("Меню анонимизации было закрыто!\n\n/anonymous для возврата в меню");
        $this->end();
    }

    public function finishSilent(Nutgram $bot): void
    {
        $bot->sendMessage("Меню было автоматически закрыто!\n\n/anonymous для возврата в меню");
        $this->end();
    }

    protected function end(): void
    {
        $this->storage->close();
        parent::end();
    }
}