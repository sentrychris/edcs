<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchSystemBodyRequest;
use App\Http\Resources\SystemBodyResource;
use App\Models\SystemBody;
use App\Traits\HasQueryRelations;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class SystemBodyController extends Controller
{
    use HasQueryRelations;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setQueryRelations([
            'withSystem' => 'system',
            'withStations' => 'system.stations',
            'withBodies' => ['system.bodies' => fn ($query) => $query->select(
                'id',
                'system_id',
                'body_id',
                'name',
                'slug',
                'type',
                'sub_type',
                'distance_to_arrival',
            )],
        ]);
    }

    /**
     * Show system body.
     *
     * User can provide the following request parameters.
     *
     * withSystem: 0 or 1 - Return body with associated system.
     * withStations: 0 or 1 - Return body with associated stations and outposts.
     */
    #[OA\Get(
        path: '/bodies/{slug}',
        summary: 'Get a single celestial body by slug',
        description: 'Retrieves a star, planet, or moon by its slug ({body_id}-{name}). Pass withSystem to embed the parent system, withStations to also embed the system\'s stations.',
        tags: ['Bodies'],
        parameters: [
            new OA\Parameter(
                name: 'slug',
                in: 'path',
                required: true,
                description: 'Body slug in format {body_id}-{name}',
                schema: new OA\Schema(type: 'string', example: '108086401534265707-earth')
            ),
            new OA\Parameter(name: 'withSystem', in: 'query', required: false, description: 'Embed the parent system', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'withStations', in: 'query', required: false, description: 'Embed the parent system\'s stations', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'withBodies', in: 'query', required: false, description: 'Embed sibling bodies in the parent system (slim listing)', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Celestial body',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/SystemBody'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Body not found'),
        ]
    )]
    public function show(string $slug, SearchSystemBodyRequest $request): SystemBodyResource|Response
    {
        $body = SystemBody::whereSlug($slug)->first();

        if (! $body) {
            return response([], 404);
        }

        // Load related data for the system depending on query parameters passed.
        $body = $this->loadQueryRelations($request->validated(), $body);

        return new SystemBodyResource($body);
    }
}
