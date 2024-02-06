<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.local.php';
require_once __DIR__ . '/App/functions.php';

use App\Conversation\Anonymous;
use App\Conversation\Manage;
use App\Conversation\Start;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;

/**
 * @return void
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws SergiX44\Nutgram\Telegram\Exceptions\TelegramException
 */
function run(): void
{
    $config = new Configuration(enableHttp2: false);
    $bot = new Nutgram(TOKEN, $config);
    $bot->setRunningMode(Polling::class);
    Conversation::refreshOnDeserialize();

    // restricted to private chat only
    $private = function (Nutgram $bot, $next) {
        if ($bot->userId() != $bot->chatId()) {
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

    $bot->run();
}

$unknown = 0;
$attempt = 0;
while (true) {
    try {
        run();
    } catch (GuzzleHttp\Exception\ConnectException $connectException) {
        output("looks like telegram api isn't responding for a while: " . $connectException->getMessage());
    } catch (GuzzleHttp\Exception\ClientException $clientException) {
        output("found guzzle client exception: " . $clientException->getMessage());
    } catch (SergiX44\Nutgram\Telegram\Exceptions\TelegramException $telegramException) {
        output("found telegram exception: " . $telegramException->getMessage());
    } catch (ContainerExceptionInterface $e) {
        output("found container exception: " . $e->getMessage());
    } catch (InvalidArgumentException $e) {
        exit('Wrong token provided');
    } catch (\Exception $exception) {
        output(
            "found unknown exception\n" .
            "message: {$exception->getMessage()}\n" .
            "file: {$exception->getFile()}:{$exception->getLine()}"
        );
    } finally {
        output("restarting bot (attempt $attempt)" . ($unknown > 0 ? " (unknown $unknown)" : ""));
        $attempt++;
    }
}