<?php

declare(strict_types=1);

namespace Axs4allAi\Crawl;

use Axs4allAi\Infrastructure\DebugLogger;

final class Scraper
{
    private int $maxAttempts;
    private int $initialDelayMs;
    private ?DebugLogger $logger;

    public function __construct(int $maxAttempts = 3, int $initialDelayMs = 250, ?DebugLogger $logger = null)
    {
        $this->maxAttempts = max(1, $maxAttempts);
        $this->initialDelayMs = max(0, $initialDelayMs);
        $this->logger = $logger;
    }

    /**
     * @return array{html:string,url:string,disallow_index:bool,status_code:int}|null
     */
    public function fetch(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            $this->log('scraper_error', 'Scraper aborted: empty URL.');
            return null;
        }

        $requestUrl = esc_url_raw($url);
        if (! $requestUrl) {
            $this->log('scraper_error', sprintf('Scraper aborted: invalid URL %s.', $url));
            return null;
        }

        $delayMs = $this->initialDelayMs;
        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            $response = wp_remote_get(
                $requestUrl,
                [
                    'timeout' => 15,
                    'redirection' => 3,
                    'headers' => [
                        'User-Agent' => 'axs4all-ai/0.0.25 (+https://axs4all-ai)',
                        'Accept' => 'text/html,application/xhtml+xml',
                    ],
                ]
            );

            if (is_wp_error($response)) {
                $this->log(
                    'scraper_error',
                    sprintf('Request error for %s (attempt %d/%d): %s', $requestUrl, $attempt, $this->maxAttempts, $response->get_error_message())
                );
                if ($attempt < $this->maxAttempts) {
                    $this->sleep($delayMs);
                    $delayMs = max($delayMs, 50) * 2;
                    continue;
                }
                return null;
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($this->shouldRetryStatus($code) && $attempt < $this->maxAttempts) {
                $this->log(
                    'scraper_retry',
                    sprintf('Retrying %s due to HTTP %d (attempt %d/%d)', $requestUrl, $code, $attempt, $this->maxAttempts)
                );
                $this->sleep($delayMs);
                $delayMs = max($delayMs, 50) * 2;
                continue;
            }

            if ($code < 200 || $code >= 300) {
                $this->log('scraper_error', sprintf('Unexpected status %d for %s.', $code, $requestUrl));
                return null;
            }

            $body = wp_remote_retrieve_body($response);
            if (! is_string($body) || trim($body) === '') {
                $this->log('scraper_error', sprintf('Empty body returned for %s.', $requestUrl));
                return null;
            }

            $contentType = wp_remote_retrieve_header($response, 'content-type');
            if (is_string($contentType) && strpos($contentType, 'text/html') === false) {
                $this->log(
                    'scraper_skip',
                    sprintf('Skipped non-HTML content for %s (content-type %s).', $requestUrl, $contentType)
                );
                return null;
            }

            $disallowIndex = $this->hasNoindexDirective($response, $body);

            return [
                'html' => $body,
                'url' => $requestUrl,
                'disallow_index' => $disallowIndex,
                'status_code' => $code,
            ];
        }

        return null;
    }

    private function shouldRetryStatus(int $status): bool
    {
        return $status === 429 || ($status >= 500 && $status < 600);
    }

    private function sleep(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        usleep($milliseconds * 1000);
    }

    /**
     * @param array<string, mixed>|null $response
     */
    private function hasNoindexDirective($response, string $html): bool
    {
        $robotsHeader = wp_remote_retrieve_header($response, 'x-robots-tag');
        if (is_string($robotsHeader) && $this->containsNoindex($robotsHeader)) {
            return true;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (@$dom->loadHTML($html)) {
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//meta[@name="robots" or @name="googlebot"]');
            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    $content = strtolower((string) $node->getAttribute('content'));
                    if ($this->containsNoindex($content)) {
                        return true;
                    }
                }
            }
        }
        libxml_clear_errors();

        return false;
    }

    private function containsNoindex(string $value): bool
    {
        $value = strtolower($value);
        return strpos($value, 'noindex') !== false || strpos($value, 'none') !== false;
    }

    private function log(string $type, string $message, array $context = []): void
    {
        error_log('[axs4all-ai] ' . $message);
        if ($this->logger instanceof DebugLogger) {
            $this->logger->record($type, $message, $context);
        }
    }
}
