<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Station',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Daedalus'),
        new OA\Property(property: 'type', type: 'string', example: 'Orbis Starport'),
        new OA\Property(property: 'body', type: 'string', nullable: true, example: 'Sol'),
        new OA\Property(property: 'distance_to_arrival', type: 'number', format: 'float', example: 508.0),
        new OA\Property(property: 'controlling_faction', type: 'string', nullable: true, example: 'Sol Constitution Party'),
        new OA\Property(property: 'allegiance', type: 'string', nullable: true, example: 'Federation'),
        new OA\Property(property: 'government', type: 'string', nullable: true, example: 'Democracy'),
        new OA\Property(property: 'economy', type: 'string', nullable: true, example: 'Industrial'),
        new OA\Property(property: 'second_economy', type: 'string', nullable: true, example: 'Refinery'),
        new OA\Property(property: 'has_market', type: 'boolean', example: true),
        new OA\Property(property: 'has_shipyard', type: 'boolean', example: true),
        new OA\Property(property: 'has_outfitting', type: 'boolean', example: true),
        new OA\Property(
            property: 'other_services',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['Restock', 'Repair', 'Contacts']
        ),
        new OA\Property(
            property: 'last_updated',
            properties: [
                new OA\Property(property: 'information', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'market', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'shipyard', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'outfitting', type: 'string', format: 'date-time', nullable: true),
            ],
            type: 'object'
        ),
        new OA\Property(property: 'slug', type: 'string', example: '128016384-daedalus'),
        new OA\Property(property: 'system', ref: '#/components/schemas/System', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'MarketCommodity',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Gold'),
        new OA\Property(property: 'buy_price', type: 'integer', example: 0),
        new OA\Property(property: 'sell_price', type: 'integer', example: 47238),
        new OA\Property(property: 'mean_price', type: 'integer', example: 47201),
        new OA\Property(property: 'demand', type: 'integer', example: 14320),
        new OA\Property(property: 'stock', type: 'integer', example: 0),
    ]
)]
#[OA\Schema(
    schema: 'MarketData',
    properties: [
        new OA\Property(property: 'station', type: 'string', example: 'Daedalus'),
        new OA\Property(property: 'system', type: 'string', example: 'Sol'),
        new OA\Property(property: 'last_updated', type: 'string', format: 'date-time'),
        new OA\Property(
            property: 'prohibited',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['Narcotics', 'Slaves']
        ),
        new OA\Property(
            property: 'commodities',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(ref: '#/components/schemas/MarketCommodity')
        ),
    ]
)]
#[OA\Schema(
    schema: 'MarketCommodityListing',
    properties: [
        new OA\Property(
            property: 'station',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'Daedalus'),
                new OA\Property(property: 'type', type: 'string', example: 'Orbis Starport'),
                new OA\Property(property: 'slug', type: 'string', example: '128016384-daedalus'),
                new OA\Property(property: 'distance_to_arrival', type: 'number', format: 'float', example: 508.0),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'system',
            properties: [
                new OA\Property(property: 'id64', type: 'integer', example: 10477373803),
                new OA\Property(property: 'name', type: 'string', example: 'Sol'),
                new OA\Property(property: 'slug', type: 'string', example: '10477373803-sol'),
                new OA\Property(
                    property: 'coords',
                    properties: [
                        new OA\Property(property: 'x', type: 'number', format: 'float'),
                        new OA\Property(property: 'y', type: 'number', format: 'float'),
                        new OA\Property(property: 'z', type: 'number', format: 'float'),
                    ],
                    type: 'object'
                ),
            ],
            type: 'object'
        ),
        new OA\Property(property: 'buy_price', type: 'integer', example: 44000),
        new OA\Property(property: 'sell_price', type: 'integer', example: 47238),
        new OA\Property(property: 'mean_price', type: 'integer', example: 47201),
        new OA\Property(property: 'stock', type: 'integer', example: 1200),
        new OA\Property(property: 'demand', type: 'integer', example: 14320),
        new OA\Property(property: 'last_updated', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'MarketTradeRoute',
    properties: [
        new OA\Property(
            property: 'commodity',
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'gold'),
                new OA\Property(property: 'display_name', type: 'string', example: 'Gold'),
            ],
            type: 'object'
        ),
        new OA\Property(property: 'profit_per_unit', type: 'integer', example: 7821),
        new OA\Property(property: 'buy_from', ref: '#/components/schemas/MarketCommodityListing'),
        new OA\Property(property: 'sell_to', ref: '#/components/schemas/MarketCommodityListing'),
    ]
)]
class StationSchema {}
