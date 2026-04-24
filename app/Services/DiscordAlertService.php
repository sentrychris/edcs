<?php

namespace App\Services;

use Spatie\DiscordAlerts\Facades\DiscordAlert;

class DiscordAlertService
{
    private const SUCCESS = '#59e277';

    private const ERROR = '#e25959';

    /**
     * Send a message to the EDDN webhook.
     *
     * @param  $caller  -  the caller
     * @param  $message  - the message to send
     * @param  $success  - whether or not it's a success message
     */
    public function eddn(string $caller, string $message, bool $success)
    {
        $webhook = config('discord-alerts.eddn.webhook');
        if (! $webhook) {
            return false;
        }
        $this->send($webhook, $caller, $message, $success ? self::SUCCESS : self::ERROR);
    }

    /**
     * Send a message to the EDDN webhook.
     *
     * @param  $caller  -  the caller
     * @param  $message  - the message to send
     * @param  $success  - whether or not it's a success message
     */
    public function edsm(string $caller, string $message, bool $success)
    {
        $webhook = config('discord-alerts.edsm.webhook');
        if (! $webhook) {
            return false;
        }
        $this->send($webhook, $caller, $message, $success ? self::SUCCESS : self::ERROR);
    }

    /**
     * Send an embed.
     *
     * @param  string  $webhook  - the webhook URL
     * @param  string  $title  - the embed title
     * @param  string  $message  - the embed message
     * @param  string|null  $color  - the embed color
     */
    private function send(string $webhook, string $title, string $message, ?string $color = '#E77625'): void
    {
        DiscordAlert::to($webhook)->message('', $this->createEmbed($title, $message, $color));
    }

    /**
     * Create an embed for discord alerts.
     *
     * @param  string  $title  - the embed title
     * @param  string  $desc  - the embed description
     * @param  string  $color  - the embed color
     * @return array - the embed config
     */
    private function createEmbed(string $title, string $desc, ?string $color = '#E77625'): array
    {
        return [
            [
                'title' => $title,
                'description' => $desc,
                'color' => $color,
                'author' => [
                    'name' => 'EDCS',
                    'url' => 'https://edcs.app/',
                ],
            ],
        ];
    }
}
