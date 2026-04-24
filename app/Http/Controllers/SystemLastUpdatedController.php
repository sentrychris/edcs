<?php

namespace App\Http\Controllers;

use App\Http\Resources\SystemResource;
use App\Models\System;
use App\Services\Edsm\EdsmSystemBodyService;
use App\Services\Edsm\EdsmSystemInformationService;
use App\Traits\HasQueryRelations;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class SystemLastUpdatedController extends Controller
{
    use HasQueryRelations;

    /**
     * Get the last updated system
     */
    #[OA\Get(
        path: '/systems/last-updated',
        summary: 'Get the most recently updated system',
        description: 'Returns the system with the latest updated_at timestamp, including its bodies and information. Useful for monitoring data freshness.',
        tags: ['Systems'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Most recently updated system',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/System'),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(
        EdsmSystemBodyService $bodies,
        EdsmSystemInformationService $information,
    ): SystemResource {
        // Map the allowed query parameters to the relations that can be loaded
        // for the system model e.g. withBodies will load bodies for the system
        $this->setQueryRelations([
            'withInformation' => 'information',
            'withBodies' => 'bodies',
            'withStations' => 'stations',
        ]);

        $system = Cache::remember('latest_system', 3600, fn () => System::latest('updated_at')->first());

        if ($system->body_count === null && ! $system->bodies()->exists()) {
            $bodies->updateSystemBodies($system);
        }

        if (! $system->information()->exists()) {
            $information->updateSystemInformation($system);
        }

        $this->loadQueryRelations(
            ['withBodies' => 1, 'withInformation' => 1],
            $system
        );

        return new SystemResource($system);
    }
}
