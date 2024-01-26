<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.local.php';
require_once __DIR__ . '/App/functions.php';

use App\Conversation\Anonymous;
use App\Conversation\Manage;
use App\Conversation\Start;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;

try {
    $config = new \SergiX44\Nutgram\Configuration(
        enableHttp2: false,
    );
    $bot = new Nutgram(TOKEN, $config);
    $bot->setRunningMode(Polling::class);
    Conversation::refreshOnDeserialize();

    echo "----------\n";
    echo strtotime(date('now')) . "\n";

    // restricted to private chat only
    $private = function (Nutgram $bot, $next) {
        if ($bot->userId() != $bot->chatId() && $bot->chatId() != CHAT_ID) {
            echo "----------\n";
            echo "attempt from chat: " . $bot->chatId() . "\n";
            return;
        }

        $next($bot);
    };
    $bot->middleware($private);

    // /start command handler
    // we react with description of bot functionality
    $bot->onCommand('start', Start::class);

    // /anonymous command handler
    // shows context menu with opportunity to select nickname/topics/activate "send" mode
    $bot->onCommand('anonymous', Anonymous::class);

    // /manage command handler
    // admin tool to approve/reject registrations on platform
    $admin = function(Nutgram $bot, $next) {
        if ($bot->userId() != ADMIN_ID) {
            return;
        }

        $next($bot);
    };
    $bot->onCommand('manage', Manage::class)->middleware($admin);

    // /help command handler
    // displays description of all commands
    // $bot->onCommand('help', Help::class);

    $bot->onMessage(function(Nutgram $bot) {
        echo "----------\n";
        echo "user_id: " . $bot->userId() . "\n";
        echo "chat: " . $bot->message()->chat->id . "\n";
        echo "thread: " . $bot->message()->message_thread_id . "\n";
        echo "text?: " . ($bot->message()->text ?? "NULL") . "\n";
    });

    $bot->run();
} catch (InvalidArgumentException $e) {
    exit('Wrong token provided');
} catch (\Psr\Container\NotFoundExceptionInterface $e) {
    exit('Not found exception inferface');
} catch (\Psr\Container\ContainerExceptionInterface $e) {
    exit('Container exception interface');
}