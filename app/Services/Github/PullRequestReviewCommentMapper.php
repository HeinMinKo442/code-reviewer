<?php

namespace App\Services\Github;

use Illuminate\Support\Facades\Log;

class PullRequestReviewCommentMapper
{
    public function __construct(
        private readonly GitHubDiffParser $gitHubDiffParser,
    ) {}

    /**
     * Map Gemini review comments into GitHub pull request review payloads.
     *
     * @param  array<int, array{path: string, line: int, side: string, comment: string}>  $geminiComments
     * @return array<int, array{path: string, line: int, side: string, body: string}>
     */
    public function mapForGithubSubmission(string $rawDiff, array $geminiComments): array
    {
        $reviewableLineMap = $this->gitHubDiffParser->extractReviewableLineMap($rawDiff);
        $githubComments = [];
        $seenPositions = [];

        foreach ($geminiComments as $index => $geminiComment) {
            $path = $this->normalizePath($geminiComment['path']);
            $line = $geminiComment['line'];
            $comment = trim($geminiComment['comment']);

            if ($comment === '') {
                $this->logDiscardedComment($index, $path, $line, 'empty_comment');

                continue;
            }

            $resolvedPath = $this->resolvePath($reviewableLineMap, $path);

            if ($resolvedPath === null) {
                $this->logDiscardedComment($index, $path, $line, 'invalid_path');

                continue;
            }

            if (! $this->isLineReviewable($reviewableLineMap, $resolvedPath, $line)) {
                $this->logDiscardedComment($index, $resolvedPath, $line, 'invalid_line_number');

                continue;
            }

            $positionKey = $resolvedPath.':'.$line;

            if (isset($seenPositions[$positionKey])) {
                $this->logDiscardedComment($index, $path, $line, 'duplicate_position');

                continue;
            }

            $seenPositions[$positionKey] = true;
            $githubComments[] = [
                'path' => $resolvedPath,
                'line' => $line,
                'side' => 'RIGHT',
                'body' => $comment,
            ];
        }

        return $githubComments;
    }

    /**
     * @param  array<string, array<int, bool>>  $reviewableLineMap
     */
    private function isLineReviewable(array $reviewableLineMap, string $path, int $line): bool
    {
        if (! isset($reviewableLineMap[$path][$line])) {
            return false;
        }

        return $reviewableLineMap[$path][$line] === true;
    }

    /**
     * @param  array<string, array<int, bool>>  $reviewableLineMap
     */
    private function resolvePath(array $reviewableLineMap, string $path): ?string
    {
        if (isset($reviewableLineMap[$path])) {
            return $path;
        }

        $matches = [];

        foreach (array_keys($reviewableLineMap) as $candidatePath) {
            if ($candidatePath === $path || str_ends_with($candidatePath, '/'.$path)) {
                $matches[] = $candidatePath;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private function logDiscardedComment(int $index, string $path, int $line, string $reason): void
    {
        Log::warning('Discarded AI review comment before GitHub submission.', [
            'comment_index' => $index,
            'path' => $path,
            'line' => $line,
            'reason' => $reason,
        ]);
    }
}
