<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchCommodityRequest;
use App\Http\Resources\MarketCommodityListingResource;
use App\Models\System;
use App\Services\MarketSearchService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class MarketCommodityController extends Controller
{
    /**
     * Search for stations buying or selling a specific commodity.
     */
    #[OA\Get(
        path: '/stations/search/commodity',
        summary: 'Find the best stations to buy from / sell to for a commodity',
        description: 'Returns two ranked lists for the given commodity: stations selling it cheapest (best places to buy from) and stations paying the most for it (best places to sell to). Backed by a Redis inverted index updated live from EDDN. Optional location filtering constrains results to systems within the given distance of a reference system.',
        tags: ['Market Search'],
        parameters: [
            new OA\Parameter(name: 'commodity', in: 'query', required: true, description: 'Commodity internal name (e.g. "gold", "tritium")', schema: new OA\Schema(type: 'string', example: 'gold')),
            new OA\Parameter(name: 'near_system', in: 'query', required: false, description: 'System slug to use as a location filter origin', schema: new OA\Schema(type: 'string', example: '10477373803-sol')),
            new OA\Parameter(name: 'ly', in: 'query', required: false, description: 'Search radius in light years from near_system (default: 100, max: 5000)', schema: new OA\Schema(type: 'number', format: 'float', example: 100.0)),
            new OA\Parameter(name: 'min_stock', in: 'query', required: false, description: 'Minimum stock to include on the buy side', schema: new OA\Schema(type: 'integer', example: 0)),
            new OA\Parameter(name: 'min_demand', in: 'query', required: false, description: 'Minimum demand to include on the sell side', schema: new OA\Schema(type: 'integer', example: 0)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Maximum results per side (default: 20, max: 100)', schema: new OA\Schema(type: 'integer', example: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Best buy-from and sell-to listings for the commodity',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'commodity',
                                    properties: [
                                        new OA\Property(property: 'name', type: 'string', example: 'gold'),
                                        new OA\Property(property: 'display_name', type: 'string', example: 'Gold'),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'best_buy_from', type: 'array', items: new OA\Items(ref: '#/components/schemas/MarketCommodityListing')),
                                new OA\Property(property: 'best_sell_to', type: 'array', items: new OA\Items(ref: '#/components/schemas/MarketCommodityListing')),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(SearchCommodityRequest $request, MarketSearchService $service): JsonResponse
    {
        $commodity = $request->input('commodity');
        $limit = (int) $request->input('limit', 20);
        $minStock = (int) $request->input('min_stock', 0);
        $minDemand = (int) $request->input('min_demand', 0);

        $allowedSystemIds64 = null;
        if ($request->filled('near_system')) {
            $origin = System::whereSlug($request->input('near_system'))->firstOrFail();
            $ly = (float) $request->input('ly', 100);
            $allowedSystemIds64 = System::findNearest(
                ['x' => $origin->coords_x, 'y' => $origin->coords_y, 'z' => $origin->coords_z],
                $ly
            )->pluck('id64')->all();
        }

        $bestBuy = $service->lowestBuy($commodity, $allowedSystemIds64, $limit, $minStock);
        $bestSell = $service->highestSell($commodity, $allowedSystemIds64, $limit, $minDemand);

        return response()->json([
            'data' => [
                'commodity' => [
                    'name' => $commodity,
                    'display_name' => config("commodities.{$commodity}", $commodity),
                ],
                'best_buy_from' => MarketCommodityListingResource::collection($bestBuy),
                'best_sell_to' => MarketCommodityListingResource::collection($bestSell),
            ],
        ]);
    }
}
