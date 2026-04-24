<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HasCommander
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->commander) {
            return $next($request);
        }

        return response()->json([
            'message' => 'You have no associated CMDR',
        ], JsonResponse::HTTP_BAD_REQUEST);
    }
}
