<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! function_exists('current_time')) {
    function current_time(string $type = 'mysql', bool $gmt = false)
    {
        $timestamp = $gmt ? time() : time();
        if ($type === 'timestamp') {
            return $timestamp;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return stripslashes((string) $value);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        $filtered = strip_tags($value);
        $filtered = preg_replace('/[\r\n\t]+/', ' ', $filtered);
        return trim((string) $filtered);
    }
}

if (! function_exists('sanitize_title')) {
    function sanitize_title(string $title): string
    {
        $title = strtolower(trim(strip_tags($title)));
        $title = preg_replace('/[^a-z0-9_\-\s]/', '', $title);
        $title = preg_replace('/\s+/', '-', $title);
        return trim((string) $title, '-');
    }
}

if (! function_exists('sanitize_title_with_dashes')) {
    function sanitize_title_with_dashes(string $title): string
    {
        return sanitize_title($title);
    }
}

if (! function_exists('esc_like')) {
    function esc_like(string $text): string
    {
        return addslashes(str_replace(['%', '_'], ['\\%', '\\_'], $text));
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return trim($url);
    }
}

if (! class_exists('wpdb')) {
    class wpdb
    {
        public string $prefix = 'wp_';
        public int $insert_id = 0;

        /** @var array<int, array<string, mixed>> */
        public array $insertLog = [];
        /** @var array<int, array<string, mixed>> */
        public array $updateLog = [];
        /** @var array<int, array<string, mixed>> */
        public array $queryLog = [];
        /** @var array<int, mixed> */
        public array $getRowQueue = [];
        /** @var array<int, mixed> */
        public array $getResultsQueue = [];
        /** @var array<int, mixed> */
        public array $getColQueue = [];
        /** @var array<int, mixed> */
        public array $getVarQueue = [];

        public function insert($table, $data, $format = null)
        {
            $this->insertLog[] = compact('table', 'data', 'format');
            $this->insert_id++;
            return true;
        }

        public function update($table, $data, $where, $format = null, $whereFormat = null)
        {
            $this->updateLog[] = compact('table', 'data', 'where', 'format', 'whereFormat');
            return 1;
        }

        public function get_col($query)
        {
            $this->queryLog[] = ['type' => 'get_col', 'query' => $query];
            $result = array_shift($this->getColQueue);
            return $result !== null ? $result : [];
        }

        public function get_row($query, $output = ARRAY_A)
        {
            $this->queryLog[] = ['type' => 'get_row', 'query' => $query];
            $result = array_shift($this->getRowQueue);
            return $result !== null ? $result : null;
        }

        public function get_results($query, $output = ARRAY_A)
        {
            $this->queryLog[] = ['type' => 'get_results', 'query' => $query];
            $result = array_shift($this->getResultsQueue);
            return $result !== null ? $result : [];
        }

        public function get_var($query)
        {
            $this->queryLog[] = ['type' => 'get_var', 'query' => $query];
            return array_shift($this->getVarQueue);
        }

        public function query($query)
        {
            $this->queryLog[] = ['type' => 'query', 'query' => $query];
            return 1;
        }

        public function prepare($query, ...$args)
        {
            $this->queryLog[] = ['type' => 'prepare', 'query' => $query, 'args' => $args];
            return $query;
        }
    }
}

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID;
        public string $post_title;
        public string $post_type;
        public string $post_date_gmt;
        public string $post_modified_gmt;

        public function __construct(int $id, array $data)
        {
            $this->ID = $id;
            $this->post_title = $data['post_title'] ?? '';
            $this->post_type = $data['post_type'] ?? '';
            $now = gmdate('Y-m-d H:i:s');
            $this->post_date_gmt = $data['post_date_gmt'] ?? $now;
            $this->post_modified_gmt = $data['post_modified_gmt'] ?? $now;
        }
    }
}

if (! function_exists('wp_insert_post')) {
    function wp_insert_post(array $data, bool $wp_error = false)
    {
        $next = $GLOBALS['wp_posts_next_id'] ?? 0;
        $next++;
        $GLOBALS['wp_posts_next_id'] = $next;
        $post = new WP_Post($next, $data);
        $GLOBALS['wp_posts'][$next] = $post;
        return $next;
    }
}

if (! function_exists('get_post')) {
    function get_post(int $post_id)
    {
        return $GLOBALS['wp_posts'][$post_id] ?? null;
    }
}

if (! function_exists('wp_update_post')) {
    function wp_update_post(array $data, bool $wp_error = false)
    {
        $id = $data['ID'] ?? 0;
        if ($id <= 0 || ! isset($GLOBALS['wp_posts'][$id])) {
            return 0;
        }

        $post = $GLOBALS['wp_posts'][$id];
        if (isset($data['post_title'])) {
            $post->post_title = $data['post_title'];
        }
        $post->post_modified_gmt = gmdate('Y-m-d H:i:s');

        return $id;
    }
}

if (! function_exists('wp_delete_post')) {
    function wp_delete_post(int $post_id, bool $force_delete = false)
    {
        unset($GLOBALS['wp_posts'][$post_id], $GLOBALS['wp_meta'][$post_id]);
        return true;
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta(int $post_id, string $meta_key, $meta_value)
    {
        $GLOBALS['wp_meta'][$post_id][$meta_key] = $meta_value;
        return true;
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $meta_key, bool $single = true)
    {
        if (! isset($GLOBALS['wp_meta'][$post_id][$meta_key])) {
            return $single ? '' : [];
        }

        return $GLOBALS['wp_meta'][$post_id][$meta_key];
    }
}

if (! function_exists('get_posts')) {
    function get_posts(array $args): array
    {
        $posts = array_values($GLOBALS['wp_posts'] ?? []);

        return array_values(array_filter($posts, static function ($post) use ($args) {
            return $post instanceof WP_Post && $post->post_type === ($args['post_type'] ?? '');
        }));
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! defined('WP_CLI')) {
    define('WP_CLI', false);
}

if (! class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static array $messages = [];

        public static function log(string $message): void
        {
            self::$messages[] = $message;
        }

        public static function success(string $message): void
        {
            self::$messages[] = $message;
        }

        public static function error(string $message): void
        {
            throw new \RuntimeException($message);
        }
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        private string $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->message = $message;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = [])
    {
        return [
            'body' => $args['body'] ?? '',
            'response' => ['code' => 200],
        ];
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return is_array($response) ? ($response['body'] ?? '') : '';
    }
}





