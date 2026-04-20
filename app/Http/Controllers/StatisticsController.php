<?php

namespace App\Http\Controllers;

use App\Services\StatService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class StatisticsController extends Controller
{
    /**
     * Get statistics.
     *
     * @param Request $request
     * @param StatService $service
     * @return Response
     */
    #[OA\Get(
        path: '/statistics',
        summary: 'Get aggregate database statistics',
        description: 'Returns counts of systems, bodies, and stations. Results are cached and refreshed hourly by the scheduler.',
        tags: ['Statistics'],
        parameters: [
            new OA\Parameter(
                name: 'flushCache',
                in: 'query',
                required: false,
                description: 'Pass 1 to flush the cache and force a fresh calculation',
                schema: new OA\Schema(type: 'integer', enum: [0, 1], example: 1)
            ),
            new OA\Parameter(
                name: 'ttl',
                in: 'query',
                required: false,
                description: 'Cache lifetime in seconds (default: 3600)',
                schema: new OA\Schema(type: 'integer', example: 3600)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistics',
                content: new OA\JsonContent(ref: '#/components/schemas/Statistics')
            ),
        ]
    )]
    public function __invoke(Request $request, StatService $service): Response
    {
        return response([
            'data' => $service->fetch('statistics', $request->all()),
        ]);
    }
}
