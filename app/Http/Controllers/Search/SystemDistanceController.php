<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchSystemByDistanceRequest;
use App\Http\Resources\SystemDistanceResource;
use App\Models\System;
use OpenApi\Attributes as OA;

class SystemDistanceController extends Controller
{
    
    /**
     * Find systems by distance in light years.
     * 
     * @param SearchSystemByDistanceRequest $request
     */
    #[OA\Get(
        path: '/systems/search/distance',
        summary: 'Find systems within a given distance of a position',
        description: 'Returns a paginated list of systems within the specified number of light years from a position, sorted by distance. The position can be specified either by a system slug or by raw galactic (x, y, z) coordinates — slug takes precedence if both are provided.',
        tags: ['System Search'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'query', required: false, description: 'System slug ({id64}-{name}) to use as the search origin', schema: new OA\Schema(type: 'string', example: '10477373803-sol')),
            new OA\Parameter(name: 'x', in: 'query', required: false, description: 'Galactic X coordinate (required when slug is not provided)', schema: new OA\Schema(type: 'number', format: 'float', example: 0.0)),
            new OA\Parameter(name: 'y', in: 'query', required: false, description: 'Galactic Y coordinate (required when slug is not provided)', schema: new OA\Schema(type: 'number', format: 'float', example: 0.0)),
            new OA\Parameter(name: 'z', in: 'query', required: false, description: 'Galactic Z coordinate (required when slug is not provided)', schema: new OA\Schema(type: 'number', format: 'float', example: 0.0)),
            new OA\Parameter(name: 'ly', in: 'query', required: false, description: 'Search radius in light years (default: 100, max: 5000)', schema: new OA\Schema(type: 'number', format: 'float', example: 100.0)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Maximum results per page (max: 1000)', schema: new OA\Schema(type: 'integer', example: 20)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Systems within the given distance, each with a calculated distance field',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SystemDistance')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(SearchSystemByDistanceRequest $request)
    {
        if ($request->has('slug')) {
            $origin = System::whereSlug($request->input('slug'))->firstOrFail();
            $coords = [
                'x' => $origin->coords_x,
                'y' => $origin->coords_y,
                'z' => $origin->coords_z,
            ];
        } else {
            $coords = $request->only(['x', 'y', 'z']);
        }

        $limit = $request->input('limit', config('app.pagination.limit'));

        $systems = System::findNearest($coords, $request->input('ly', 100))
            ->with('information')
            ->simplePaginate($limit)
            ->appends($request->all());

        return SystemDistanceResource::collection($systems);
    }
}