<?php

namespace Katana\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates warm endpoint requests via Bearer token.
 *
 * Token is configured in katana.warm.token.
 * If no token is configured, all requests are denied.
 */
class KatanaAuthMiddleware
{
    /**
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, \Closure $next): Response
    {
        /** @var string $configuredToken */
        $configuredToken = config('katana.warm.token', '');

        if ($configuredToken === '') {
            return new \Illuminate\Http\JsonResponse(
                ['message' => 'Warm endpoint is not configured. Set katana.warm.token.'],
                403,
            );
        }

        $bearerToken = $request->bearerToken();

        if ($bearerToken === null || ! hash_equals($configuredToken, $bearerToken)) {
            return new \Illuminate\Http\JsonResponse(
                ['message' => 'Unauthorized.'],
                401,
            );
        }

        return $next($request);
    }
}
