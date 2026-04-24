<?php

namespace App\Services\Eddn;

use App\Facades\DiscordAlert;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZMQ;
use ZMQContext;
use ZMQSocketException;

class EddnListenerService
{
    /**
     *  Listen to the EDDN relay and process messages in batches of 100.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    public function listen(?callable $callback = null)
    {
        $context = new ZMQContext;
        $socket = $context->getSocket(ZMQ::SOCKET_SUB);
        $socket->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, ''); // Subscribe to all messages

        $messagesDefault = ['batch' => true, 'messages' => []];
        $messagesBatch = 100;
        $messagesBatchTime = 10;

        $messages = $messagesDefault;
        $lastTimeMessages = time();

        try {
            $relay = config('elite.eddn.relay.listener');
            $socket->connect($relay);

            $message = "EDDN listener is connected to {$relay}";
            Log::channel('eddn')->info($message);
            DiscordAlert::eddn(self::class, $message, true);

            while (true) {
                try {
                    $message = $socket->recv();

                    if ($message !== false) {
                        $decompressedMessage = zlib_decode($message);

                        if ($decompressedMessage === false) {
                            Log::channel('eddn')->error('Failed to decompress message');

                            continue;
                        }

                        $data = json_decode($decompressedMessage, true);

                        if ($data) {
                            $messages['messages'][] = $data;
                        }

                        // If we have more than 500 messages or 20 seconds have passed since the last messages were received, process the batch
                        if (count($messages['messages']) >= $messagesBatch || time() > ($lastTimeMessages + $messagesBatchTime)) {
                            // Process the batch of messages
                            if ($callback) {
                                $callback($messages);
                            }

                            // Reset messages and timer
                            $messages = $messagesDefault;
                            $lastTimeMessages = time();
                        }
                    } else {
                        // If no message received, sleep a bit to avoid tight loop
                        usleep(10000); // 10 ms
                    }
                } catch (ZMQSocketException $e) {
                    Log::channel('eddn')->error('ZMQSocketException: '.$e->getMessage());
                    throw new RuntimeException('ZMQSocketException: '.$e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $message = 'EDDN listener failed to connect: '.$e->getMessage();
            Log::channel('eddn')->error($message);
            DiscordAlert::eddn(self::class, $message, false);

            throw new RuntimeException('Failed to connect to EDDN relay.');
        }
    }
}
