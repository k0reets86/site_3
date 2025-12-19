<?php
/**
 * RSS Parser
 * Fetches and parses RSS/Atom feeds from news sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_RSS_Parser {

    /**
     * Database instance
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new AINCC_Database();

        // Register cron hook
        add_action('aincc_fetch_sources', [$this, 'fetch_all_sources']);
    }

    /**
     * Fetch all sources that are due
     */
    public function fetch_all_sources() {
        AINCC_Logger::info('Starting RSS fetch cycle');

        $sources = $this->db->get_sources_to_fetch();

        if (empty($sources)) {
            AINCC_Logger::debug('No sources to fetch');
            return;
        }

        $total_new = 0;

        foreach ($sources as $source) {
            try {
                $result = $this->fetch_source($source);
                $total_new += $result['new_count'] ?? 0;

                AINCC_Logger::debug("Fetched source: {$source['name']}", [
                    'new_items' => $result['new_count'] ?? 0,
                    'total_items' => $result['total_count'] ?? 0,
                ]);

            } catch (Exception $e) {
                AINCC_Logger::error("Error fetching {$source['name']}", [
                    'error' => $e->getMessage(),
                ]);

                $this->db->update_source_fetched($source['id'], $e->getMessage());
            }
        }

        AINCC_Logger::info("RSS fetch cycle complete", ['new_items' => $total_new]);
    }

    /**
     * Fetch single source
     */
    public function fetch_source($source) {
        $start_time = microtime(true);

        // Fetch RSS feed
        $feed_content = $this->fetch_feed($source['url']);

        if (!$feed_content) {
            throw new Exception('Failed to fetch feed');
        }

        // Parse feed
        $items = $this->parse_feed($feed_content, $source);

        // Save new items
        $new_count = 0;
        foreach ($items as $item) {
            $item['source_id'] = $source['id'];
            $item['status'] = 'new';
            $item['priority'] = $this->calculate_priority($item, $source);

            $inserted = $this->db->insert_raw_item($item);
            if ($inserted) {
                $new_count++;

                // Add to processing queue
                $this->db->add_to_queue('process_item', [
                    'raw_item_id' => $inserted,
                    'source_id' => $source['id'],
                ], $this->get_queue_priority($source));
            }
        }

        // Update source last fetched
        $this->db->update_source_fetched($source['id']);

        $duration = (microtime(true) - $start_time) * 1000;

        AINCC_Logger::cron("fetch_source_{$source['id']}", $duration, [
            'new' => $new_count,
            'total' => count($items),
        ]);

        return [
            'new_count' => $new_count,
            'total_count' => count($items),
            'duration' => $duration,
        ];
    }

    /**
     * Fetch feed content via HTTP
     */
    private function fetch_feed($url) {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'AI News Control Center/1.0 (+https://your-site.de)',
                'Accept' => 'application/rss+xml, application/xml, text/xml, application/atom+xml',
            ],
        ]);

        if (is_wp_error($response)) {
            AINCC_Logger::error('Feed fetch error', [
                'url' => $url,
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            AINCC_Logger::error('Feed fetch HTTP error', [
                'url' => $url,
                'status' => $status,
            ]);
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Parse feed content (RSS or Atom)
     */
    private function parse_feed($content, $source) {
        $items = [];

        // Use SimplePie if available (WordPress includes it)
        if (function_exists('fetch_feed')) {
            // Save content to temp file for SimplePie
            $temp_file = wp_tempnam('feed_');
            file_put_contents($temp_file, $content);

            $feed = fetch_feed('file://' . $temp_file);
            unlink($temp_file);

            if (!is_wp_error($feed)) {
                $feed_items = $feed->get_items(0, 50);

                foreach ($feed_items as $feed_item) {
                    $item = $this->normalize_feed_item($feed_item, $source);
                    if ($item) {
                        $items[] = $item;
                    }
                }

                return $items;
            }
        }

        // Fallback: manual XML parsing
        return $this->parse_xml_feed($content, $source);
    }

    /**
     * Normalize SimplePie feed item
     */
    private function normalize_feed_item($feed_item, $source) {
        $url = $feed_item->get_permalink();
        $title = html_entity_decode($feed_item->get_title(), ENT_QUOTES, 'UTF-8');

        if (empty($url) || empty($title)) {
            return null;
        }

        // Clean URL (remove UTM parameters)
        $url = $this->clean_url($url);

        // Get description/summary
        $description = $feed_item->get_description();
        $content = $feed_item->get_content();

        // Clean HTML from description
        $summary = wp_strip_all_tags($description);
        $summary = html_entity_decode($summary, ENT_QUOTES, 'UTF-8');
        $summary = trim(preg_replace('/\s+/', ' ', $summary));

        // Get publication date
        $published = $feed_item->get_date('Y-m-d H:i:s');
        if (!$published) {
            $published = current_time('mysql');
        }

        // Get author
        $author = null;
        $author_obj = $feed_item->get_author();
        if ($author_obj) {
            $author = $author_obj->get_name();
        }

        // Get categories/tags
        $tags = [];
        $categories = $feed_item->get_categories();
        if ($categories) {
            foreach ($categories as $cat) {
                $tags[] = $cat->get_label() ?? $cat->get_term();
            }
        }

        // Get image if available
        $image_url = null;
        $enclosures = $feed_item->get_enclosures();
        if ($enclosures) {
            foreach ($enclosures as $enc) {
                if (strpos($enc->get_type(), 'image') !== false) {
                    $image_url = $enc->get_link();
                    break;
                }
            }
        }

        // Try to get image from content if not in enclosure
        if (!$image_url && $content) {
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches)) {
                $image_url = $matches[1];
            }
        }

        return [
            'url' => $url,
            'title' => $title,
            'summary' => substr($summary, 0, 1000),
            'body_html' => $content,
            'author' => $author,
            'published_at' => $published,
            'lang' => $source['lang'],
            'keywords' => json_encode(array_slice($tags, 0, 10)),
            'entities' => json_encode([]),
        ];
    }

    /**
     * Fallback XML parser
     */
    private function parse_xml_feed($content, $source) {
        $items = [];

        // Suppress XML errors
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content);

        if ($xml === false) {
            AINCC_Logger::error('XML parse error', [
                'errors' => libxml_get_errors(),
            ]);
            return [];
        }

        // Detect feed type (RSS or Atom)
        if (isset($xml->channel)) {
            // RSS 2.0
            foreach ($xml->channel->item as $item) {
                $parsed = $this->parse_rss_item($item, $source);
                if ($parsed) {
                    $items[] = $parsed;
                }
            }
        } elseif (isset($xml->entry)) {
            // Atom
            foreach ($xml->entry as $entry) {
                $parsed = $this->parse_atom_entry($entry, $source);
                if ($parsed) {
                    $items[] = $parsed;
                }
            }
        }

        return $items;
    }

    /**
     * Parse RSS 2.0 item
     */
    private function parse_rss_item($item, $source) {
        $title = (string) $item->title;
        $link = (string) $item->link;

        if (empty($title) || empty($link)) {
            return null;
        }

        $description = (string) $item->description;
        $pubDate = (string) $item->pubDate;
        $author = (string) $item->author;

        // Parse namespace elements if present
        $dc = $item->children('http://purl.org/dc/elements/1.1/');
        if (!$author && isset($dc->creator)) {
            $author = (string) $dc->creator;
        }

        // Convert date
        $published = null;
        if ($pubDate) {
            $published = date('Y-m-d H:i:s', strtotime($pubDate));
        }

        return [
            'url' => $this->clean_url($link),
            'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'summary' => wp_strip_all_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8')),
            'body_html' => $description,
            'author' => $author ?: null,
            'published_at' => $published ?: current_time('mysql'),
            'lang' => $source['lang'],
            'keywords' => json_encode([]),
            'entities' => json_encode([]),
        ];
    }

    /**
     * Parse Atom entry
     */
    private function parse_atom_entry($entry, $source) {
        $title = (string) $entry->title;

        // Get link - Atom can have multiple links
        $link = null;
        foreach ($entry->link as $l) {
            $rel = (string) $l['rel'];
            if ($rel === 'alternate' || empty($rel)) {
                $link = (string) $l['href'];
                break;
            }
        }

        if (empty($title) || empty($link)) {
            return null;
        }

        $summary = (string) $entry->summary;
        $content = (string) $entry->content;
        $updated = (string) $entry->updated;
        $published = (string) $entry->published;

        // Get author
        $author = null;
        if (isset($entry->author->name)) {
            $author = (string) $entry->author->name;
        }

        // Convert date
        $date = $published ?: $updated;
        $published_at = null;
        if ($date) {
            $published_at = date('Y-m-d H:i:s', strtotime($date));
        }

        return [
            'url' => $this->clean_url($link),
            'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'summary' => wp_strip_all_tags(html_entity_decode($summary ?: $content, ENT_QUOTES, 'UTF-8')),
            'body_html' => $content ?: $summary,
            'author' => $author,
            'published_at' => $published_at ?: current_time('mysql'),
            'lang' => $source['lang'],
            'keywords' => json_encode([]),
            'entities' => json_encode([]),
        ];
    }

    /**
     * Clean URL (remove tracking parameters)
     */
    private function clean_url($url) {
        $parsed = parse_url($url);

        if (!$parsed) {
            return $url;
        }

        // Remove UTM and other tracking parameters
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);

            $tracking_params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'fbclid', 'gclid', 'mc_eid', 'mc_cid', 'ref', 'source'];

            foreach ($tracking_params as $param) {
                unset($params[$param]);
            }

            $parsed['query'] = http_build_query($params);
        }

        // Rebuild URL
        $clean_url = '';
        if (isset($parsed['scheme'])) {
            $clean_url .= $parsed['scheme'] . '://';
        }
        if (isset($parsed['host'])) {
            $clean_url .= $parsed['host'];
        }
        if (isset($parsed['port'])) {
            $clean_url .= ':' . $parsed['port'];
        }
        if (isset($parsed['path'])) {
            $clean_url .= $parsed['path'];
        }
        if (!empty($parsed['query'])) {
            $clean_url .= '?' . $parsed['query'];
        }

        return $clean_url;
    }

    /**
     * Calculate item priority
     */
    private function calculate_priority($item, $source) {
        $priority = 'normal';

        // High trust source = higher priority
        if ($source['trust_score'] >= 0.9) {
            $priority = 'high';
        }

        // Official sources = higher priority
        if ($source['category'] === 'official') {
            $priority = 'high';
        }

        // Emergency sources = urgent
        if ($source['category'] === 'emergency') {
            $priority = 'urgent';
        }

        // Check for breaking news keywords
        $breaking_keywords = ['BREAKING', 'EILMELDUNG', 'ТЕРМІНОВО', 'СРОЧНО', 'Warnung', 'Alert'];
        foreach ($breaking_keywords as $keyword) {
            if (stripos($item['title'], $keyword) !== false) {
                $priority = 'urgent';
                break;
            }
        }

        return $priority;
    }

    /**
     * Get queue priority number
     */
    private function get_queue_priority($source) {
        // Lower number = higher priority
        $category_priority = [
            'emergency' => 1,
            'official' => 3,
            'media' => 5,
            'ukraine' => 4,
            'international' => 6,
            'aggregator' => 7,
            'transport' => 2,
            'economy' => 5,
        ];

        return $category_priority[$source['category']] ?? 5;
    }

    /**
     * Fetch full article content from URL
     */
    public function fetch_full_content($url) {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $html = wp_remote_retrieve_body($response);

        // Extract main content using simple heuristics
        return $this->extract_main_content($html);
    }

    /**
     * Extract main content from HTML
     */
    private function extract_main_content($html) {
        // Try to find article content
        $patterns = [
            '/<article[^>]*>(.*?)<\/article>/is',
            '/<div[^>]*class="[^"]*article[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<main[^>]*>(.*?)<\/main>/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $content = $matches[1];

                // Clean up the content
                $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
                $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
                $content = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $content);
                $content = preg_replace('/<aside[^>]*>.*?<\/aside>/is', '', $content);
                $content = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $content);

                return [
                    'html' => trim($content),
                    'text' => wp_strip_all_tags($content),
                ];
            }
        }

        return false;
    }

    /**
     * Test a source URL
     */
    public function test_source($url) {
        if (empty($url)) {
            return [
                'success' => false,
                'message' => 'URL не указан',
            ];
        }

        $content = $this->fetch_feed($url);

        if (!$content) {
            return [
                'success' => false,
                'message' => 'Не удалось загрузить RSS ленту. Проверьте URL.',
            ];
        }

        // Try to parse
        $temp_source = [
            'id' => 'test',
            'lang' => 'de',
            'category' => 'test',
        ];

        $items = $this->parse_feed($content, $temp_source);

        if (empty($items)) {
            return [
                'success' => false,
                'message' => 'RSS лента пуста или неверный формат',
            ];
        }

        return [
            'success' => true,
            'message' => 'RSS лента валидна',
            'item_count' => count($items),
            'sample_item' => $items[0],
        ];
    }

    /**
     * Add a new source
     */
    public function add_source($data) {
        global $wpdb;

        // Validate required fields
        if (empty($data['name'])) {
            return [
                'success' => false,
                'message' => 'Название источника обязательно',
            ];
        }

        if (empty($data['url'])) {
            return [
                'success' => false,
                'message' => 'URL источника обязателен',
            ];
        }

        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'Некорректный URL',
            ];
        }

        $defaults = [
            'method' => 'rss',
            'lang' => 'de',
            'geo' => 'Deutschland',
            'category' => 'media',
            'trust_score' => 0.70,
            'fetch_interval' => 15,
            'enabled' => 1,
        ];

        $data = wp_parse_args($data, $defaults);

        // Generate ID from name
        if (empty($data['id'])) {
            $data['id'] = sanitize_title($data['name']);
        }

        // Check if source already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->db->table('sources')} WHERE id = %s OR url = %s",
                $data['id'],
                $data['url']
            )
        );

        if ($exists) {
            return [
                'success' => false,
                'message' => 'Источник с таким ID или URL уже существует',
            ];
        }

        // Test the source first
        $test = $this->test_source($data['url']);
        if (!$test['success']) {
            return [
                'success' => false,
                'message' => $test['message'],
            ];
        }

        // Insert into database
        $result = $wpdb->insert(
            $this->db->table('sources'),
            [
                'id' => $data['id'],
                'name' => sanitize_text_field($data['name']),
                'method' => $data['method'],
                'url' => esc_url_raw($data['url']),
                'lang' => sanitize_text_field($data['lang']),
                'geo' => sanitize_text_field($data['geo']),
                'category' => sanitize_text_field($data['category']),
                'trust_score' => floatval($data['trust_score']),
                'fetch_interval' => intval($data['fetch_interval']),
                'enabled' => intval($data['enabled']),
            ]
        );

        if ($result === false) {
            AINCC_Logger::error('Failed to insert source', [
                'data' => $data,
                'db_error' => $wpdb->last_error,
            ]);
            return [
                'success' => false,
                'message' => 'Ошибка сохранения в базу данных: ' . $wpdb->last_error,
            ];
        }

        AINCC_Logger::info('Source added', ['id' => $data['id'], 'name' => $data['name']]);

        return [
            'success' => true,
            'id' => $data['id'],
            'message' => 'Источник успешно добавлен',
        ];
    }

    /**
     * Update source
     */
    public function update_source($id, $data) {
        global $wpdb;

        $result = $wpdb->update(
            $this->db->table('sources'),
            $data,
            ['id' => $id]
        );

        return $result !== false;
    }

    /**
     * Delete source
     */
    public function delete_source($id) {
        global $wpdb;

        return $wpdb->delete(
            $this->db->table('sources'),
            ['id' => $id]
        );
    }

    /**
     * Get all sources
     */
    public function get_all_sources() {
        // Return ALL sources (not just active) for admin management
        return $this->db->get_all_sources();
    }
}
