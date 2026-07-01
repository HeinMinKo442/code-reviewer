<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGithubPullRequestJob;
use App\Services\Github\AllowedRepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GithubWebhookController extends Controller
{
    private const AI_REVIEW_LABEL = 'ai-review';

    private const PULL_REQUEST_EVENT = 'pull_request';

    public function __construct(
        private readonly AllowedRepositoryService $allowedRepositoryService,
    ) {}

    /**
     * Accept a GitHub pull_request webhook and queue AI review when the ai-review label is applied.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Log::info('Webhook received', ['action' => $request->input('action')]);

            if ($request->header('X-GitHub-Event') !== self::PULL_REQUEST_EVENT) {
                return response()->json(['status' => 'ignored_event'], Response::HTTP_OK);
            }

            $payload = $request->json()->all();
            $action = $payload['action'] ?? null;

            if (!in_array($action, ['opened', 'labeled', 'synchronize'])) {
                return response()->json(['status' => 'ignored_action', 'action' => $action], Response::HTTP_OK);
            }

            if (!$this->hasAiReviewLabel($payload)) {
                \Illuminate\Support\Facades\Log::info('Ignored because ai-review label is missing.', [
                    'current_labels' => $payload['pull_request']['labels'] ?? []
                ]);
                return response()->json(['status' => 'ignored_missing_label'], Response::HTTP_OK);
            }

            $repository = $payload['repository'] ?? null;
            $pullRequestNumber = $payload['number'] ?? $payload['pull_request']['number'] ?? null;
            $repositoryFullName = is_array($repository) ? ($repository['full_name'] ?? null) : null;

            if (! is_array($repository) || ! is_int($pullRequestNumber) || ! is_string($repositoryFullName)) {
                return response()->json(['message' => 'Invalid pull request payload.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (! $this->allowedRepositoryService->isAllowed($repositoryFullName)) {
                \Illuminate\Support\Facades\Log::warning('Ignored not allowed repo: ' . $repositoryFullName);
                return response()->json(['status' => 'ignored_not_allowed_repo', 'repo' => $repositoryFullName], Response::HTTP_OK);
            }

            \Illuminate\Support\Facades\Log::info('Dispatching AI Review Job to Queue', ['repo' => $repositoryFullName, 'pr' => $pullRequestNumber]);

            // ပြင်ဆင်လိုက်သည့်နေရာ: dispatchSync မှ dispatch သို့ ပြောင်းလဲခြင်း
            ProcessGithubPullRequestJob::dispatch(
                $repositoryFullName,
                $pullRequestNumber,
                $repository,
            );

            return response()->json(['status' => 'review_triggered'], Response::HTTP_ACCEPTED);
        } catch(\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Determine whether the pull request currently carries the ai-review label.
     *
     * @param  array<string, mixed>  $payload
     */
    private function hasAiReviewLabel(array $payload): bool
    {
        $labels = $payload['pull_request']['labels'] ?? [];

        if (! is_array($labels)) {
            return false;
        }

        foreach ($labels as $label) {
            if (is_array($label) && ($label['name'] ?? null) === self::AI_REVIEW_LABEL) {
                return true;
            }
        }

        return false;
    }
}
