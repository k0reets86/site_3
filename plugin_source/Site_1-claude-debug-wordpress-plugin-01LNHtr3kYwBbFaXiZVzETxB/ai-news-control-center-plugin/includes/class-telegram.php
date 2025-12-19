<?php
/**
 * Telegram Publisher
 * Handles posting to Telegram channels
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Telegram {

    /**
     * Bot token
     */
    private $bot_token;

    /**
     * Channel ID
     */
    private $channel_id;

    /**
     * API base URL
     */
    private $api_url = 'https://api.telegram.org/bot';

    /**
     * Constructor
     */
    public function __construct() {
        $this->bot_token = AINCC_Settings::get('telegram_bot_token', '');
        $this->channel_id = AINCC_Settings::get('telegram_channel_id', '');
    }

    /**
     * Check if Telegram is configured
     */
    public function is_configured() {
        return !empty($this->bot_token) && !empty($this->channel_id);
    }

    /**
     * Make API request
     */
    private function request($method, $params = []) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error' => 'Telegram not configured',
            ];
        }

        $url = $this->api_url . $this->bot_token . '/' . $method;

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'body' => $params,
        ]);

        if (is_wp_error($response)) {
            AINCC_Logger::error('Telegram API error', [
                'method' => $method,
                'error' => $response->get_error_message(),
            ]);

            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body['ok']) {
            AINCC_Logger::error('Telegram API error', [
                'method' => $method,
                'error' => $body['description'] ?? 'Unknown error',
            ]);

            return [
                'success' => false,
                'error' => $body['description'] ?? 'Unknown error',
            ];
        }

        return [
            'success' => true,
            'result' => $body['result'],
        ];
    }

    /**
     * Send text message
     */
    public function send_message($text, $parse_mode = 'HTML', $disable_preview = false) {
        return $this->request('sendMessage', [
            'chat_id' => $this->channel_id,
            'text' => $text,
            'parse_mode' => $parse_mode,
            'disable_web_page_preview' => $disable_preview,
        ]);
    }

    /**
     * Send photo with caption
     */
    public function send_photo($photo_url, $caption = '', $parse_mode = 'HTML') {
        return $this->request('sendPhoto', [
            'chat_id' => $this->channel_id,
            'photo' => $photo_url,
            'caption' => $caption,
            'parse_mode' => $parse_mode,
        ]);
    }

    /**
     * Post article to Telegram
     */
    public function post_article($draft, $wp_url = '') {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error' => 'Telegram not configured',
            ];
        }

        // Format message
        $message = $this->format_article_message($draft, $wp_url);

        // Determine posting method
        $has_image = !empty($draft['image_url']);

        if ($has_image) {
            // Caption limit is 1024 characters for photos
            $caption = mb_substr($message, 0, 1024);
            $result = $this->send_photo($draft['image_url'], $caption);
        } else {
            // Message limit is 4096 characters
            $text = mb_substr($message, 0, 4096);
            $result = $this->send_message($text);
        }

        if ($result['success']) {
            // Save to social_posts table
            $db = new AINCC_Database();
            global $wpdb;

            $wpdb->insert(
                $db->table('social_posts'),
                [
                    'draft_id' => $draft['id'],
                    'platform' => 'telegram',
                    'platform_post_id' => $result['result']['message_id'] ?? null,
                    'url' => $this->get_message_link($result['result']['message_id'] ?? 0),
                    'lang' => $draft['lang'],
                    'status' => 'published',
                    'posted_at' => current_time('mysql'),
                ]
            );

            AINCC_Logger::info('Posted to Telegram', [
                'draft_id' => $draft['id'],
                'message_id' => $result['result']['message_id'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * Format article for Telegram message
     */
    private function format_article_message($draft, $wp_url = '') {
        $lang = $draft['lang'] ?? 'de';

        // Language-specific labels
        $labels = [
            'de' => [
                'read_more' => 'Weiterlesen',
                'source' => 'Quelle',
            ],
            'ua' => [
                'read_more' => 'Читати далі',
                'source' => 'Джерело',
            ],
            'ru' => [
                'read_more' => 'Читать далее',
                'source' => 'Источник',
            ],
            'en' => [
                'read_more' => 'Read more',
                'source' => 'Source',
            ],
        ];

        $label = $labels[$lang] ?? $labels['de'];

        // Format: Title + Lead + Link
        $parts = [];

        // Title (bold)
        $parts[] = '<b>' . $this->escape_html($draft['title']) . '</b>';

        // Lead/summary
        if (!empty($draft['lead'])) {
            $parts[] = $this->escape_html($draft['lead']);
        }

        // Tags as hashtags
        $tags = json_decode($draft['tags'], true);
        if (!empty($tags) && is_array($tags)) {
            $hashtags = array_map(function ($tag) {
                return '#' . preg_replace('/[^a-zA-Z0-9а-яА-ЯіІїЇєЄäöüÄÖÜß]/u', '', $tag);
            }, array_slice($tags, 0, 5));
            $parts[] = implode(' ', $hashtags);
        }

        // Read more link
        if (!empty($wp_url)) {
            $parts[] = "\n🔗 <a href=\"{$wp_url}\">{$label['read_more']}</a>";
        }

        return implode("\n\n", $parts);
    }

    /**
     * Escape HTML for Telegram
     */
    private function escape_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Get message link (for public channels)
     */
    private function get_message_link($message_id) {
        if (!$message_id) {
            return '';
        }

        // Channel ID format: @channelname or -100XXXXXXXXXX
        $channel = ltrim($this->channel_id, '@');

        if (strpos($channel, '-100') === 0) {
            // Private channel ID - convert to public format
            $channel = substr($channel, 4);
        }

        return "https://t.me/{$channel}/{$message_id}";
    }

    /**
     * Test connection
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => 'Bot token or channel ID not configured',
            ];
        }

        // Get bot info
        $result = $this->request('getMe');

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to connect: ' . ($result['error'] ?? 'Unknown error'),
            ];
        }

        // Try to get chat info
        $chat_result = $this->request('getChat', [
            'chat_id' => $this->channel_id,
        ]);

        if (!$chat_result['success']) {
            return [
                'success' => false,
                'message' => 'Bot connected, but cannot access channel: ' . ($chat_result['error'] ?? 'Unknown error'),
            ];
        }

        return [
            'success' => true,
            'message' => 'Connected successfully',
            'bot' => $result['result'],
            'channel' => $chat_result['result'],
        ];
    }

    /**
     * Send breaking news alert
     */
    public function send_breaking_news($title, $summary, $url = '') {
        $message = "🚨 <b>BREAKING</b>\n\n";
        $message .= "<b>" . $this->escape_html($title) . "</b>\n\n";
        $message .= $this->escape_html($summary);

        if ($url) {
            $message .= "\n\n🔗 <a href=\"{$url}\">Mehr erfahren</a>";
        }

        return $this->send_message($message);
    }

    /**
     * Post digest (multiple articles)
     */
    public function post_digest($articles, $title = '') {
        $lang = $articles[0]['lang'] ?? 'de';

        $digest_titles = [
            'de' => '📰 Nachrichten des Tages',
            'ua' => '📰 Новини дня',
            'ru' => '📰 Новости дня',
            'en' => '📰 Daily News',
        ];

        $message = '<b>' . ($title ?: $digest_titles[$lang] ?? $digest_titles['de']) . "</b>\n\n";

        foreach ($articles as $index => $article) {
            $num = $index + 1;
            $message .= "{$num}. <a href=\"{$article['url']}\">{$article['title']}</a>\n";
        }

        return $this->send_message($message);
    }
}
