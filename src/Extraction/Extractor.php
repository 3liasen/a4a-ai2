<?php

declare(strict_types=1);

namespace Axs4allAi\Extraction;

final class Extractor
{
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
        foreach ($scored as $row) {
            $text = $row['text'];
            if (! in_array($text, $selected, true)) {
                $selected[] = $text;
            }
            if (count($selected) >= 5) {
                break;
            }
        }

        return $selected;
    }

    /**
     * @param array<int, string> $fragments
     * @param array<string, array<int, string>> $metadata
     * @return array<int, array{score:int,index:int,text:string}>
     */
    private function scoreFragments(array $fragments, array $metadata): array
    {
        $scored = [];
        foreach ($fragments as $index => $text) {
            $score = 0;
            $lower = mb_strtolower($text);

            foreach ($metadata['phrases'] as $phrase) {
                if ($phrase !== '' && mb_strpos($lower, $phrase) !== false) {
                    $score += 5;
                }
            }

            foreach ($metadata['keywords'] as $keyword) {
                if ($keyword !== '' && mb_strpos($lower, $keyword) !== false) {
                    $score += 2;
                }
            }

            foreach ($metadata['options'] as $option) {
                if ($option !== '' && mb_strpos($lower, $option) !== false) {
                    $score += 1;
                }
            }

            $length = mb_strlen($text);
            if ($length >= 40 && $length <= 420) {
                $score += 1;
            } elseif ($length < 25) {
                $score -= 1;
            }

            if ($score > 0) {
                $scored[] = [
                    'score' => $score,
                    'index' => $index,
                    'text' => $text,
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
     * @return array<string, array<int, string>>
     */
    private function normaliseMetadata(array $metadata): array
    {
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
        ];
    }
}
