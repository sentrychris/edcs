<?php

namespace App\Http\Controllers;

use App\Services\Frontier\FrontierCApiService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FrontierCApiController extends Controller
{
    /**
     * The Frontier API manager.
     *
     * @var FrontierCApiService
     */
    private $frontierCApiService;

    /**
     * Create a new controller instance.
     *
     * @param  FrontierCApiService  $frontierCApiService  - the frontier auth service
     */
    public function __construct(FrontierCApiService $frontierCApiService)
    {
        $this->frontierCApiService = $frontierCApiService;
    }

    /**
     * Get commander profile.
     */
    #[OA\Get(
        path: '/frontier/capi/profile',
        summary: 'Get the authenticated commander profile from Frontier CAPI',
        description: 'Fetches the live commander profile from the Frontier Companion API and confirms/updates the stored commander record.',
        security: [['sanctum' => []]],
        tags: ['Frontier CAPI'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Commander profile',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object', description: 'Commander profile from Frontier CAPI'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function profile(Request $request)
    {
        $user = $request->user();
        $profile = $this->frontierCApiService->confirmCommander($user);

        return response()->json([
            'data' => $profile,
        ]);
    }

    /**
     * Get all journal entries.
     */
    #[OA\Get(
        path: '/frontier/capi/journal',
        summary: 'Get the commander journal for a given date',
        security: [['sanctum' => []]],
        tags: ['Frontier CAPI'],
        parameters: [
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Journal year', schema: new OA\Schema(type: 'integer', example: 2024)),
            new OA\Parameter(name: 'month', in: 'query', required: false, description: 'Journal month (1–12)', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'day', in: 'query', required: false, description: 'Journal day', schema: new OA\Schema(type: 'integer', example: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Journal entries',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function journal(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $day = $request->input('day');

        $user = $request->user();
        $journal = $this->frontierCApiService->getJournal($user, $year, $month, $day);

        dd($journal);

        return response()->json([
            'data' => $journal,
        ]);
    }
}
