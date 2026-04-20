<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchSystemByInformationRequest;
use App\Models\System;
use App\Http\Resources\SystemResource;
use App\Traits\HasQueryRelations;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class SystemInformationController extends Controller
{
    use HasQueryRelations;

    /**
     * Search for systems by information.
     *
     * @param SearchSystemByInformationRequest $request
     * @return AnonymousResourceCollection
     */
    #[OA\Get(
        path: '/systems/search/information',
        summary: 'Search systems by political and demographic attributes',
        description: 'Filters systems by population (minimum), security, government, allegiance, and economy. All text filters are partial-match.',
        tags: ['System Search'],
        parameters: [
            new OA\Parameter(name: 'population', in: 'query', required: false, description: 'Minimum population', schema: new OA\Schema(type: 'integer', example: 5000000000)),
            new OA\Parameter(name: 'security', in: 'query', required: false, description: 'Security level (partial match)', schema: new OA\Schema(type: 'string', example: 'high')),
            new OA\Parameter(name: 'government', in: 'query', required: false, description: 'Government type (partial match)', schema: new OA\Schema(type: 'string', example: 'Democracy')),
            new OA\Parameter(name: 'allegiance', in: 'query', required: false, description: 'Allegiance (partial match)', schema: new OA\Schema(type: 'string', example: 'Federation')),
            new OA\Parameter(name: 'economy', in: 'query', required: false, description: 'Economy type (partial match)', schema: new OA\Schema(type: 'string', example: 'Industrial')),
            new OA\Parameter(name: 'withInformation', in: 'query', required: false, description: 'Embed political/demographic information', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'withBodies', in: 'query', required: false, description: 'Embed celestial bodies', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'withStations', in: 'query', required: false, description: 'Embed stations and outposts', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of matching systems',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/System')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(SearchSystemByInformationRequest $request): AnonymousResourceCollection
    {
        // Map the allowed query parameters to the relations that can be loaded
        // for the system model e.g. withBodies will load bodies for the system
        $this->setQueryRelations([
            'withInformation' => 'information',
            'withBodies'      => 'bodies',
            'withStations'    => 'stations',
        ]);

        $validated = $request->validated();
        $infoFilters = array_intersect_key($validated, array_flip(['population', 'allegiance', 'government', 'economy', 'security']));

        $systems = System::query()
            ->when(! empty($infoFilters), fn ($query) => $query
                ->whereHas('information', function ($q) use ($infoFilters) {
                    $q
                        ->when(isset($infoFilters['population']),
                            fn ($q) => $q->where('population', '>=', $infoFilters['population'])
                        )
                        ->when(isset($infoFilters['allegiance']),
                            fn ($q) => $q->where('allegiance', 'LIKE', $infoFilters['allegiance'].'%')
                        )
                        ->when(isset($infoFilters['government']),
                            fn ($q) => $q->where('government', 'LIKE', $infoFilters['government'].'%')
                        )
                        ->when(isset($infoFilters['economy']),
                            fn ($q) => $q->where('economy', 'LIKE', $infoFilters['economy'].'%')
                        )
                        ->when(isset($infoFilters['security']),
                            fn ($q) => $q->where('security', 'LIKE', $infoFilters['security'].'%')
                        );
                })
            )
            ->simplePaginate();

        $this->loadQueryRelations(
            $request->only(['withInformation', 'withBodies', 'withStations']),
            $systems
        );

        return SystemResource::collection($systems);
    }
}