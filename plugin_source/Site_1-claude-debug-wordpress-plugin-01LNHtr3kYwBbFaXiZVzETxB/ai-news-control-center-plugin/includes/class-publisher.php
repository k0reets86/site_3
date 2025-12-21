<?php
/**
 * Publisher
 * Handles publishing drafts to WordPress and social media
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Publisher {

    /**
     * Database instance
     */
    private $db;

    /**
     * Image handler instance
     */
    private $image_handler;

    /**
     * Telegram instance
     */
    private $telegram;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new AINCC_Database();
        $this->image_handler = new AINCC_Image_Handler();
        $this->telegram = new AINCC_Telegram();

        // Register cron hook
        add_action('aincc_auto_publish', [$this, 'process_auto_publish']);
    }

    /**
     * Publish a draft to WordPress
     */
    public function publish_to_wordpress($draft_id) {
        $draft = $this->db->get_draft($draft_id);

        if (!$draft) {
            return [
                'success' => false,
                'error' => 'Draft not found',
            ];
        }

        // CRITICAL: Prevent duplicate publishing
        // Check 1: Draft already has status 'published'
        if ($draft['status'] === 'published') {
            AINCC_Logger::warning('Duplicate publish attempt blocked - draft already published', [
                'draft_id' => $draft_id,
                'wp_post_id' => $draft['wp_post_id'] ?? null,
            ]);
            return [
                'success' => true,
                'post_id' => $draft['wp_post_id'],
                'url' => $draft['wp_post_id'] ? get_permalink($draft['wp_post_id']) : '',
                'message' => 'Статья уже была опубликована',
            ];
        }

        // Check 2: Use transient lock to prevent race condition (multiple rapid clicks)
        $lock_key = 'aincc_publish_lock_' . $draft_id;
        if (get_transient($lock_key)) {
            AINCC_Logger::warning('Duplicate publish attempt blocked - publish in progress', [
                'draft_id' => $draft_id,
            ]);
            return [
                'success' => false,
                'error' => 'Публикация уже выполняется. Подождите.',
            ];
        }
        // Set lock for 60 seconds
        set_transient($lock_key, time(), 60);

        // Check 3: Check if WordPress post already exists for this draft
        global $wpdb;
        $existing_post = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_aincc_draft_id' AND pm.meta_value = %s
             AND p.post_status IN ('publish', 'draft', 'pending')
             LIMIT 1",
            $draft_id
        ));

        if ($existing_post) {
            delete_transient($lock_key);
            AINCC_Logger::warning('Duplicate publish blocked - WP post already exists', [
                'draft_id' => $draft_id,
                'existing_post_id' => $existing_post,
            ]);

            // Update draft status to match reality
            $this->db->update_draft($draft_id, [
                'status' => 'published',
                'wp_post_id' => $existing_post,
            ]);

            return [
                'success' => true,
                'post_id' => $existing_post,
                'url' => get_permalink($existing_post),
                'message' => 'Статья уже была опубликована',
            ];
        }

        // Prepare post data
        $post_data = [
            'post_title' => $draft['title'],
            'post_content' => $this->prepare_content($draft),
            'post_excerpt' => $draft['lead'],
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => $draft['created_by'] ?: 1,
            'meta_input' => [
                '_aincc_draft_id' => $draft_id,
                '_aincc_lang' => $draft['lang'],
                '_aincc_sources' => $draft['sources'],
                // Yoast SEO
                '_yoast_wpseo_title' => $draft['seo_title'],
                '_yoast_wpseo_metadesc' => $draft['meta_description'],
                // Rank Math SEO
                'rank_math_title' => $draft['seo_title'],
                'rank_math_description' => $draft['meta_description'],
                'rank_math_focus_keyword' => $this->get_focus_keyword($draft),
                'rank_math_seo_score' => 80,
            ],
        ];

        // Set category
        if (!empty($draft['category'])) {
            $category = $this->get_or_create_category($draft['category'], $draft['lang']);
            if ($category) {
                $post_data['post_category'] = [$category];
            }
        }

        // Set slug
        if (!empty($draft['slug'])) {
            $post_data['post_name'] = $draft['slug'];
        }

        // Insert post
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            // Release lock on error
            delete_transient($lock_key);

            AINCC_Logger::error('Failed to create WordPress post', [
                'draft_id' => $draft_id,
                'error' => $post_id->get_error_message(),
            ]);

            return [
                'success' => false,
                'error' => $post_id->get_error_message(),
            ];
        }

        // Handle tags
        if (!empty($draft['tags'])) {
            $tags = json_decode($draft['tags'], true);
            if (is_array($tags)) {
                wp_set_post_tags($post_id, $tags, false);
            }
        }

        // Handle featured image
        $this->set_featured_image($post_id, $draft);

        // Add schema markup
        if (!empty($draft['schema_markup'])) {
            update_post_meta($post_id, '_aincc_schema', $draft['schema_markup']);
        }

        // Get post URL
        $post_url = get_permalink($post_id);

        // Update draft
        $this->db->update_draft($draft_id, [
            'status' => 'published',
            'wp_post_id' => $post_id,
            'published_at' => current_time('mysql'),
        ]);

        // Save to publishes table
        global $wpdb;
        $wpdb->insert(
            $this->db->table('publishes'),
            [
                'draft_id' => $draft_id,
                'wp_post_id' => $post_id,
                'lang' => $draft['lang'],
                'url' => $post_url,
            ]
        );

        AINCC_Logger::info('Published to WordPress', [
            'draft_id' => $draft_id,
            'post_id' => $post_id,
            'url' => $post_url,
        ]);

        // Release the publish lock
        delete_transient($lock_key);

        return [
            'success' => true,
            'post_id' => $post_id,
            'url' => $post_url,
        ];
    }

    /**
     * Prepare content HTML
     */
    private function prepare_content($draft) {
        $content = $draft['body_html'];

        // Add sources at the end
        if (!empty($draft['sources'])) {
            $sources = json_decode($draft['sources'], true);
            if (!empty($sources) && is_array($sources)) {
                $content .= "\n\n<div class=\"aincc-sources\">\n";
                $content .= "<h4>" . $this->get_label('sources', $draft['lang']) . "</h4>\n";
                $content .= "<ul>\n";

                foreach ($sources as $source) {
                    $name = esc_html($source['name'] ?? '');
                    $url = esc_url($source['url'] ?? '');
                    $date = esc_html($source['date'] ?? '');

                    if ($url) {
                        $content .= "<li><a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">{$name}</a>";
                    } else {
                        $content .= "<li>{$name}";
                    }

                    if ($date) {
                        $content .= " ({$date})";
                    }

                    $content .= "</li>\n";
                }

                $content .= "</ul>\n</div>";
            }
        }

        // Add image credit if available
        if (!empty($draft['image_author']) || !empty($draft['image_license'])) {
            $credit_parts = [];

            if (!empty($draft['image_author'])) {
                $credit_parts[] = "Foto: " . esc_html($draft['image_author']);
            }

            if (!empty($draft['image_license'])) {
                $credit_parts[] = esc_html($draft['image_license']);
            }

            $content .= "\n<p class=\"aincc-image-credit\"><small>" . implode(' / ', $credit_parts) . "</small></p>";
        }

        return $content;
    }

    /**
     * Get focus keyword from draft keywords
     */
    private function get_focus_keyword($draft) {
        $keywords = json_decode($draft['keywords'] ?? '[]', true);
        if (is_array($keywords) && !empty($keywords)) {
            // Return first keyword as focus keyword
            return is_string($keywords[0]) ? $keywords[0] : '';
        }
        // Fallback: extract from title
        $title_words = explode(' ', $draft['title'] ?? '');
        $filtered = array_filter($title_words, function($w) {
            return strlen($w) > 4;
        });
        return !empty($filtered) ? reset($filtered) : '';
    }

    /**
     * Get localized label
     */
    private function get_label($key, $lang) {
        $labels = [
            'sources' => [
                'de' => 'Quellen',
                'ua' => 'Джерела',
                'ru' => 'Источники',
                'en' => 'Sources',
            ],
            'read_more' => [
                'de' => 'Weiterlesen',
                'ua' => 'Читати далі',
                'ru' => 'Читать далее',
                'en' => 'Read more',
            ],
        ];

        return $labels[$key][$lang] ?? $labels[$key]['de'] ?? $key;
    }

    /**
     * Get or create category
     */
    private function get_or_create_category($category_key, $lang) {
        $categories = AINCC_Settings::get_categories();

        if (!isset($categories[$category_key])) {
            return null;
        }

        $cat_config = $categories[$category_key];
        $cat_name = $cat_config["name_{$lang}"] ?? $cat_config['name_de'];

        // Check if category exists
        $term = get_term_by('name', $cat_name, 'category');

        if ($term) {
            return $term->term_id;
        }

        // Create category
        $result = wp_insert_term($cat_name, 'category', [
            'slug' => $category_key . ($lang !== 'de' ? '-' . $lang : ''),
        ]);

        if (is_wp_error($result)) {
            return null;
        }

        return $result['term_id'];
    }

    /**
     * Set featured image for post
     */
    private function set_featured_image($post_id, $draft) {
        // Check if draft already has image in media library
        if (!empty($draft['image_local_id'])) {
            $attachment_exists = get_post($draft['image_local_id']);
            if ($attachment_exists) {
                set_post_thumbnail($post_id, $draft['image_local_id']);
                AINCC_Logger::debug('Using existing media library image', ['attachment_id' => $draft['image_local_id']]);
                return true;
            }
        }

        // If has image URL, download and attach
        if (!empty($draft['image_url'])) {
            $image_data = [
                'url' => $draft['image_url'],
                'alt' => $draft['image_alt'] ?? $draft['title'],
                'author' => $draft['image_author'] ?? '',
                'license' => $draft['image_license'] ?? '',
            ];

            AINCC_Logger::debug('Downloading image from URL', ['url' => $draft['image_url']]);
            $attachment_id = $this->image_handler->save_to_media_library($image_data, $post_id);

            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);

                // Update draft with local image ID
                $this->db->update_draft($draft['id'], [
                    'image_local_id' => $attachment_id,
                ]);

                AINCC_Logger::info('Image downloaded and attached', ['attachment_id' => $attachment_id]);
                return true;
            } else {
                AINCC_Logger::warning('Failed to download image from URL', ['url' => $draft['image_url']]);
            }
        }

        // No image available - try to find one from Pexels
        $keywords = json_decode($draft['keywords'] ?? '[]', true) ?: [];

        // If no keywords, extract from title
        if (empty($keywords)) {
            $title_words = explode(' ', $draft['title']);
            $keywords = array_filter($title_words, function($w) {
                return strlen($w) > 4;
            });
            $keywords = array_slice(array_values($keywords), 0, 5);
        }

        AINCC_Logger::debug('Searching for image', ['keywords' => $keywords, 'title' => $draft['title'], 'category' => $draft['category']]);
        $image_data = $this->image_handler->find_image($keywords, $draft['title'], $draft['category']);

        if ($image_data) {
            $attachment_id = $this->image_handler->save_to_media_library($image_data, $post_id);

            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
                AINCC_Logger::info('Auto-found image attached', ['attachment_id' => $attachment_id, 'source' => $image_data['source'] ?? 'unknown']);
                return true;
            }
        }

        AINCC_Logger::warning('No image could be attached to post', ['post_id' => $post_id, 'draft_id' => $draft['id']]);
        return false;
    }

    /**
     * Publish to all channels
     */
    public function publish_all($draft_id, $channels = ['wordpress', 'telegram']) {
        $results = [];

        // Publish to WordPress first
        if (in_array('wordpress', $channels)) {
            $wp_result = $this->publish_to_wordpress($draft_id);
            $results['wordpress'] = $wp_result;

            if (!$wp_result['success']) {
                return [
                    'success' => false,
                    'error' => 'WordPress publish failed',
                    'results' => $results,
                ];
            }
        }

        // Get updated draft with WP URL
        $draft = $this->db->get_draft($draft_id);
        $wp_url = $results['wordpress']['url'] ?? '';

        // Publish to Telegram
        if (in_array('telegram', $channels) && AINCC_Settings::get('telegram_enabled')) {
            $tg_result = $this->telegram->post_article($draft, $wp_url);
            $results['telegram'] = $tg_result;
        }

        // TODO: Facebook integration
        if (in_array('facebook', $channels) && AINCC_Settings::get('facebook_enabled')) {
            $results['facebook'] = [
                'success' => false,
                'error' => 'Facebook integration not yet implemented',
            ];
        }

        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * Process auto-publish queue
     */
    public function process_auto_publish() {
        if (!AINCC_Settings::get('auto_publish_enabled')) {
            return;
        }

        global $wpdb;

        // Get drafts ready for auto-publish
        $delay_minutes = AINCC_Settings::get('auto_publish_delay', 10);

        $drafts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->db->table('drafts')}
                 WHERE status = 'auto_ready'
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)
                 AND lang = %s
                 ORDER BY created_at ASC
                 LIMIT 5",
                $delay_minutes,
                AINCC_Settings::get('default_language', 'de')
            ),
            ARRAY_A
        );

        foreach ($drafts as $draft) {
            try {
                $channels = ['wordpress'];

                if (AINCC_Settings::get('telegram_enabled')) {
                    $channels[] = 'telegram';
                }

                $result = $this->publish_all($draft['id'], $channels);

                AINCC_Logger::info('Auto-published draft', [
                    'draft_id' => $draft['id'],
                    'success' => $result['success'],
                ]);

            } catch (Exception $e) {
                AINCC_Logger::error('Auto-publish failed', [
                    'draft_id' => $draft['id'],
                    'error' => $e->getMessage(),
                ]);

                // Mark as failed
                $this->db->update_draft($draft['id'], [
                    'status' => 'publish_failed',
                    'gate_reason' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Schedule a draft for publication
     */
    public function schedule($draft_id, $scheduled_at, $channels = ['wordpress', 'telegram']) {
        $this->db->update_draft($draft_id, [
            'status' => 'scheduled',
            'scheduled_at' => $scheduled_at,
        ]);

        // Store channels in meta
        global $wpdb;
        $wpdb->update(
            $this->db->table('drafts'),
            ['risk_flags' => json_encode(['scheduled_channels' => $channels])],
            ['id' => $draft_id]
        );

        return [
            'success' => true,
            'scheduled_at' => $scheduled_at,
        ];
    }

    /**
     * Process scheduled posts
     */
    public function process_scheduled() {
        global $wpdb;

        $drafts = $wpdb->get_results(
            "SELECT * FROM {$this->db->table('drafts')}
             WHERE status = 'scheduled'
             AND scheduled_at <= NOW()
             ORDER BY scheduled_at ASC
             LIMIT 5",
            ARRAY_A
        );

        foreach ($drafts as $draft) {
            // Get scheduled channels
            $risk_flags = json_decode($draft['risk_flags'], true);
            $channels = $risk_flags['scheduled_channels'] ?? ['wordpress', 'telegram'];

            $result = $this->publish_all($draft['id'], $channels);

            AINCC_Logger::info('Published scheduled draft', [
                'draft_id' => $draft['id'],
                'success' => $result['success'],
            ]);
        }
    }

    /**
     * Unpublish a draft
     */
    public function unpublish($draft_id) {
        $draft = $this->db->get_draft($draft_id);

        if (!$draft) {
            return ['success' => false, 'error' => 'Draft not found'];
        }

        // If has WordPress post, trash it
        if (!empty($draft['wp_post_id'])) {
            wp_trash_post($draft['wp_post_id']);
        }

        // Update draft status
        $this->db->update_draft($draft_id, [
            'status' => 'unpublished',
        ]);

        return ['success' => true];
    }

    /**
     * Approve a draft for publication
     */
    public function approve($draft_id) {
        $this->db->update_draft($draft_id, [
            'status' => 'auto_ready',
            'gate_reason' => null,
        ]);

        return ['success' => true];
    }

    /**
     * Reject a draft
     */
    public function reject($draft_id, $reason = '') {
        $this->db->update_draft($draft_id, [
            'status' => 'rejected',
            'gate_reason' => $reason ?: 'Rejected by editor',
        ]);

        return ['success' => true];
    }
}
