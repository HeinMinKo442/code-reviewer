<?php

namespace App\Services\Github;

class GitHubDiffParser
{
    /**
     * Build a compact diff string containing modified and added blocks for AI review.
     */
    public function formatForAiReview(string $rawDiff): string
    {
        if (trim($rawDiff) === '') {
            return '';
        }

        $sections = [];

        foreach ($this->parseFileDiffs($rawDiff) as $fileDiff) {
            $fileSection = $this->formatFileDiff($fileDiff);

            if ($fileSection !== '') {
                $sections[] = $fileSection;
            }
        }

        return implode("\n\n", $sections);
    }

    /**
     * @return array<int, array{path: string, hunks: array<int, array{header: string, lines: array<int, array{type: string, content: string, old_line: int|null, new_line: int|null}>}>}>
     */
    public function parseFileDiffs(string $rawDiff): array
    {
        $fileDiffs = [];
        $chunks = preg_split('/^diff --git /m', $rawDiff) ?: [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            $fileDiff = $this->parseSingleFileDiff('diff --git '.$chunk);

            if ($fileDiff !== null) {
                $fileDiffs[] = $fileDiff;
            }
        }

        return $fileDiffs;
    }

    /**
     * Build a lookup map of RIGHT-side line numbers that exist in the pull request diff.
     *
     * @return array<string, array<int, bool>>
     */
    public function extractReviewableLineMap(string $rawDiff): array
    {
        $lineMap = [];

        foreach ($this->parseFileDiffs($rawDiff) as $fileDiff) {
            $path = $this->normalizePath($fileDiff['path']);

            foreach ($fileDiff['hunks'] as $hunk) {
                foreach ($hunk['lines'] as $line) {
                    if ($line['new_line'] === null) {
                        continue;
                    }

                    if (! in_array($line['type'], ['addition', 'context'], true)) {
                        continue;
                    }

                    if (! isset($lineMap[$path])) {
                        $lineMap[$path] = [];
                    }

                    $lineMap[$path][$line['new_line']] = true;
                }
            }
        }

        return $lineMap;
    }

    private function normalizePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    /**
     * @param  array{path: string, hunks: array<int, array{header: string, lines: array<int, array{type: string, content: string, old_line: int|null, new_line: int|null}>}>}  $fileDiff
     */
    private function formatFileDiff(array $fileDiff): string
    {
        $blocks = [];

        foreach ($fileDiff['hunks'] as $hunk) {
            $meaningfulLines = array_values(array_filter(
                $hunk['lines'],
                fn (array $line): bool => $line['type'] !== 'context' || $this->neighborIsChanged($hunk['lines'], $line)
            ));

            if ($meaningfulLines === []) {
                continue;
            }

            $blockLines = ['@@ '.$hunk['header']];

            foreach ($meaningfulLines as $line) {
                $prefix = match ($line['type']) {
                    'addition' => '+',
                    'deletion' => '-',
                    default => ' ',
                };

                $lineNumber = $line['new_line'] ?? $line['old_line'];
                $lineLabel = $lineNumber !== null ? "L{$lineNumber}" : 'L?';

                $blockLines[] = "[{$lineLabel}] {$prefix}{$line['content']}";
            }

            $blocks[] = implode("\n", $blockLines);
        }

        if ($blocks === []) {
            return '';
        }

        return '=== '.$fileDiff['path']." ===\n".implode("\n\n", $blocks);
    }

    /**
     * @param  array<int, array{type: string, content: string, old_line: int|null, new_line: int|null}>  $lines
     * @param  array{type: string, content: string, old_line: int|null, new_line: int|null}  $contextLine
     */
    private function neighborIsChanged(array $lines, array $contextLine): bool
    {
        if ($contextLine['type'] !== 'context') {
            return true;
        }

        foreach ($lines as $line) {
            if (in_array($line['type'], ['addition', 'deletion'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{path: string, hunks: array<int, array{header: string, lines: array<int, array{type: string, content: string, old_line: int|null, new_line: int|null}>}>}|null
     */
    private function parseSingleFileDiff(string $chunk): ?array
    {
        if (! preg_match('/^diff --git a\/(.+?) b\/(.+)$/m', $chunk, $fileMatches)) {
            return null;
        }

        $path = $fileMatches[2];
        $lines = preg_split("/\r\n|\n|\r/", $chunk) ?: [];
        $hunks = [];
        $currentHunk = null;
        $oldLine = 0;
        $newLine = 0;

        foreach ($lines as $line) {
            if (preg_match('/^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@(.*)$/', $line, $hunkMatches)) {
                if ($currentHunk !== null) {
                    $hunks[] = $currentHunk;
                }

                $oldLine = (int) $hunkMatches[1];
                $newLine = (int) $hunkMatches[2];
                $currentHunk = [
                    'header' => trim(substr($line, 3)),
                    'lines' => [],
                ];

                continue;
            }

            if ($currentHunk === null || $line === '' || str_starts_with($line, 'diff --git') || str_starts_with($line, 'index ')
                || str_starts_with($line, '--- ') || str_starts_with($line, '+++ ')) {
                continue;
            }

            $type = 'context';
            $content = $line;

            if (str_starts_with($line, '+')) {
                $type = 'addition';
                $content = substr($line, 1);
                $currentHunk['lines'][] = [
                    'type' => $type,
                    'content' => $content,
                    'old_line' => null,
                    'new_line' => $newLine,
                ];
                $newLine++;

                continue;
            }

            if (str_starts_with($line, '-')) {
                $type = 'deletion';
                $content = substr($line, 1);
                $currentHunk['lines'][] = [
                    'type' => $type,
                    'content' => $content,
                    'old_line' => $oldLine,
                    'new_line' => null,
                ];
                $oldLine++;

                continue;
            }

            if (str_starts_with($line, ' ')) {
                $content = substr($line, 1);
            }

            if (str_starts_with($line, '\\')) {
                continue;
            }

            $currentHunk['lines'][] = [
                'type' => $type,
                'content' => $content,
                'old_line' => $oldLine,
                'new_line' => $newLine,
            ];
            $oldLine++;
            $newLine++;
        }

        if ($currentHunk !== null) {
            $hunks[] = $currentHunk;
        }

        return [
            'path' => $path,
            'hunks' => $hunks,
        ];
    }
}
