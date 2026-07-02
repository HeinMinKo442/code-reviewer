<?php

namespace App\Services\File;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CodeReviewRulesFileService
{
    /**
     * Rule files bundled with the image; read-only at runtime on Cloud Run.
     *
     * @var list<string>
     */
    private const RULE_FILES = [
        'php_rules.md',
        'laravel_rules.md',
        'js_rules.md',
        'html_css_rules.md',
    ];

    /**
     * Load and combine all readable code review rule files from storage.
     *
     * Missing or unreadable files are skipped so a partial ruleset still applies.
     */
    public function loadCombinedRules(): string
    {
        $sections = [];

        foreach (self::RULE_FILES as $filename) {
            $content = $this->readRuleFile($filename);

            if ($content !== null) {
                $sections[] = $content;
            }
        }

        return implode("\n\n", $sections);
    }

    /**
     * Read a single rule file from storage, returning null when unavailable.
     */
    private function readRuleFile(string $filename): ?string
    {
        try {
            $disk = Storage::disk('review_rules');

            if (! $disk->exists($filename)) {
                Log::warning('Code review rule file not found, skipping.', [
                    'disk' => 'review_rules',
                    'file' => $filename,
                ]);

                return null;
            }

            $content = $disk->get($filename);

            if (! is_string($content) || trim($content) === '') {
                Log::warning('Code review rule file is empty, skipping.', [
                    'disk' => 'review_rules',
                    'file' => $filename,
                ]);

                return null;
            }

            return trim($content);
        } catch (Throwable $exception) {
            Log::warning('Failed to read code review rule file, skipping.', [
                'disk' => 'review_rules',
                'file' => $filename,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
