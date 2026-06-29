<?php

namespace App\Services\CloudTasks;

use App\Services\VertexAi\VertexAiAccessTokenProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudTasksDispatcher
{
    public function __construct(
        private readonly VertexAiAccessTokenProvider $accessTokenProvider,
    ) {}

    /**
     * Enqueue a Laravel queue payload to Google Cloud Tasks as an HTTP task.
     *
     * @throws RequestException
     */
    public function dispatch(string $payload, string $queue, ?int $delaySeconds = null): void
    {
        $projectId = config('cloudtasks.project_id');
        $location = config('cloudtasks.location');
        $handlerUrl = $this->resolveHandlerUrl();
        $handlerSecret = config('cloudtasks.handler_secret');

        if (! is_string($projectId) || $projectId === '') {
            throw new RuntimeException('Google Cloud project ID is not configured for Cloud Tasks.');
        }

        if (! is_string($handlerUrl) || $handlerUrl === '') {
            throw new RuntimeException('Cloud Tasks handler URL is not configured.');
        }

        if (! is_string($handlerSecret) || $handlerSecret === '') {
            throw new RuntimeException('Cloud Tasks handler secret is not configured.');
        }

        $task = [
            'httpRequest' => [
                'httpMethod' => 'POST',
                'url' => $handlerUrl,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Cloud-Tasks-Handler-Secret' => $handlerSecret,
                ],
                'body' => base64_encode($payload),
            ],
        ];

        $oidcServiceAccount = config('cloudtasks.oidc_service_account');

        if (is_string($oidcServiceAccount) && $oidcServiceAccount !== '') {
            $task['httpRequest']['oidcToken'] = [
                'serviceAccountEmail' => $oidcServiceAccount,
                'audience' => $handlerUrl,
            ];
        }

        if ($delaySeconds !== null && $delaySeconds > 0) {
            $task['scheduleTime'] = now()->addSeconds($delaySeconds)->utc()->format('Y-m-d\TH:i:s\Z');
        }

        $endpoint = sprintf(
            'https://cloudtasks.googleapis.com/v2/projects/%s/locations/%s/queues/%s/tasks',
            $projectId,
            $location,
            $queue,
        );

        Http::withToken($this->accessTokenProvider->getAccessToken())
            ->timeout(30)
            ->post($endpoint, ['task' => $task])
            ->throw();
    }

    private function resolveHandlerUrl(): string
    {
        $handlerUrl = config('cloudtasks.handler_url');

        if (is_string($handlerUrl) && $handlerUrl !== '') {
            return $handlerUrl;
        }

        return rtrim((string) config('app.url'), '/').'/api/internal/queue/handle';
    }
}
