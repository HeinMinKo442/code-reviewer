<?php

namespace App\Jobs;

use App\Services\Github\AllowedRepositoryService;
use App\Services\Github\GitHubDiffParser;
use App\Services\Github\GitHubService;
use App\Services\Github\PullRequestReviewCommentMapper;
use App\Services\VertexAi\GeminiCodeReviewService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessGithubPullRequestJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $repository
     */
    public function __construct(
        public string $repositoryFullName,
        public int $pullRequestNumber,
        public array $repository,
    ) {}

    /**
     * Execute the queued pull request review workflow.
     */
    public function handle(
        AllowedRepositoryService $allowedRepositoryService,
        GitHubService $gitHubService,
        GitHubDiffParser $gitHubDiffParser,
        GeminiCodeReviewService $geminiCodeReviewService,
        PullRequestReviewCommentMapper $pullRequestReviewCommentMapper,
    ): void {
        if (! $allowedRepositoryService->isAllowed($this->repositoryFullName)) {
            Log::info('Skipped pull request review for non-allowed repository.', [
                'repository' => $this->repositoryFullName,
                'pull_request_number' => $this->pullRequestNumber,
            ]);

            return;
        }

        try {
            $rawDiff = $gitHubService->getPullRequestDiff($this->repositoryFullName, $this->pullRequestNumber);
            $formattedDiff = $gitHubDiffParser->formatForAiReview($rawDiff);

            if ($formattedDiff === '') {
                Log::info('No reviewable diff content found for pull request.', [
                    'repository' => $this->repositoryFullName,
                    'pull_request_number' => $this->pullRequestNumber,
                ]);

                return;
            }

            Log::info('Fetched pull request diff for AI review.', [
                'repository' => $this->repositoryFullName,
                'pull_request_number' => $this->pullRequestNumber,
                'diff_length' => strlen($formattedDiff),
            ]);

            $reviewComments = $geminiCodeReviewService->reviewDiff($formattedDiff);

            if ($reviewComments === []) {
                Log::info('No AI review comments generated for pull request.', [
                    'repository' => $this->repositoryFullName,
                    'pull_request_number' => $this->pullRequestNumber,
                ]);

                return;
            }

            $githubComments = $pullRequestReviewCommentMapper->mapForGithubSubmission($rawDiff, $reviewComments);

            if ($githubComments === []) {
                Log::warning('All AI review comments were discarded before GitHub submission.', [
                    'repository' => $this->repositoryFullName,
                    'pull_request_number' => $this->pullRequestNumber,
                    'generated_comment_count' => count($reviewComments),
                ]);

                return;
            }

            $gitHubService->postCommitComment(
                $this->repositoryFullName,
                $this->pullRequestNumber,
                $githubComments,
            );

            Log::info('Published AI pull request review to GitHub.', [
                'repository' => $this->repositoryFullName,
                'pull_request_number' => $this->pullRequestNumber,
                'generated_comment_count' => count($reviewComments),
                'submitted_comment_count' => count($githubComments),
            ]);
        } catch (RequestException $exception) {
            Log::error('GitHub API request failed while processing pull request review.', [
                'repository' => $this->repositoryFullName,
                'pull_request_number' => $this->pullRequestNumber,
                'status' => $exception->response?->status(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Unexpected failure while processing pull request review job.', [
                'repository' => $this->repositoryFullName,
                'pull_request_number' => $this->pullRequestNumber,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
