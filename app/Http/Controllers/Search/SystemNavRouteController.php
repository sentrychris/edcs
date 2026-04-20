<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchSystemRouteRequest;
use App\Http\Resources\SystemRouteResource;
use App\Models\System;
use App\Services\NavRouteFinderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class SystemNavRouteController extends Controller
{
    /**
     * Find the shortest route between two systems.
     * 
     * @param SearchSystemRouteRequest $request
     * @param NavRouteFinderService $service
     * @return AnonymousResourceCollection|Response
     */
    #[OA\Get(
        path: '/systems/search/route',
        summary: 'Find the shortest jump route between two systems',
        description: 'Computes the shortest route between two systems within the given jump range. Returns an ordered list of waypoints with per-hop and cumulative distances. Results are cached for 24 hours.',
        tags: ['System Search'],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: true, description: 'Origin system slug ({id64}-{name})', schema: new OA\Schema(type: 'string', example: '8216113749-maia')),
            new OA\Parameter(name: 'to', in: 'query', required: true, description: 'Destination system slug ({id64}-{name})', schema: new OA\Schema(type: 'string', example: '670685668665-pleiades-sector-ag-n-b7-0')),
            new OA\Parameter(name: 'ly', in: 'query', required: true, description: 'Maximum jump range in light years (min: 1, max: 500)', schema: new OA\Schema(type: 'number', format: 'float', example: 40.0)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ordered list of route waypoints',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SystemRouteWaypoint')),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'No route found within the given jump range'),
        ]
    )]
    public function __invoke(SearchSystemRouteRequest $request, NavRouteFinderService $service): AnonymousResourceCollection|Response
    {
        $from = System::whereSlug($request->input('from'))->firstOrFail();
        $to = System::whereSlug($request->input('to'))->firstOrFail();
        $ly = (float) $request->input('ly');

        $cacheKey = "system_route_{$from->slug}_{$to->slug}_{$ly}";
        $waypoints = Cache::get($cacheKey);

        if (! $waypoints) {
            $route = $service->findRoute($from, $to, $ly);

            if ($route === null) {
                return response(['message' => 'No route found within the given jump range.'], 404);
            }

            $totalDistance = 0.0;
            $waypoints = [];

            foreach ($route as $jump => $system) {
                $hopDistance = $jump === 0
                    ? 0.0
                    : $service->distance(
                        [
                            'x' => (float) $route[$jump - 1]->coords_x,
                            'y' => (float) $route[$jump - 1]->coords_y,
                            'z' => (float) $route[$jump - 1]->coords_z,
                        ],
                        [
                            'x' => (float) $system->coords_x,
                            'y' => (float) $system->coords_y,
                            'z' => (float) $system->coords_z,
                        ],
                    );

                $totalDistance += $hopDistance;

                $waypoints[] = [
                    'jump' => $jump,
                    'system' => $system,
                    'distance' => $hopDistance,
                    'total_distance' => $totalDistance,
                ];
            }

            Cache::set($cacheKey, $waypoints, 86400);
        }

        return SystemRouteResource::collection(collect($waypoints));
    }
}