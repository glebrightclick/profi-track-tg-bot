<?php
require_once('vendor/autoload.php');

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;
use SergiX44\Nutgram\Telegram\Types\Input\InputTextMessageContent;

try {
    $bot = new Nutgram('6519530193:AAGgkavYD0qaLtfEHZpM1SFuX3yvjIWSH7U');
    $bot->setRunningMode(Polling::class);

    // $bot->onCommand('')

    $bot->onChosenInlineResult(function (Nutgram $bot) {
        $bot->set('called', true);
        echo 'called' . PHP_EOL;
    });

    $bot->onInlineQuery(function (Nutgram $bot) {
        echo 'inside online query' . PHP_EOL;
        echo $bot->inlineQuery()->query . PHP_EOL;
        $bot->answerInlineQuery(
            [
                \SergiX44\Nutgram\Telegram\Types\Inline\InlineQueryResultArticle::make(
                    1,
                    'Anonymized message',
                    InputTextMessageContent::make(message_text: $bot->inlineQuery()->query, disable_web_page_preview: true),
                )
            ],
            $bot->inlineQuery()->id,
        );
    });

    $bot->onMessage(function (Nutgram $bot) {
        if (!$message = $bot->message()) {
            return;
        }

        foreach (get_object_vars($message) as $var => $value) {
            if (is_null($value)) {
                continue;
            }

            if ($value instanceof SergiX44\Nutgram\Telegram\Types\Chat\Chat) {
                echo 'chat id' . PHP_EOL;
                echo $value->id . PHP_EOL;
                echo '---------' . PHP_EOL;
                // $bot->deleteMessage(chat_id: $message->chat->id, message_id: $message->message_id);
                // $bot->sendMessage(text: $message->text, message_thread_id: 3);
                continue;
            }

            if (is_object($value)) {
                echo get_class($value) . PHP_EOL;
                continue;
            }

            if (is_string($value)) {
                echo $var . ' ' . $value . PHP_EOL;
            } else {
                echo 'interesting' . PHP_EOL;
                echo var_export($var, true). PHP_EOL;
                echo var_export($value, true) . PHP_EOL;
                echo '-----' . PHP_EOL;
            }
        }

        echo PHP_EOL;
    });

    $bot->run(); // finally, begin to process incoming updates
} catch (InvalidArgumentException $e) {
    exit('Wrong token provided');
} catch (\Psr\Container\NotFoundExceptionInterface $e) {
    exit('Not found exception inferface');
} catch (\Psr\Container\ContainerExceptionInterface $e) {
    exit('Container exception interface');
}