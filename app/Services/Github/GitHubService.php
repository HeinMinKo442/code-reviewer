<?php

namespace App\Services\Github;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubService
{
    private const GITHUB_API_BASE = 'https://api.github.com';

    public function __construct(
        private readonly string $token,
    ) {}

    /**
     * Fetch the raw unified diff for a pull request.
     *
     * @throws RequestException
     */
    public function getPullRequestDiff(string $repositoryFullName, int $pullRequestNumber): string
    {
        $this->ensureTokenIsConfigured();

        $response = Http::withToken($this->token)
            ->withHeaders([
                'Accept' => 'application/vnd.github.v3.diff',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get($this->buildPullRequestUrl($repositoryFullName, $pullRequestNumber));

        $response->throw();

        return $response->body();
    }

    /**
     * Publish inline review comments on a pull request using the GitHub Reviews API.
     *
     * @param  array<int, array{path: string, line: int, side?: string, body: string}>  $comments
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function postCommitComment(string $repositoryFullName, int $pullRequestNumber, array $comments): array
    {
        $this->ensureTokenIsConfigured();

        $response = Http::withToken($this->token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post($this->buildPullRequestUrl($repositoryFullName, $pullRequestNumber).'/reviews', [
                'event' => 'COMMENT',
                'comments' => array_map(
                    fn (array $comment): array => [
                        'path' => $comment['path'],
                        'line' => $comment['line'],
                        'side' => $comment['side'] ?? 'RIGHT',
                        'body' => $comment['body'],
                    ],
                    $comments
                ),
            ]);

        $response->throw();

        return $response->json();
    }

    private function ensureTokenIsConfigured(): void
    {
        if ($this->token === '') {
            throw new RuntimeException('GitHub token is not configured.');
        }
    }

    private function buildPullRequestUrl(string $repositoryFullName, int $pullRequestNumber): string
    {
        return self::GITHUB_API_BASE.'/repos/'.$this->encodeRepositoryPath($repositoryFullName).'/pulls/'.$pullRequestNumber;
    }

    private function encodeRepositoryPath(string $repositoryFullName): string
    {
        $segments = explode('/', $repositoryFullName, 2);

        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            throw new RuntimeException('Invalid repository full name.');
        }

        return rawurlencode($segments[0]).'/'.rawurlencode($segments[1]);
    }
}
