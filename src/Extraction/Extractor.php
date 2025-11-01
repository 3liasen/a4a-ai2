<?php

declare(strict_types=1);

namespace Axs4allAi\Extraction;

use Axs4allAi\Infrastructure\DebugLogger;

final class Extractor
{
    private const PHRASE_WEIGHT = 6;
    private const KEYWORD_WEIGHT = 3;
    private const OPTION_WEIGHT = 1;
    private const LENGTH_BONUS = 2;
    private const LENGTH_PENALTY_SHORT = 2;
    private const LENGTH_PENALTY_LONG = 2;

    private ?DebugLogger $logger;

    public function __construct(?DebugLogger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @return array<int, string>
     */
    public function extract(string $html, string $category, array $metadata = []): array
    {
        $metadata = $this->normaliseMetadata($metadata);
        $fragments = $this->extractTextFragments($html);
        $scored = $this->scoreFragments($fragments, $metadata);

        if (empty($scored)) {
            error_log(sprintf('[axs4all-ai] Extractor found no usable snippets for category %s.', $category));
            return [];
        }

        usort(
            $scored,
            static function (array $a, array $b): int {
                if ($a['score'] === $b['score']) {
                    return $a['index'] <=> $b['index'];
                }
                return $b['score'] <=> $a['score'];
            }
        );

        $selected = [];
        $selectedMap = [];
        $hasMetadata = ! empty($metadata['phrases']) || ! empty($metadata['keywords']) || ! empty($metadata['options']);
        $snippetLimit = $this->resolveSnippetLimit($metadata, $hasMetadata);

        foreach ($scored as $row) {
            $text = $row['text'];
            $fingerprint = mb_strtolower($text);
            if (! isset($selectedMap[$fingerprint])) {
                $selected[] = $text;
                $selectedMap[$fingerprint] = true;
            }
            if (count($selected) >= $snippetLimit) {
                break;
            }
        }

        if ($this->logger instanceof DebugLogger) {
            $topSnippets = array_slice(
                array_map(
                    static function (array $row): array {
                        $preview = mb_substr($row['text'], 0, 160);
                        if (mb_strlen($row['text']) > 160) {
                            $preview .= '...';
                        }

                        return [
                            'score' => $row['score'],
                            'phrases' => $row['matches']['phrases'],
                            'keywords' => $row['matches']['keywords'],
                            'options' => $row['matches']['options'],
                            'text' => $preview,
                        ];
                    },
                    $scored
                ),
                0,
                3
            );

            $encoded = json_encode($topSnippets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->log(
                'extract_debug',
                'Extractor scoring summary',
                [
                    'category' => $category,
                    'fragments' => count($fragments),
                    'scored' => count($scored),
                    'selected' => count($selected),
                    'snippet_limit' => $snippetLimit,
                    'top_snippets' => $encoded !== false ? $encoded : '',
                ]
            );
        }

        return $selected;
    }

    /**
     * @param array<int, string> $fragments
     * @param array<string, mixed> $metadata
     * @return array<int, array{score:int,index:int,text:string,matches:array{phrases:int,keywords:int,options:int}}>
     */
    private function scoreFragments(array $fragments, array $metadata): array
    {
        $scored = [];
        foreach ($fragments as $index => $text) {
            $score = 0;
            $lower = mb_strtolower($text);
            $phraseMatches = 0;
            $keywordMatches = 0;
            $optionMatches = 0;

            foreach ($metadata['phrases'] as $phrase) {
                if ($phrase !== '' && mb_strpos($lower, $phrase) !== false) {
                    $score += self::PHRASE_WEIGHT;
                    $phraseMatches++;
                }
            }

            foreach ($metadata['keywords'] as $keyword) {
                if ($keyword !== '' && mb_strpos($lower, $keyword) !== false) {
                    $score += self::KEYWORD_WEIGHT;
                    $keywordMatches++;
                }
            }

            foreach ($metadata['options'] as $option) {
                if ($option !== '' && mb_strpos($lower, $option) !== false) {
                    $score += self::OPTION_WEIGHT;
                    $optionMatches++;
                }
            }

            $length = mb_strlen($text);
            if ($length >= 60 && $length <= 420) {
                $score += self::LENGTH_BONUS;
            } elseif ($length < 40) {
                $score -= self::LENGTH_PENALTY_SHORT;
            } elseif ($length > 520) {
                $score -= self::LENGTH_PENALTY_LONG;
            }

            if (($phraseMatches + $keywordMatches) >= 2) {
                $score += 2;
            }

            if ($score > 0) {
                $scored[] = [
                    'score' => $score,
                    'index' => $index,
                    'text' => $text,
                    'matches' => [
                        'phrases' => $phraseMatches,
                        'keywords' => $keywordMatches,
                        'options' => $optionMatches,
                    ],
                ];
            }
        }

        if (! empty($scored)) {
            return $scored;
        }

        // fallback to the first fragments if nothing matched the metadata
        $fallback = [];
        foreach ($fragments as $index => $text) {
            $fallback[] = [
                'score' => 1,
                'index' => $index,
                'text' => $text,
                'matches' => [
                    'phrases' => 0,
                    'keywords' => 0,
                    'options' => 0,
                ],
            ];
            if (count($fallback) >= 3) {
                break;
            }
        }

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function extractTextFragments(string $html): array
    {
        $fragments = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (@$dom->loadHTML($html)) {
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//p|//li|//h1|//h2|//h3|//h4|//blockquote');
            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    $text = trim($node->textContent ?? '');
                    $text = preg_replace('/\s+/', ' ', $text ?? '');
                    if (is_string($text)) {
                        $clean = trim($text);
                        if ($clean !== '') {
                            $fragments[] = $clean;
                        }
                    }
                }
            }
        }
        libxml_clear_errors();

        if (! empty($fragments)) {
            return $fragments;
        }

        // fallback: strip tags and split
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        if ($text === '') {
            return [];
        }

        foreach (preg_split('/(?<=[.!?])\s+/', $text) as $sentence) {
            $sentence = trim($sentence);
            if ($sentence !== '') {
                $fragments[] = $sentence;
            }
        }

        return $fragments;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function normaliseMetadata(array $metadata): array
    {
        $limit = null;
        if (isset($metadata['snippet_limit']) && is_numeric($metadata['snippet_limit'])) {
            $limit = max(1, min(10, (int) $metadata['snippet_limit']));
        }

        $extractList = static function ($key) use ($metadata): array {
            if (! isset($metadata[$key])) {
                return [];
            }

            $value = $metadata[$key];
            if (is_string($value)) {
                $value = preg_split('/\r\n|\r|\n|,/', $value) ?: [];
            }

            if (! is_array($value)) {
                return [];
            }

            $sanitised = [];
            foreach ($value as $item) {
                $item = mb_strtolower(trim((string) $item));
                if ($item !== '') {
                    $sanitised[$item] = $item;
                }
            }

            return array_values($sanitised);
        };

        return [
            'phrases' => $extractList('phrases'),
            'keywords' => $extractList('keywords'),
            'options' => $extractList('options'),
            'snippet_limit' => $limit,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolveSnippetLimit(array $metadata, bool $hasMetadata): int
    {
        if (isset($metadata['snippet_limit']) && is_int($metadata['snippet_limit']) && $metadata['snippet_limit'] > 0) {
            return $metadata['snippet_limit'];
        }

        return $hasMetadata ? 3 : 5;
    }

    private function log(string $type, string $message, array $context = []): void
    {
        if (! $this->logger instanceof DebugLogger) {
            return;
        }

        $this->logger->record($type, $message, $context);
    }
}
