<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookmarkRequest;
use App\Http\Resources\SystemResource;
use App\Models\System;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class BookmarkController extends Controller
{
    /**
     * List the authenticated user's bookmarked systems.
     */
    #[OA\Get(
        path: '/bookmarks',
        summary: 'List bookmarked systems',
        description: 'Returns a paginated list of systems bookmarked by the authenticated user, most recent first.',
        security: [['sanctum' => []]],
        tags: ['Bookmarks'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of bookmarked systems',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/System')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = (int) $request->input('limit', config('app.pagination.limit'));

        $systems = $request->user()
            ->bookmarkedSystems()
            ->orderByPivot('created_at', 'desc')
            ->simplePaginate($limit)
            ->appends($request->all());

        return SystemResource::collection($systems);
    }

    /**
     * Bookmark a system for the authenticated user.
     */
    #[OA\Post(
        path: '/bookmarks',
        summary: 'Bookmark a system',
        description: 'Adds the given system to the authenticated user\'s bookmarks. Idempotent — bookmarking the same system twice is a no-op.',
        security: [['sanctum' => []]],
        tags: ['Bookmarks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['slug'],
                properties: [
                    new OA\Property(property: 'slug', type: 'string', example: '10477373803-sol'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Bookmark created (or already existed)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/System'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreBookmarkRequest $request): JsonResponse
    {
        $system = System::whereSlug($request->validated('slug'))->firstOrFail();

        $request->user()->bookmarkedSystems()->syncWithoutDetaching([$system->id]);

        return (new SystemResource($system))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a system from the authenticated user's bookmarks.
     */
    #[OA\Delete(
        path: '/bookmarks/{slug}',
        summary: 'Remove a bookmark',
        description: 'Removes the given system from the authenticated user\'s bookmarks. Idempotent — removing a bookmark that does not exist still returns 204.',
        security: [['sanctum' => []]],
        tags: ['Bookmarks'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: '10477373803-sol')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Bookmark removed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'System not found'),
        ]
    )]
    public function destroy(Request $request, string $slug): Response
    {
        $system = System::whereSlug($slug)->firstOrFail();

        $request->user()->bookmarkedSystems()->detach($system->id);

        return response()->noContent();
    }
}
