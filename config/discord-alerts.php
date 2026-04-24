<?php

use Spatie\DiscordAlerts\Jobs\SendToDiscordChannelJob;

return [
    /**
     * Custom config for EDDN
     */
    'eddn' => [
        'webhook' => env('DISCORD_ALERT_EDDN_LISTENER_WEBHOOK'),
        'embed' => [
            'title' => 'EDDN Listener Updates',
            'description' => 'EDDN listener restarts every 3 hours.',
        ],
    ],

    'edsm' => [
        'webhook' => env('DISCORD_ALERT_EDSM_API_WEBHOOK'),
        'embed' => [
            'title' => 'EDSM API Status',
            'description' => 'EDSM API response connectivity status and failures.',
        ],
    ],

    /*
     * The webhook URLs that we'll use to send a message to Discord.
     */
    'webhook_urls' => [
        'default' => env('DISCORD_ALERT_WEBHOOK'),
    ],

    /*
     * Default avatar is an empty string '' which means it will not be included in the payload.
     * You can add multiple custom avatars and then specify directly with withAvatar()
     */
    'avatar_urls' => [
        'default' => '',
    ],

    /*
     * This job will send the message to Discord. You can extend this
     * job to set timeouts, retries, etc...
     */
    'job' => SendToDiscordChannelJob::class,

    /*
     * The queue connection that should be used to send the alert.
     *
     * If not specified, we'll use the default queue connection.
     */
    'queue_connection' => env('DISCORD_ALERT_QUEUE_CONNECTION'),

    /*
     * The queue name that should be used to send the alert. Only supported for drivers
     * that allow multiple queues (e.g., redis, database, beanstalkd). Ignored for sync and null drivers.
     */
    'queue' => env('DISCORD_ALERT_QUEUE', 'default'),
];
