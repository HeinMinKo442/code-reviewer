<?php

namespace App\Services\VertexAi;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class GeminiCodeReviewService
{
    /**
     * Send a formatted pull request diff to Gemini and return structured review comments.
     *
     * @return array<int, array{path: string, line: int, side: string, comment: string}>
     *
     * @throws RequestException
     */
    public function reviewDiff(string $formattedDiff): array
    {
        $projectId = config('vertex.project_id');
        $location = config('vertex.location');
        $model = config('vertex.model');

        if (! is_string($projectId) || $projectId === '') {
            throw new RuntimeException('Google Cloud project ID is not configured.');
        }

        if (! is_string($location) || $location === '' || ! is_string($model) || $model === '') {
            throw new RuntimeException('Vertex AI model configuration is invalid.');
        }

        $accessToken = app(VertexAiAccessTokenProvider::class)->getAccessToken();
        $endpoint = sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent',
            $location,
            $projectId,
            $location,
            $model,
        );

        \Illuminate\Support\Facades\Log::error('DEBUG_URL_CONSTRUCTION', [
            'location' => $location,
            'projectId' => $projectId,
            'model' => $model,
            'full_endpoint' => $endpoint
        ]);

        $response = Http::withToken($accessToken)
            ->timeout(120)
            ->post($endpoint, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => CodeReviewSystemPrompt::build()],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => "Review the following pull request diff and return only the JSON array schema response.\n\n".$formattedDiff,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 8192,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => CodeReviewResponseSchema::definition(),
                ],
            ]);

        $response->throw();

        return $this->parseReviewComments($response->json());
    }

    /**
     * @param  array<string, mixed>|null  $responseBody
     * @return array<int, array{path: string, line: int, side: string, comment: string}>
     */
    private function parseReviewComments(?array $responseBody): array
    {
        $rawJson = data_get($responseBody, 'candidates.0.content.parts.0.text');

        if (! is_string($rawJson) || trim($rawJson) === '') {
            throw new RuntimeException('Vertex AI returned an empty review response.');
        }

        try {
            $decoded = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Vertex AI returned invalid JSON for review comments.', 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Vertex AI review response must be a JSON array.');
        }

        $comments = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = $this->normalizeReviewComment($item);

            if ($normalized !== null) {
                $comments[] = $normalized;
            }
        }

        return $comments;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{path: string, line: int, side: string, comment: string}|null
     */
    private function normalizeReviewComment(array $item): ?array
    {
        $path = $item['path'] ?? null;
        $line = $item['line'] ?? null;
        $comment = $item['comment'] ?? null;

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (! is_int($line) || $line < 1) {
            if (is_numeric($line)) {
                $line = (int) $line;
            }

            if (! is_int($line) || $line < 1) {
                return null;
            }
        }

        if (! is_string($comment) || trim($comment) === '') {
            return null;
        }

        return [
            'path' => $path,
            'line' => $line,
            'side' => 'RIGHT',
            'comment' => trim($comment),
        ];
    }
}
