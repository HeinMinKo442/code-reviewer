<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCloudTaskHandler
{
    /**
     * Verify that the internal queue handler is invoked by Cloud Tasks.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $handlerSecret = config('cloudtasks.handler_secret');

        if (! is_string($handlerSecret) || $handlerSecret === '') {
            return response()->json(['message' => 'Cloud Tasks handler secret is not configured.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($request->header('X-Cloud-Tasks-Handler-Secret') !== $handlerSecret) {
            return response()->json(['message' => 'Unauthorized queue handler request.'], Response::HTTP_FORBIDDEN);
        }

        if (! $request->hasHeader('X-CloudTasks-TaskName') && ! $request->hasHeader('X-Cloud-Tasks-TaskName')) {
            return response()->json(['message' => 'Missing Cloud Tasks request headers.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
