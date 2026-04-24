<?php

return [
    'eddn' => [
        // Status - https://eddn.edcd.io/
        'relay' => [
            'listener' => 'tcp://eddn.edcd.io:9500',
        ],

        // Valid schemas. Only messages conforming to the following schemas
        // will be processed.
        'schemas' => [
            'valid' => [
                'https://eddn.edcd.io/schemas/approachsettlement/1' => true,
                'https://eddn.edcd.io/schemas/blackmarket/1' => true,
                'https://eddn.edcd.io/schemas/codexentry/1' => true,
                'https://eddn.edcd.io/schemas/commodity/3' => true,
                'https://eddn.edcd.io/schemas/dockingdenied/1' => true,
                'https://eddn.edcd.io/schemas/dockinggranted/1' => true,
                'https://eddn.edcd.io/schemas/fcmaterials_capi/1' => true,
                'https://eddn.edcd.io/schemas/fcmaterials_journal/1' => true,
                'https://eddn.edcd.io/schemas/fssallbodiesfound/1' => true,
                'https://eddn.edcd.io/schemas/fssbodysignals/1' => true,
                'https://eddn.edcd.io/schemas/fssdiscoveryscan/1' => true,
                'https://eddn.edcd.io/schemas/fsssignaldiscovered/1' => true,
                'https://eddn.edcd.io/schemas/journal/1' => true,
                'https://eddn.edcd.io/schemas/navbeaconscan/1' => true,
                'https://eddn.edcd.io/schemas/navroute/1' => true,
                'https://eddn.edcd.io/schemas/outfitting/2' => true,
                'https://eddn.edcd.io/schemas/shipyard/2' => true,
            ],
        ],

        // Software control list to allow or block messages originating from
        // various third-party clients
        'software' => [
            'blacklist' => [
                'GameGlass' => '*', // I can't remember why I blacklisted this...
            ],
            'whitelist' => [
                'E:D Market Connector [Linux]' => '>5.*',
                'E:D Market Connector [Windows]' => '>5.*',
                'EDDI' => '>4.*',
                'EDDiscovery' => '>17.*',
                'EDDLite' => '>2.*',
                'EDO Materials Helper' => '*',
            ],
        ],
    ],

    // Galnet feeds. Please note the RSS feed has been retired in favor of the JSON feed.
    'galnet' => [
        'rss' => 'https://community.elitedangerous.com/galnet-rss',
        'json' => 'https://cms.zaonce.net/en-GB/jsonapi/node/galnet_article?sort=-published_at',
    ],

    // Inara
    'inara' => 'https://inara.cz',

    // EDSM API endpoints
    'edsm' => [

        'base_url' => 'https://www.edsm.net/',

        'systems' => [
            'system' => 'api-v1/system',
            'systems' => 'api-v1/systems',
            'sphere' => 'api-v1/sphere-systems',
            'cube' => 'api-v1/cube-systems',
        ],

        'system' => [
            'bodies' => 'api-system-v1/bodies',
            'scan-value' => 'api-system-v1/estimated-value',
            'factions' => 'api-system-v1/factions',
            'traffic' => 'api-system-v1/traffic',
            'deaths' => 'api-system-v1/deaths',
            'stations' => [
                'stations' => 'api-system-v1/stations',
                'market' => 'api-system-v1/stations/market',
                'shipyard' => 'api-system-v1/stations/shipyard',
                'outfitting' => 'api-system-v1/stations/outfitting',
            ],
        ],

        'journal' => [
            'store' => 'api-journal-v1',
            'discard' => 'api-journal-v1/disard',
        ],

        'commander' => [
            'flight-log' => 'api-logs-v1/get-logs',
            'last-position' => 'api-logs-v1/get-position',
            'ranks' => 'api-commander-v1/get-ranks',
            'credits' => 'api-commander-v1/get-credits',
            'materials' => 'api-commander-v1/get-materials',
        ],

        'status' => 'api-status-v1/elite-server',
    ],

    // Frontier auth configuration
    'frontier' => [
        'auth' => [
            'url' => env('FRONTIER_AUTH_URL'),
            'client_id' => env('FRONTIER_CLIENT_ID'),
            'client_key' => env('FRONTIER_CLIENT_KEY'),
            'scopes' => ['auth', 'capi'],
        ],
        'capi' => [
            'url' => env('FRONTIER_CAPI_URL'),
        ],
    ],
];
