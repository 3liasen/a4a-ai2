<?php

declare(strict_types=1);

namespace Axs4allAi\Crawl;

final class Scraper
{
    public function fetch(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            error_log('[axs4all-ai] Scraper aborted: empty URL provided.');
            return null;
        }

        $requestUrl = esc_url_raw($url);
        if (! $requestUrl) {
            error_log(sprintf('[axs4all-ai] Scraper aborted: invalid URL %s.', $url));
            return null;
        }

        $response = wp_remote_get(
            $requestUrl,
            [
                'timeout' => 15,
                'redirection' => 3,
                'headers' => [
                    'User-Agent' => 'axs4all-ai/0.0.20 (+https://axs4all-ai)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]
        );

        if (is_wp_error($response)) {
            error_log(sprintf('[axs4all-ai] Scraper error for %s: %s', $requestUrl, $response->get_error_message()));
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log(sprintf('[axs4all-ai] Scraper unexpected status %d for %s.', $code, $requestUrl));
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (! is_string($body) || trim($body) === '') {
            error_log(sprintf('[axs4all-ai] Scraper received empty body for %s.', $requestUrl));
            return null;
        }

        $contentType = wp_remote_retrieve_header($response, 'content-type');
        if (is_string($contentType) && strpos($contentType, 'text/html') === false) {
            error_log(sprintf('[axs4all-ai] Scraper skipped non-HTML content for %s (content-type: %s).', $requestUrl, $contentType));
            return null;
        }

        return $body;
    }
}
