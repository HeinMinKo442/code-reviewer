<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGithubWebhookSignature
{
    /**
     * Verify that the incoming request was signed by GitHub using the shared webhook secret.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.github.webhook_secret');

        if (empty($secret)) {
            return response()->json(['message' => 'Webhook secret is not configured.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $signatureHeader = $request->header('X-Hub-Signature-256');

        if (! is_string($signatureHeader) || $signatureHeader === '') {
            return response()->json(['message' => 'Missing webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signatureHeader)) {
            return response()->json(['message' => 'Invalid webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
