<?php

declare(strict_types=1);

namespace Axs4allAi\Crawl;

use Axs4allAi\Category\CategoryRepository;
use Axs4allAi\Data\ClientRepository;
use Axs4allAi\Data\QueueRepository;

final class ClientCrawlScheduler
{
    private const HOOK = 'axs4all_ai_crawl_client';
    /**
     * @var array<string, array{interval:int,schedule:?string}>
     */
    private const FREQUENCY_CONFIG = [
        'manual' => ['interval' => 0, 'schedule' => null],
        'hourly' => ['interval' => HOUR_IN_SECONDS, 'schedule' => 'hourly'],
        'twicedaily' => ['interval' => 12 * HOUR_IN_SECONDS, 'schedule' => 'twicedaily'],
        'daily' => ['interval' => DAY_IN_SECONDS, 'schedule' => 'daily'],
        'weekly' => ['interval' => WEEK_IN_SECONDS, 'schedule' => 'axs4all_ai_weekly'],
    ];

    private ClientRepository $clients;
    private QueueRepository $queue;
    private CategoryRepository $categories;

    public function __construct(ClientRepository $clients, QueueRepository $queue, CategoryRepository $categories)
    {
        $this->clients = $clients;
        $this->queue = $queue;
        $this->categories = $categories;
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'registerIntervals']);
        add_action('init', [$this, 'syncSchedules'], 10, 0);
        add_action(self::HOOK, [$this, 'handleCron'], 10, 1);
    }

    /**
     * @param array<string, array<int, mixed>> $schedules
     * @return array<string, array<int, mixed>>
     */
    public function registerIntervals(array $schedules): array
    {
        $schedules['axs4all_ai_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Weekly (axs4all AI)', 'axs4all-ai'),
        ];

        return $schedules;
    }

    public function syncSchedules(): void
    {
        foreach ($this->clients->all() as $client) {
            $this->applySchedule($client);
        }
    }

    /**
     * @param array<string, mixed>|object|null $client
     */
    private function applySchedule($client): void
    {
        $client = $this->normalizeClient($client);
        $clientId = (int) ($client['id'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $status = isset($client['status']) ? strtolower((string) $client['status']) : 'active';
        $frequency = $this->clients->sanitizeFrequency((string) ($client['crawl_frequency'] ?? ClientRepository::DEFAULT_FREQUENCY));

        if ($status !== 'active' || $frequency === ClientRepository::DEFAULT_FREQUENCY) {
            $this->clearSchedule($clientId);
            return;
        }

        $config = self::FREQUENCY_CONFIG[$frequency] ?? null;
        if ($config === null || $config['schedule'] === null) {
            $this->clearSchedule($clientId);
            return;
        }

        $event = function_exists('wp_get_scheduled_event')
            ? wp_get_scheduled_event(self::HOOK, [$clientId])
            : null;
        $existingScheduleValue = $this->extractEventValue($event, 'schedule');
        $existingSchedule = is_string($existingScheduleValue) ? $existingScheduleValue : null;

        if ($existingSchedule === $config['schedule'] && $event !== null) {
            return;
        }

        if ($event !== null) {
            $timestampValue = $this->extractEventValue($event, 'timestamp');
            $timestamp = is_numeric($timestampValue) ? (int) $timestampValue : null;
            if ($timestamp === null) {
                $timestamp = wp_next_scheduled(self::HOOK, [$clientId]);
            }
            if ($timestamp) {
                wp_unschedule_event((int) $timestamp, self::HOOK, [$clientId]);
            }
        } else {
            $timestamp = wp_next_scheduled(self::HOOK, [$clientId]);
            if ($timestamp) {
                wp_unschedule_event((int) $timestamp, self::HOOK, [$clientId]);
            }
        }

        wp_schedule_event(time() + (int) $config['interval'], $config['schedule'], self::HOOK, [$clientId]);
    }

    private function clearSchedule(int $clientId): void
    {
        $timestamp = wp_next_scheduled(self::HOOK, [$clientId]);
        while ($timestamp) {
            wp_unschedule_event((int) $timestamp, self::HOOK, [$clientId]);
            $timestamp = wp_next_scheduled(self::HOOK, [$clientId]);
        }
    }

    public function handleCron(int $clientId): void
    {
        $clientData = $this->clients->find($clientId);
        $client = $this->normalizeClient($clientData);
        if (empty($client) || ($client['status'] ?? 'active') !== 'active') {
            $this->clearSchedule($clientId);
            return;
        }

        $urls = $client['urls'] ?? [];
        if (empty($urls)) {
            return;
        }

        $categorySlug = $this->determineCategorySlug($client);
        foreach ($urls as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (empty($row['url'])) {
                continue;
            }

            $this->queue->enqueueWithId(
                (string) $row['url'],
                $categorySlug,
                5,
                ! empty($row['crawl_subpages'])
            );
        }
    }

    /**
     * @param array<string, mixed>|object|null $client
     */
    private function determineCategorySlug($client): string
    {
        $client = $this->normalizeClient($client);
        $categories = $client['categories'] ?? [];
        if (empty($categories)) {
            return 'default';
        }

        $categoryId = (int) $categories[0];
        if ($categoryId <= 0) {
            return 'default';
        }

        $category = $this->categories->find($categoryId);
        if ($category === null) {
            return 'default';
        }

        $slug = sanitize_title((string) $category['name']);
        if ($slug === '') {
            $slug = sanitize_title_with_dashes((string) $category['name']);
        }

        return $slug !== '' ? $slug : 'default';
    }
    /**
     * @param mixed $client
     * @return array<string, mixed>
     */
    private function normalizeClient($client): array
    {
        if (is_object($client)) {
            $client = (array) $client;
        }

        return is_array($client) ? $client : [];
    }

    /**
     * @param mixed $event
     * @return mixed
     */
    private function extractEventValue($event, string $key)
    {
        if (is_array($event) && array_key_exists($key, $event)) {
            return $event[$key];
        }

        if (is_object($event) && isset($event->$key)) {
            return $event->$key;
        }

        return null;
    }
}
