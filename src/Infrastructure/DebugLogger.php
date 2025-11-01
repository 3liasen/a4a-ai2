<?php

declare(strict_types=1);

namespace Axs4allAi\Infrastructure;

final class DebugLogger
{
    private const OPTION_KEY = 'axs4all_ai_debug_events';
    private const DEFAULT_LIMIT = 50;
    private const MAX_ENTRIES = 200;

    public function record(string $type, string $message, array $context = []): void
    {
        $type = strtolower($type);
        $type = preg_replace('/[^a-z0-9_\-]/', '', $type) ?? '';
        if ($type === '') {
            $type = 'info';
        }
        $message = trim(strip_tags($message));
        $sanitisedContext = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $sanitisedContext[(string) $key] = $value === null ? null : (string) $value;
            }
        }

        $events = get_option(self::OPTION_KEY, []);
        if (! is_array($events)) {
            $events = [];
        }

        $events[] = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'message' => $message,
            'context' => $sanitisedContext,
        ];

        $excess = count($events) - self::MAX_ENTRIES;
        if ($excess > 0) {
            $events = array_slice($events, $excess);
        }

        update_option(self::OPTION_KEY, $events, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min(self::MAX_ENTRIES, $limit));
        $events = get_option(self::OPTION_KEY, []);
        if (! is_array($events) || empty($events)) {
            return [];
        }

        $events = array_slice($events, -1 * $limit);

        return array_reverse($events);
    }

    public function clear(): void
    {
        update_option(self::OPTION_KEY, [], false);
    }
}
