<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchTradeRouteRequest;
use App\Http\Resources\MarketTradeRouteResource;
use App\Models\System;
use App\Services\MarketSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class MarketTradeRouteController extends Controller
{
    /**
     * Suggest profitable trade routes from the live commodity index.
     */
    #[OA\Get(
        path: '/stations/search/trade-route',
        summary: 'Find profitable commodity trade routes',
        description: 'For every commodity that has at least one buy and one sell station in the live index, pairs the cheapest buy station with the highest-paying sell station and returns the best routes by profit per unit. Optionally constrains both ends to systems within a given distance of a reference system. Results are cached for 5 minutes.',
        tags: ['Market Search'],
        parameters: [
            new OA\Parameter(name: 'near_system', in: 'query', required: false, description: 'System slug to use as a location filter origin. When provided, both the buy and sell stations must be within `ly` of this system.', schema: new OA\Schema(type: 'string', example: '10477373803-sol')),
            new OA\Parameter(name: 'ly', in: 'query', required: false, description: 'Search radius in light years from near_system (default: 100, max: 5000)', schema: new OA\Schema(type: 'number', format: 'float', example: 100.0)),
            new OA\Parameter(name: 'min_stock', in: 'query', required: false, description: 'Minimum stock at the buy station (default: 1)', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'min_demand', in: 'query', required: false, description: 'Minimum demand at the sell station (default: 1)', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'min_profit', in: 'query', required: false, description: 'Minimum profit per unit (default: 1000)', schema: new OA\Schema(type: 'integer', example: 1000)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Maximum routes to return, sorted by profit (default: 20, max: 100)', schema: new OA\Schema(type: 'integer', example: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Trade routes ordered by profit per unit',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/MarketTradeRoute')),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(SearchTradeRouteRequest $request, MarketSearchService $service): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);
        $minStock = (int) $request->input('min_stock', 1);
        $minDemand = (int) $request->input('min_demand', 1);
        $minProfit = (int) $request->input('min_profit', 1000);

        $allowedSystemIds64 = null;
        $nearSystemSlug = null;
        if ($request->filled('near_system')) {
            $nearSystemSlug = $request->input('near_system');
            $origin = System::whereSlug($nearSystemSlug)->firstOrFail();
            $ly = (float) $request->input('ly', 100);
            $allowedSystemIds64 = System::id64sWithinDistance(
                ['x' => $origin->coords_x, 'y' => $origin->coords_y, 'z' => $origin->coords_z],
                $ly
            );
        }

        $cacheKey = sprintf(
            'trade_routes_%s_%d_%d_%d_%d',
            $nearSystemSlug ?? 'global',
            (int) $request->input('ly', 100),
            $minStock,
            $minDemand,
            $minProfit,
        );

        $routes = Cache::remember($cacheKey, 300, function () use ($service, $allowedSystemIds64, $minStock, $minDemand, $minProfit) {
            return $this->buildRoutes($service, $allowedSystemIds64, $minStock, $minDemand, $minProfit);
        });

        return response()->json([
            'data' => MarketTradeRouteResource::collection(
                collect($routes)->take($limit)
            ),
        ]);
    }

    /**
     * Build the route list across every indexed commodity.
     *
     * @param  array<int, int>|null  $allowedSystemIds64
     * @return array<int, array<string, mixed>>
     */
    private function buildRoutes(MarketSearchService $service, ?array $allowedSystemIds64, int $minStock, int $minDemand, int $minProfit): array
    {
        $routes = [];

        foreach ($service->indexedCommodities() as $commodity) {
            $buys = $service->lowestBuy($commodity, $allowedSystemIds64, 3, $minStock);
            if ($buys->isEmpty()) {
                continue;
            }

            $sells = $service->highestSell($commodity, $allowedSystemIds64, 3, $minDemand);
            if ($sells->isEmpty()) {
                continue;
            }

            $pair = $this->bestPair($buys->all(), $sells->all());
            if ($pair === null) {
                continue;
            }

            [$buy, $sell] = $pair;
            $profit = (int) ($sell['commodity']['sellPrice'] ?? 0) - (int) ($buy['commodity']['buyPrice'] ?? 0);

            if ($profit < $minProfit) {
                continue;
            }

            $routes[] = [
                'commodity_name' => $commodity,
                'buy' => $buy,
                'sell' => $sell,
                'profit' => $profit,
            ];
        }

        usort($routes, fn ($a, $b) => $b['profit'] <=> $a['profit']);

        return $routes;
    }

    /**
     * Pick the highest-profit (buy, sell) pair where the two stations differ.
     *
     * @param  array<int, array<string, mixed>>  $buys  - sorted ascending by buyPrice
     * @param  array<int, array<string, mixed>>  $sells  - sorted descending by sellPrice
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    private function bestPair(array $buys, array $sells): ?array
    {
        foreach ($buys as $buy) {
            foreach ($sells as $sell) {
                if ($buy['station']->id !== $sell['station']->id) {
                    return [$buy, $sell];
                }
            }
        }

        return null;
    }
}
