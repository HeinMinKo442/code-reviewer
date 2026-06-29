<?php

namespace App\Services\VertexAi;

class CodeReviewResponseSchema
{
    /**
     * Vertex AI structured output schema for pull request review comments.
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'path' => [
                        'type' => 'STRING',
                        'description' => 'Repository-relative file path from the diff.',
                    ],
                    'line' => [
                        'type' => 'INTEGER',
                        'description' => 'Line number on the RIGHT side for the added or modified line.',
                    ],
                    'side' => [
                        'type' => 'STRING',
                        'enum' => ['RIGHT'],
                        'description' => 'Always RIGHT for additions and modifications.',
                    ],
                    'comment' => [
                        'type' => 'STRING',
                        'description' => 'Markdown review feedback for the targeted line.',
                    ],
                ],
                'required' => ['path', 'line', 'side', 'comment'],
            ],
        ];
    }
}
