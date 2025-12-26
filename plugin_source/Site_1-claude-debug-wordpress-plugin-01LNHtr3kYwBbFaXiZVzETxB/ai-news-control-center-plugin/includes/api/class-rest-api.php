<?php
/**
 * REST API
 * Provides endpoints for the React admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_REST_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'aincc/v1';

    /**
     * Constructor - routes are registered via init_rest_api in main plugin
     */
    public function __construct() {
        // Ensure all required classes are loaded
        $this->load_dependencies();
    }

    /**
     * Load required dependencies for API operations
     */
    private function load_dependencies() {
        $plugin_dir = AINCC_PLUGIN_DIR;

        // Load AI providers
        if (!interface_exists('AINCC_AI_Provider_Interface')) {
            require_once $plugin_dir . 'includes/ai-providers/interface-ai-provider.php';
        }
        if (!class_exists('AINCC_AI_Provider_Factory')) {
            require_once $plugin_dir . 'includes/ai-providers/class-ai-provider-factory.php';
        }
        if (!class_exists('AINCC_DeepSeek_Provider')) {
            require_once $plugin_dir . 'includes/ai-providers/class-deepseek-provider.php';
        }
        if (!class_exists('AINCC_OpenAI_Provider')) {
            require_once $plugin_dir . 'includes/ai-providers/class-openai-provider.php';
        }
        if (!class_exists('AINCC_Anthropic_Provider')) {
            require_once $plugin_dir . 'includes/ai-providers/class-anthropic-provider.php';
        }

        // Load other required classes
        if (!class_exists('AINCC_RSS_Parser')) {
            require_once $plugin_dir . 'includes/class-rss-parser.php';
        }
        if (!class_exists('AINCC_Content_Processor')) {
            require_once $plugin_dir . 'includes/class-content-processor.php';
        }
        if (!class_exists('AINCC_Image_Handler')) {
            require_once $plugin_dir . 'includes/class-image-handler.php';
        }
        if (!class_exists('AINCC_Publisher')) {
            require_once $plugin_dir . 'includes/class-publisher.php';
        }
        if (!class_exists('AINCC_Telegram')) {
            require_once $plugin_dir . 'includes/class-telegram.php';
        }
        if (!class_exists('AINCC_Scheduler')) {
            require_once $plugin_dir . 'includes/class-scheduler.php';
        }

        // Auto-initialize database if needed
        $this->ensure_database_initialized();
    }

    /**
     * Ensure database tables exist and have sources
     */
    private function ensure_database_initialized() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aincc_sources';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

        if (!$table_exists) {
            // Tables don't exist - create them
            $db = new AINCC_Database();
            $db->create_tables();
            AINCC_Logger::info('Database auto-initialized via REST API');
        } else {
            // Table exists but check if sources are empty
            $source_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            if ($source_count == 0) {
                // Load default sources
                $db = new AINCC_Database();
                $db->load_default_sources();
                AINCC_Logger::info('Default sources loaded via REST API');
            }
        }
    }

    /**
     * Register all REST routes
     */
    public function register_routes() {
        // Dashboard/Queue
        register_rest_route(self::NAMESPACE, '/drafts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_drafts'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'status' => ['type' => 'string', 'default' => 'pending_ok,auto_ready'],
                'lang' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'page' => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 20],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_draft'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_draft'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_draft'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Draft actions
        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_draft'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_draft'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'reason' => ['type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'publish_draft'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'channels' => ['type' => 'array', 'default' => ['wordpress', 'telegram']],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/schedule', [
            'methods' => 'POST',
            'callback' => [$this, 'schedule_draft'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'scheduled_at' => ['type' => 'string', 'required' => true],
                'channels' => ['type' => 'array', 'default' => ['wordpress', 'telegram']],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/regenerate', [
            'methods' => 'POST',
            'callback' => [$this, 'regenerate_draft'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'what' => ['type' => 'string', 'default' => 'all'],
                'instructions' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/image', [
            'methods' => 'POST',
            'callback' => [$this, 'assign_image'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'query' => ['type' => 'string'],
            ],
        ]);

        // Create manual article
        register_rest_route(self::NAMESPACE, '/articles/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_article'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Create article from URL
        register_rest_route(self::NAMESPACE, '/articles/from-url', [
            'methods' => 'POST',
            'callback' => [$this, 'create_article_from_url'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'url' => ['type' => 'string', 'required' => true],
                'source_lang' => ['type' => 'string', 'default' => 'de'],
                'target_langs' => ['type' => 'array', 'default' => ['de', 'ua', 'ru', 'en']],
                'category' => ['type' => 'string', 'default' => 'nachrichten'],
            ],
        ]);

        // Manual fetch trigger
        register_rest_route(self::NAMESPACE, '/fetch/now', [
            'methods' => 'POST',
            'callback' => [$this, 'trigger_fetch'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Sources
        register_rest_route(self::NAMESPACE, '/sources', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_sources'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_source'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/sources/(?P<id>[a-zA-Z0-9_]+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_source'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_source'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/sources/test', [
            'methods' => 'POST',
            'callback' => [$this, 'test_source'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'url' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // Settings
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);

        // Analytics
        register_rest_route(self::NAMESPACE, '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'period' => ['type' => 'string', 'default' => '7d'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/analytics/top-articles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_top_articles'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'period' => ['type' => 'string', 'default' => '7d'],
                'limit' => ['type' => 'integer', 'default' => 10],
            ],
        ]);

        // System
        register_rest_route(self::NAMESPACE, '/system/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_system_status'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/system/cron', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cron_status'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/system/cron/trigger', [
            'methods' => 'POST',
            'callback' => [$this, 'trigger_cron'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'hook' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/system/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'level' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'default' => 100],
            ],
        ]);

        // Test connections
        register_rest_route(self::NAMESPACE, '/test/ai', [
            'methods' => 'POST',
            'callback' => [$this, 'test_ai_connection'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/test/telegram', [
            'methods' => 'POST',
            'callback' => [$this, 'test_telegram_connection'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/test/pexels', [
            'methods' => 'POST',
            'callback' => [$this, 'test_pexels_connection'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // System reinitialize
        register_rest_route(self::NAMESPACE, '/system/reinitialize', [
            'methods' => 'POST',
            'callback' => [$this, 'reinitialize_plugin'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Image search
        register_rest_route(self::NAMESPACE, '/images/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_images'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'query' => ['type' => 'string', 'required' => true],
                'per_page' => ['type' => 'integer', 'default' => 10],
            ],
        ]);

        // AI check text
        register_rest_route(self::NAMESPACE, '/ai/check-text', [
            'methods' => 'POST',
            'callback' => [$this, 'check_text_with_ai'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'text' => ['type' => 'string', 'required' => true],
                'lang' => ['type' => 'string', 'default' => 'de'],
            ],
        ]);

        // Toggle source enabled status
        register_rest_route(self::NAMESPACE, '/sources/(?P<id>[a-zA-Z0-9_-]+)/toggle', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_source'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Clear rejected/processed items from queue
        register_rest_route(self::NAMESPACE, '/queue/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_queue'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'status' => ['type' => 'string', 'default' => 'rejected'],
            ],
        ]);

        // Delete multiple drafts
        register_rest_route(self::NAMESPACE, '/drafts/bulk-delete', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_delete_drafts'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Get/Set AI prompts settings
        register_rest_route(self::NAMESPACE, '/settings/prompts', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_prompts'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_prompts'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);

        // Get/Set cron intervals
        register_rest_route(self::NAMESPACE, '/settings/cron', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_cron_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_cron_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);

        // Reschedule all cron jobs
        register_rest_route(self::NAMESPACE, '/system/cron/reschedule', [
            'methods' => 'POST',
            'callback' => [$this, 'reschedule_cron'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Get queue statistics
        register_rest_route(self::NAMESPACE, '/queue/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_queue_stats'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // AI rewrite endpoint with custom instructions
        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/ai-rewrite', [
            'methods' => 'POST',
            'callback' => [$this, 'ai_rewrite_draft'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'instructions' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // Upload media for draft
        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/upload-image', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_draft_image'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Set image from URL for draft
        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/set-image', [
            'methods' => 'POST',
            'callback' => [$this, 'set_draft_image'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'image_url' => ['type' => 'string', 'required' => true],
                'alt' => ['type' => 'string', 'default' => ''],
                'author' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        // Get WordPress categories (from actual WP categories)
        register_rest_route(self::NAMESPACE, '/wordpress/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_wordpress_categories'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Get WordPress pages (for special language pages)
        register_rest_route(self::NAMESPACE, '/wordpress/pages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_wordpress_pages'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Clear all queue items (different statuses)
        register_rest_route(self::NAMESPACE, '/queue/clear-all', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_all_queue'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'types' => ['type' => 'array', 'default' => ['rejected', 'failed']],
            ],
        ]);

        // Extract image from article URL
        register_rest_route(self::NAMESPACE, '/images/extract', [
            'methods' => 'POST',
            'callback' => [$this, 'extract_image_from_url'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'url' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // PUBLIC cron endpoint - uses secret key authentication
        // Can be called by server cron: curl https://site.com/wp-json/aincc/v1/cron?secret=KEY
        register_rest_route(self::NAMESPACE, '/cron', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_public_cron'],
            'permission_callback' => '__return_true', // Public, uses secret
            'args' => [
                'secret' => ['type' => 'string', 'required' => true],
                'action' => ['type' => 'string', 'default' => 'all'],
            ],
        ]);

        // Get cron setup info (including secret key)
        register_rest_route(self::NAMESPACE, '/system/cron-setup', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cron_setup_info'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Regenerate cron secret
        register_rest_route(self::NAMESPACE, '/system/cron-secret/regenerate', [
            'methods' => 'POST',
            'callback' => [$this, 'regenerate_cron_secret'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    /**
     * Check if user has editor permission
     */
    public function check_permission() {
        return current_user_can('edit_posts');
    }

    /**
     * Check if user has admin permission
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Get drafts list
     */
    public function get_drafts($request) {
        $db = new AINCC_Database();

        $statuses = explode(',', $request->get_param('status'));
        $lang = $request->get_param('lang');
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $offset = ($page - 1) * $per_page;

        $drafts = $db->get_drafts_by_status($statuses, $lang, $per_page, $offset);
        $total = $db->count_drafts_by_status($statuses);

        // Format for API response
        $items = array_map(function ($draft) {
            return $this->format_draft($draft);
        }, $drafts);

        return new WP_REST_Response([
            'items' => $items,
            'total' => (int) $total,
            'page' => (int) $page,
            'per_page' => (int) $per_page,
            'total_pages' => ceil($total / $per_page),
        ], 200);
    }

    /**
     * Get single draft
     */
    public function get_draft($request) {
        $db = new AINCC_Database();
        $draft = $db->get_draft($request->get_param('id'));

        if (!$draft) {
            return new WP_Error('not_found', 'Draft not found', ['status' => 404]);
        }

        return new WP_REST_Response($this->format_draft($draft, true), 200);
    }

    /**
     * Update draft
     */
    public function update_draft($request) {
        $db = new AINCC_Database();
        $id = $request->get_param('id');
        $data = $request->get_json_params();

        // Filter allowed fields
        $allowed = ['title', 'lead', 'body_html', 'seo_title', 'meta_description',
            'slug', 'category', 'tags', 'image_url', 'image_alt'];

        $update_data = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['tags'])) {
                    $update_data[$field] = json_encode($data[$field]);
                } else {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                }
            }
        }

        // Allow HTML in body
        if (isset($data['body_html'])) {
            $update_data['body_html'] = wp_kses_post($data['body_html']);
        }

        $update_data['edited_by'] = get_current_user_id();

        $db->update_draft($id, $update_data);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Delete draft
     */
    public function delete_draft($request) {
        global $wpdb;
        $db = new AINCC_Database();

        $wpdb->delete($db->table('drafts'), ['id' => $request->get_param('id')]);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Approve draft
     */
    public function approve_draft($request) {
        $publisher = new AINCC_Publisher();
        $result = $publisher->approve($request->get_param('id'));

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Reject draft
     */
    public function reject_draft($request) {
        $publisher = new AINCC_Publisher();
        $reason = $request->get_param('reason') ?: 'Rejected by editor';
        $result = $publisher->reject($request->get_param('id'), $reason);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Publish draft
     */
    public function publish_draft($request) {
        $publisher = new AINCC_Publisher();
        $channels = $request->get_param('channels');

        $result = $publisher->publish_all($request->get_param('id'), $channels);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Schedule draft
     */
    public function schedule_draft($request) {
        $publisher = new AINCC_Publisher();

        $scheduled_at = $request->get_param('scheduled_at');
        $channels = $request->get_param('channels');

        $result = $publisher->schedule($request->get_param('id'), $scheduled_at, $channels);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Regenerate draft content
     */
    public function regenerate_draft($request) {
        $processor = new AINCC_Content_Processor();

        $result = $processor->regenerate_draft(
            $request->get_param('id'),
            $request->get_param('what'),
            $request->get_param('instructions')
        );

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Assign/find image for draft
     */
    public function assign_image($request) {
        $image_handler = new AINCC_Image_Handler();
        $query = $request->get_param('query');

        if ($query) {
            $image = $image_handler->search_pexels($query);

            if ($image) {
                $db = new AINCC_Database();
                $db->update_draft($request->get_param('id'), [
                    'image_url' => $image['url'],
                    'image_author' => $image['author'],
                    'image_license' => $image['license'],
                    'image_alt' => $image['alt'],
                ]);

                return new WP_REST_Response(['success' => true, 'image' => $image], 200);
            }

            return new WP_REST_Response(['success' => false, 'error' => 'No images found'], 404);
        }

        $result = $image_handler->assign_to_draft($request->get_param('id'));
        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Create manual article
     */
    public function create_article($request) {
        $processor = new AINCC_Content_Processor();
        $data = $request->get_json_params();

        // Validate required fields
        if (empty($data['title']) || empty($data['body'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Заголовок и текст обязательны',
            ], 400);
        }

        try {
            $result = $processor->process_manual_article($data);
            return new WP_REST_Response($result, $result['success'] ? 201 : 400);
        } catch (Exception $e) {
            AINCC_Logger::error('Article creation failed', ['error' => $e->getMessage()]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка создания: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create article from URL
     */
    public function create_article_from_url($request) {
        $url = $request->get_param('url');
        $source_lang = $request->get_param('source_lang') ?: 'de';
        $target_langs = $request->get_param('target_langs') ?: ['de', 'ua', 'ru', 'en'];
        $category = $request->get_param('category') ?: 'nachrichten';

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Некорректный URL',
            ], 400);
        }

        try {
            $processor = new AINCC_Content_Processor();
            $result = $processor->process_article_from_url($url, $source_lang, $target_langs, $category);

            return new WP_REST_Response($result, $result['success'] ? 201 : 400);
        } catch (Exception $e) {
            AINCC_Logger::error('URL article creation failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка обработки URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger manual fetch
     */
    public function trigger_fetch($request) {
        try {
            $parser = new AINCC_RSS_Parser();
            $parser->fetch_all_sources();

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Сбор новостей запущен',
            ], 200);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sources
     */
    public function get_sources($request) {
        $parser = new AINCC_RSS_Parser();
        $sources = $parser->get_all_sources();

        // Ensure proper types for frontend (MySQL returns strings)
        $sources = array_map(function($source) {
            $source['enabled'] = (int) $source['enabled'];
            $source['trust_score'] = (float) $source['trust_score'];
            $source['fetch_interval'] = (int) $source['fetch_interval'];
            $source['error_count'] = (int) ($source['error_count'] ?? 0);
            return $source;
        }, $sources);

        return new WP_REST_Response(['items' => $sources], 200);
    }

    /**
     * Create source
     */
    public function create_source($request) {
        $parser = new AINCC_RSS_Parser();
        $data = $request->get_json_params();

        $result = $parser->add_source($data);

        return new WP_REST_Response($result, $result['success'] ? 201 : 400);
    }

    /**
     * Update source
     */
    public function update_source($request) {
        $parser = new AINCC_RSS_Parser();
        $data = $request->get_json_params();

        $result = $parser->update_source($request->get_param('id'), $data);

        return new WP_REST_Response(['success' => $result], $result ? 200 : 400);
    }

    /**
     * Delete source
     */
    public function delete_source($request) {
        $parser = new AINCC_RSS_Parser();
        $result = $parser->delete_source($request->get_param('id'));

        return new WP_REST_Response(['success' => (bool) $result], $result ? 200 : 400);
    }

    /**
     * Test source URL
     */
    public function test_source($request) {
        $parser = new AINCC_RSS_Parser();
        $result = $parser->test_source($request->get_param('url'));

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get settings
     */
    public function get_settings($request) {
        $settings = AINCC_Settings::get_all();

        // Hide API keys
        $hidden_keys = ['deepseek_api_key', 'openai_api_key', 'anthropic_api_key',
            'pexels_api_key', 'telegram_bot_token', 'facebook_access_token'];

        foreach ($hidden_keys as $key) {
            if (!empty($settings[$key])) {
                $settings[$key] = '***' . substr($settings[$key], -4);
            }
        }

        return new WP_REST_Response($settings, 200);
    }

    /**
     * Update settings
     */
    public function update_settings($request) {
        $data = $request->get_json_params();

        // Don't overwrite keys if they're masked
        $key_fields = ['deepseek_api_key', 'openai_api_key', 'anthropic_api_key',
            'pexels_api_key', 'telegram_bot_token', 'facebook_access_token'];

        foreach ($key_fields as $field) {
            if (isset($data[$field]) && strpos($data[$field], '***') === 0) {
                unset($data[$field]);
            }
        }

        AINCC_Settings::update_all($data);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Get analytics
     */
    public function get_analytics($request) {
        $db = new AINCC_Database();
        global $wpdb;

        $period = $request->get_param('period');
        $days = $this->period_to_days($period);

        // Get counts
        $stats = [
            'published' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$db->table('drafts')}
                     WHERE status = 'published' AND published_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                )
            ),
            'pending' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$db->table('drafts')} WHERE status = 'pending_ok'"
            ),
            'auto_ready' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$db->table('drafts')} WHERE status = 'auto_ready'"
            ),
            'rejected' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$db->table('drafts')}
                     WHERE status = 'rejected' AND updated_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                )
            ),
        ];

        // Get category distribution
        $categories = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT category, COUNT(*) as count FROM {$db->table('drafts')}
                 WHERE status = 'published' AND published_at > DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY category ORDER BY count DESC",
                $days
            ),
            ARRAY_A
        );

        // Get source performance
        $sources = $wpdb->get_results(
            "SELECT s.name, s.trust_score, COUNT(d.id) as article_count
             FROM {$db->table('sources')} s
             LEFT JOIN {$db->table('raw_items')} ri ON s.id = ri.source_id
             LEFT JOIN {$db->table('drafts')} d ON ri.id = d.raw_item_id
             WHERE d.status = 'published'
             GROUP BY s.id ORDER BY article_count DESC LIMIT 10",
            ARRAY_A
        );

        // Daily published count for chart
        $daily = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(published_at) as date, COUNT(*) as count
                 FROM {$db->table('drafts')}
                 WHERE status = 'published' AND published_at > DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY DATE(published_at) ORDER BY date ASC",
                $days
            ),
            ARRAY_A
        );

        return new WP_REST_Response([
            'stats' => $stats,
            'categories' => $categories,
            'sources' => $sources,
            'daily' => $daily,
            'period' => $period,
        ], 200);
    }

    /**
     * Get top articles
     */
    public function get_top_articles($request) {
        $db = new AINCC_Database();
        global $wpdb;

        $days = $this->period_to_days($request->get_param('period'));
        $limit = $request->get_param('limit');

        $articles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT d.title, d.wp_post_id, d.published_at, d.category
                 FROM {$db->table('drafts')} d
                 WHERE d.status = 'published'
                 AND d.published_at > DATE_SUB(NOW(), INTERVAL %d DAY)
                 AND d.lang = 'de'
                 ORDER BY d.published_at DESC
                 LIMIT %d",
                $days,
                $limit
            ),
            ARRAY_A
        );

        // Add post URLs
        foreach ($articles as &$article) {
            if ($article['wp_post_id']) {
                $article['url'] = get_permalink($article['wp_post_id']);
            }
        }

        return new WP_REST_Response(['items' => $articles], 200);
    }

    /**
     * Get system status
     */
    public function get_system_status($request) {
        $db = new AINCC_Database();

        // API key validation
        $api_errors = AINCC_Settings::validate_api_keys();

        // Database tables check
        global $wpdb;
        $tables = ['sources', 'raw_items', 'events', 'drafts', 'fact_checks',
            'publishes', 'social_posts', 'queue', 'logs'];

        $table_status = [];
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$db->table($table)}'");
            $table_status[$table] = (bool) $exists;
        }

        // Queue status
        $queue_pending = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$db->table('queue')} WHERE status = 'pending'"
        );
        $queue_failed = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$db->table('queue')} WHERE status = 'failed'"
        );

        return new WP_REST_Response([
            'version' => AINCC_VERSION,
            'db_version' => get_option('aincc_db_version'),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'api_errors' => $api_errors,
            'tables' => $table_status,
            'queue' => [
                'pending' => (int) $queue_pending,
                'failed' => (int) $queue_failed,
            ],
            'ai_provider' => AINCC_Settings::get('ai_provider'),
        ], 200);
    }

    /**
     * Get cron status
     */
    public function get_cron_status($request) {
        $scheduler = new AINCC_Scheduler();
        return new WP_REST_Response($scheduler->get_cron_status(), 200);
    }

    /**
     * Trigger cron manually
     */
    public function trigger_cron($request) {
        $scheduler = new AINCC_Scheduler();
        $result = $scheduler->trigger_cron($request->get_param('hook'));

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get logs
     */
    public function get_logs($request) {
        $db = new AINCC_Database();
        $logs = $db->get_logs(
            $request->get_param('level'),
            $request->get_param('limit')
        );

        return new WP_REST_Response(['items' => $logs], 200);
    }

    /**
     * Test AI connection
     */
    public function test_ai_connection($request) {
        try {
            $provider = AINCC_Settings::get('ai_provider', 'deepseek');
            $api_key = AINCC_Settings::get($provider . '_api_key');

            if (empty($api_key)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => "API ключ для {$provider} не указан. Введите ключ в настройках.",
                ], 400);
            }

            $ai = AINCC_AI_Provider_Factory::create();
            $result = $ai->test_connection();

            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        } catch (Exception $e) {
            AINCC_Logger::error('AI connection test failed', ['error' => $e->getMessage()]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Telegram connection
     */
    public function test_telegram_connection($request) {
        try {
            $bot_token = AINCC_Settings::get('telegram_bot_token');

            if (empty($bot_token)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Telegram Bot Token не указан. Введите токен в настройках.',
                ], 400);
            }

            $telegram = new AINCC_Telegram();
            $result = $telegram->test_connection();

            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Pexels connection
     */
    public function test_pexels_connection($request) {
        try {
            $api_key = AINCC_Settings::get('pexels_api_key');

            if (empty($api_key)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Pexels API ключ не указан. Введите ключ в настройках.',
                ], 400);
            }

            $image_handler = new AINCC_Image_Handler();
            $result = $image_handler->search_pexels('nature');

            if ($result) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Pexels API подключен успешно!',
                    'sample_image' => $result['url'] ?? null,
                ], 200);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Не удалось подключиться к Pexels. Проверьте API ключ.',
            ], 400);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reinitialize plugin (create tables, load sources)
     */
    public function reinitialize_plugin($request) {
        try {
            // Create database tables
            $db = new AINCC_Database();
            $db->create_tables();

            // Schedule cron jobs
            if (!wp_next_scheduled('aincc_fetch_sources')) {
                wp_schedule_event(time() + 60, 'every_5_minutes', 'aincc_fetch_sources');
            }
            if (!wp_next_scheduled('aincc_process_queue')) {
                wp_schedule_event(time() + 120, 'every_2_minutes', 'aincc_process_queue');
            }

            // Get source count
            global $wpdb;
            $source_count = $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('sources')}");

            AINCC_Logger::info('Plugin reinitialized', ['sources' => $source_count]);

            return new WP_REST_Response([
                'success' => true,
                'message' => "Плагин переинициализирован. Загружено источников: {$source_count}",
                'sources_count' => (int) $source_count,
            ], 200);
        } catch (Exception $e) {
            AINCC_Logger::error('Reinitialize failed', ['error' => $e->getMessage()]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search images
     */
    public function search_images($request) {
        // Check if Pexels API key is configured
        $api_key = AINCC_Settings::get('pexels_api_key');
        if (empty($api_key)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Pexels API ключ не настроен. Проверьте настройки плагина.',
            ], 400);
        }

        $query = $request->get_param('query');
        if (empty($query)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Введите поисковый запрос',
            ], 400);
        }

        $url = add_query_arg([
            'query' => urlencode($query),
            'per_page' => $request->get_param('per_page') ?: 8,
            'orientation' => 'landscape',
        ], 'https://api.pexels.com/v1/search');

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => $api_key],
        ]);

        if (is_wp_error($response)) {
            AINCC_Logger::error('Pexels API error', ['error' => $response->get_error_message()]);
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Ошибка подключения к Pexels: ' . $response->get_error_message(),
            ], 400);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            AINCC_Logger::error('Pexels API HTTP error', ['status' => $status_code]);
            if ($status_code === 401) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Неверный Pexels API ключ. Проверьте настройки.',
                ], 400);
            }
            if ($status_code === 429) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Превышен лимит запросов к Pexels. Попробуйте позже.',
                ], 429);
            }
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Ошибка Pexels API (код: ' . $status_code . ')',
            ], 400);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        $images = [];
        if (!empty($body['photos'])) {
            foreach ($body['photos'] as $photo) {
                $images[] = [
                    'id' => $photo['id'],
                    'url' => $photo['src']['large'],
                    'url_small' => $photo['src']['medium'],
                    'author' => $photo['photographer'],
                    'source' => 'Pexels',
                    'license' => 'Pexels License',
                    'alt' => $photo['alt'] ?? '',
                ];
            }
        }

        if (empty($images)) {
            return new WP_REST_Response([
                'success' => true,
                'items' => [],
                'message' => 'По запросу "' . $query . '" ничего не найдено',
            ], 200);
        }

        return new WP_REST_Response(['success' => true, 'items' => $images], 200);
    }

    /**
     * Format draft for API response
     */
    private function format_draft($draft, $full = false) {
        $formatted = [
            'id' => $draft['id'],
            'lang' => $draft['lang'],
            'status' => $draft['status'],
            'title' => $draft['title'],
            'category' => $draft['category'],
            'source_name' => $draft['source_name'] ?? null,
            'source_trust' => $draft['source_trust'] ?? null,
            'created_at' => $draft['created_at'],
            'updated_at' => $draft['updated_at'],
            'risk_flags' => json_decode($draft['risk_flags'] ?? '[]', true),
        ];

        if ($full) {
            $formatted = array_merge($formatted, [
                'lead' => $draft['lead'],
                'body_html' => $draft['body_html'],
                'sources' => json_decode($draft['sources'] ?? '[]', true),
                'seo_title' => $draft['seo_title'],
                'meta_description' => $draft['meta_description'],
                'slug' => $draft['slug'],
                'keywords' => json_decode($draft['keywords'] ?? '[]', true),
                'tags' => json_decode($draft['tags'] ?? '[]', true),
                'geo_tags' => json_decode($draft['geo_tags'] ?? '[]', true),
                'image_url' => $draft['image_url'],
                'image_author' => $draft['image_author'],
                'image_alt' => $draft['image_alt'],
                'sentiment' => $draft['sentiment'],
                'gate_reason' => $draft['gate_reason'],
                'scheduled_at' => $draft['scheduled_at'],
                'published_at' => $draft['published_at'],
                'wp_post_id' => $draft['wp_post_id'],
            ]);
        }

        return $formatted;
    }

    /**
     * Convert period string to days
     */
    private function period_to_days($period) {
        $map = [
            '24h' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
        ];

        return $map[$period] ?? 7;
    }

    /**
     * Check and improve text with AI
     */
    public function check_text_with_ai($request) {
        $text = $request->get_param('text');
        $lang = $request->get_param('lang') ?: 'de';

        if (empty($text)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Текст не указан',
            ], 400);
        }

        try {
            $ai = AINCC_AI_Provider_Factory::create();

            $prompt = "Улучши этот текст, исправь грамматические ошибки, сделай его более читаемым и профессиональным. Сохрани смысл и факты. Верни ТОЛЬКО улучшенный текст без пояснений:";

            $result = $ai->complete($text, $prompt);

            if ($result['success']) {
                return new WP_REST_Response([
                    'success' => true,
                    'improved' => trim($result['content']),
                    'original' => $text,
                ], 200);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'AI недоступен',
            ], 400);

        } catch (Exception $e) {
            AINCC_Logger::error('AI check text failed', ['error' => $e->getMessage()]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка AI: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle source enabled status
     */
    public function toggle_source($request) {
        global $wpdb;
        $id = $request->get_param('id');

        $db = new AINCC_Database();
        $source = $db->get_source($id);

        if (!$source) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Источник не найден',
            ], 404);
        }

        // Properly cast to int for comparison (database might return string '0' or '1')
        $current_status = (int) $source['enabled'];
        $new_status = $current_status ? 0 : 1;

        $result = $wpdb->update(
            $db->table('sources'),
            ['enabled' => $new_status],
            ['id' => $id]
        );

        if ($result !== false) {
            AINCC_Logger::info('Source toggled', ['id' => $id, 'was' => $current_status, 'now' => $new_status]);
            return new WP_REST_Response([
                'success' => true,
                'enabled' => (bool) $new_status,
                'message' => $new_status ? 'Источник включен' : 'Источник отключен',
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Ошибка обновления',
        ], 500);
    }

    /**
     * Clear queue items by status
     */
    public function clear_queue($request) {
        global $wpdb;
        $status = $request->get_param('status') ?: 'rejected';
        $db = new AINCC_Database();

        // Valid statuses to clear
        $valid_statuses = ['rejected', 'published', 'failed'];
        if (!in_array($status, $valid_statuses)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Недопустимый статус',
            ], 400);
        }

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$db->table('drafts')} WHERE status = %s",
                $status
            )
        );

        AINCC_Logger::info('Queue cleared', ['status' => $status, 'deleted' => $deleted]);

        return new WP_REST_Response([
            'success' => true,
            'deleted' => (int) $deleted,
            'message' => "Удалено записей: {$deleted}",
        ], 200);
    }

    /**
     * Bulk delete drafts
     */
    public function bulk_delete_drafts($request) {
        global $wpdb;
        $data = $request->get_json_params();
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Не указаны ID для удаления',
            ], 400);
        }

        $db = new AINCC_Database();
        $deleted = 0;

        foreach ($ids as $id) {
            $result = $wpdb->delete($db->table('drafts'), ['id' => sanitize_text_field($id)]);
            if ($result) {
                $deleted++;
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'deleted' => $deleted,
            'message' => "Удалено: {$deleted}",
        ], 200);
    }

    /**
     * Get AI prompts settings
     */
    public function get_prompts($request) {
        $prompts = [
            'rewrite_style' => AINCC_Settings::get('prompt_rewrite_style',
                'Профессиональная новостная статья. Факты, ясность, без эмоций. Стиль Deutsche Welle. Для украинской аудитории в Германии.'),
            'tone' => AINCC_Settings::get('prompt_tone', 'neutral'),
            'custom_instructions' => AINCC_Settings::get('prompt_custom_instructions', ''),
            'seo_focus' => AINCC_Settings::get('prompt_seo_focus', 'Украинцы в Германии, миграция, интеграция'),
            'image_style' => AINCC_Settings::get('prompt_image_style', 'professional news photography'),
            'translation_notes' => AINCC_Settings::get('prompt_translation_notes', 'Сохраняй немецкие термины (BAMF, Jobcenter) с пояснениями'),
        ];

        return new WP_REST_Response($prompts, 200);
    }

    /**
     * Update AI prompts settings
     */
    public function update_prompts($request) {
        $data = $request->get_json_params();

        $allowed = ['rewrite_style', 'tone', 'custom_instructions', 'seo_focus', 'image_style', 'translation_notes'];

        foreach ($allowed as $key) {
            if (isset($data[$key])) {
                AINCC_Settings::set('prompt_' . $key, sanitize_textarea_field($data[$key]));
            }
        }

        AINCC_Logger::info('AI prompts updated');

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Get cron settings
     */
    public function get_cron_settings($request) {
        $settings = [
            'fetch_interval' => AINCC_Settings::get('fetch_interval', 5),
            'process_interval' => AINCC_Settings::get('process_interval', 2),
            'auto_publish_interval' => AINCC_Settings::get('auto_publish_interval', 5),
            'auto_publish_enabled' => AINCC_Settings::get('auto_publish_enabled', false),
            'auto_publish_delay' => AINCC_Settings::get('auto_publish_delay', 10),
            'batch_size' => AINCC_Settings::get('batch_size', 5),
        ];

        // Add current cron status
        $scheduler = new AINCC_Scheduler();
        $settings['cron_status'] = $scheduler->get_cron_status();

        return new WP_REST_Response($settings, 200);
    }

    /**
     * Update cron settings
     */
    public function update_cron_settings($request) {
        $data = $request->get_json_params();

        $intervals = [
            'fetch_interval' => [2, 5, 10, 15, 30, 60, 120],
            'process_interval' => [2, 5, 10],
            'auto_publish_interval' => [5, 10, 15, 30],
        ];

        // Validate and set intervals
        foreach ($intervals as $key => $valid_values) {
            if (isset($data[$key])) {
                $value = (int) $data[$key];
                if (in_array($value, $valid_values)) {
                    AINCC_Settings::set($key, $value);
                }
            }
        }

        // Other settings
        if (isset($data['auto_publish_enabled'])) {
            AINCC_Settings::set('auto_publish_enabled', (bool) $data['auto_publish_enabled']);
        }
        if (isset($data['auto_publish_delay'])) {
            AINCC_Settings::set('auto_publish_delay', max(0, min(60, (int) $data['auto_publish_delay'])));
        }
        if (isset($data['batch_size'])) {
            AINCC_Settings::set('batch_size', max(1, min(20, (int) $data['batch_size'])));
        }

        AINCC_Logger::info('Cron settings updated', $data);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Reschedule all cron jobs
     */
    public function reschedule_cron($request) {
        $scheduler = new AINCC_Scheduler();
        $result = $scheduler->reschedule_all();

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get queue statistics
     */
    public function get_queue_stats($request) {
        global $wpdb;
        $db = new AINCC_Database();

        $stats = [
            'drafts' => [
                'pending_ok' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('drafts')} WHERE status = 'pending_ok'"),
                'auto_ready' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('drafts')} WHERE status = 'auto_ready'"),
                'published' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('drafts')} WHERE status = 'published'"),
                'rejected' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('drafts')} WHERE status = 'rejected'"),
                'scheduled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('drafts')} WHERE status = 'scheduled'"),
            ],
            'queue' => [
                'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('queue')} WHERE status = 'pending'"),
                'processing' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('queue')} WHERE status = 'processing'"),
                'failed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('queue')} WHERE status = 'failed'"),
            ],
            'raw_items' => [
                'new' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('raw_items')} WHERE status = 'new'"),
                'processing' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('raw_items')} WHERE status = 'processing'"),
                'processed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('raw_items')} WHERE status = 'processed'"),
            ],
            'sources' => [
                'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('sources')}"),
                'enabled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('sources')} WHERE enabled = 1"),
            ],
        ];

        return new WP_REST_Response($stats, 200);
    }

    /**
     * AI rewrite draft with custom instructions
     */
    public function ai_rewrite_draft($request) {
        $id = $request->get_param('id');
        $instructions = $request->get_param('instructions');

        $db = new AINCC_Database();
        $draft = $db->get_draft($id);

        if (!$draft) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Черновик не найден',
            ], 404);
        }

        try {
            $ai = AINCC_AI_Provider_Factory::create();
            $content = $draft['title'] . "\n\n" . $draft['lead'] . "\n\n" . $draft['body_html'];

            // Get custom prompt style from settings
            $base_style = AINCC_Settings::get('prompt_rewrite_style',
                'Профессиональная новостная статья. Факты, ясность, без эмоций.');

            $full_instructions = $base_style . "\n\nДополнительные инструкции: " . $instructions;

            $result = $ai->rewrite($content, $full_instructions, $draft['lang']);

            if ($result['success']) {
                // Parse the rewritten content
                $processor = new AINCC_Content_Processor();
                $parsed = $this->parse_ai_content($result['content']);

                // Update draft
                $update_data = [
                    'body_html' => $this->convert_markdown_to_html($parsed['body'] ?? $result['content']),
                ];

                if (!empty($parsed['title'])) {
                    $update_data['title'] = $parsed['title'];
                }
                if (!empty($parsed['lead'])) {
                    $update_data['lead'] = $parsed['lead'];
                }

                $db->update_draft($id, $update_data);

                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Текст переписан',
                    'content' => $update_data,
                ], 200);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'AI error',
            ], 400);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse AI content response
     */
    private function parse_ai_content($content) {
        $result = ['title' => '', 'lead' => '', 'body' => $content];

        // Try to extract title
        if (preg_match('/<title>(.*?)<\/title>/is', $content, $m)) {
            $result['title'] = trim(strip_tags($m[1]));
            $content = str_replace($m[0], '', $content);
        }

        // Try to extract lead
        if (preg_match('/<lead>(.*?)<\/lead>/is', $content, $m)) {
            $result['lead'] = trim(strip_tags($m[1]));
            $content = str_replace($m[0], '', $content);
        }

        $result['body'] = trim($content);
        return $result;
    }

    /**
     * Convert Markdown to HTML
     */
    private function convert_markdown_to_html($text) {
        // Convert **bold** to <strong>
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

        // Convert *italic* to <em>
        $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);

        // Convert ## headers
        $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);

        // Convert line breaks to paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $paragraphs = array_filter(array_map('trim', $paragraphs));

        if (count($paragraphs) > 1) {
            $text = '<p>' . implode('</p><p>', $paragraphs) . '</p>';
        }

        // Clean up any remaining markdown artifacts
        $text = preg_replace('/^\s*[-*]\s+/m', '• ', $text);

        return $text;
    }

    /**
     * Upload image for draft
     */
    public function upload_draft_image($request) {
        $id = $request->get_param('id');
        $files = $request->get_file_params();

        if (empty($files['image'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Файл не загружен',
            ], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload('image', 0);

        if (is_wp_error($attachment_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $attachment_id->get_error_message(),
            ], 400);
        }

        $image_url = wp_get_attachment_url($attachment_id);

        $db = new AINCC_Database();
        $db->update_draft($id, [
            'image_url' => $image_url,
            'image_local_id' => $attachment_id,
            'image_author' => 'Uploaded',
            'image_license' => 'User Upload',
        ]);

        return new WP_REST_Response([
            'success' => true,
            'image_url' => $image_url,
            'attachment_id' => $attachment_id,
        ], 200);
    }

    /**
     * Set image from URL for draft
     */
    public function set_draft_image($request) {
        $id = $request->get_param('id');
        $image_url = $request->get_param('image_url');
        $alt = $request->get_param('alt') ?: '';
        $author = $request->get_param('author') ?: '';

        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Некорректный URL изображения',
            ], 400);
        }

        $db = new AINCC_Database();
        $draft = $db->get_draft($id);

        if (!$draft) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Черновик не найден',
            ], 404);
        }

        // Try to pre-download image to media library for faster publishing
        $image_local_id = null;
        $image_handler = new AINCC_Image_Handler();
        $image_data = [
            'url' => $image_url,
            'alt' => $alt ?: $draft['title'],
            'author' => $author,
            'license' => 'Pexels License',
            'source' => 'Pexels',
        ];

        $attachment_id = $image_handler->save_to_media_library($image_data, 0);
        if ($attachment_id) {
            $image_local_id = $attachment_id;
            AINCC_Logger::info('Image pre-downloaded to media library', ['draft_id' => $id, 'attachment_id' => $attachment_id]);
        }

        $update_data = [
            'image_url' => esc_url_raw($image_url),
            'image_alt' => sanitize_text_field($alt ?: $draft['title']),
            'image_author' => sanitize_text_field($author),
            'image_license' => 'Pexels License',
        ];

        if ($image_local_id) {
            $update_data['image_local_id'] = $image_local_id;
        }

        $db->update_draft($id, $update_data);

        return new WP_REST_Response([
            'success' => true,
            'image_url' => $image_url,
            'image_local_id' => $image_local_id,
        ], 200);
    }

    /**
     * Get WordPress categories
     */
    public function get_wordpress_categories($request) {
        $categories = get_categories([
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => false,
        ]);

        $items = [];
        foreach ($categories as $cat) {
            $items[] = [
                'id' => $cat->term_id,
                'slug' => $cat->slug,
                'name' => $cat->name,
                'count' => $cat->count,
                'parent' => $cat->parent,
            ];
        }

        return new WP_REST_Response(['items' => $items], 200);
    }

    /**
     * Get WordPress pages
     */
    public function get_wordpress_pages($request) {
        $pages = get_pages([
            'sort_column' => 'menu_order,post_title',
            'sort_order' => 'ASC',
        ]);

        $items = [];
        foreach ($pages as $page) {
            $items[] = [
                'id' => $page->ID,
                'slug' => $page->post_name,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
                'parent' => $page->post_parent,
            ];
        }

        return new WP_REST_Response(['items' => $items], 200);
    }

    /**
     * Clear all queue items by types
     */
    public function clear_all_queue($request) {
        global $wpdb;
        $db = new AINCC_Database();
        $types = $request->get_param('types') ?: ['rejected', 'failed'];

        $valid_types = ['rejected', 'failed', 'published', 'auto_ready', 'pending_ok'];
        $types = array_intersect($types, $valid_types);

        if (empty($types)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Нет допустимых типов для очистки',
            ], 400);
        }

        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$db->table('drafts')} WHERE status IN ($placeholders)",
                ...$types
            )
        );

        // Also clear failed queue items
        $queue_deleted = $wpdb->query(
            "DELETE FROM {$db->table('queue')} WHERE status = 'failed'"
        );

        AINCC_Logger::info('Queue cleared', [
            'types' => $types,
            'drafts_deleted' => $deleted,
            'queue_deleted' => $queue_deleted,
        ]);

        return new WP_REST_Response([
            'success' => true,
            'deleted' => [
                'drafts' => (int) $deleted,
                'queue' => (int) $queue_deleted,
            ],
            'message' => "Удалено черновиков: {$deleted}, очереди: {$queue_deleted}",
        ], 200);
    }

    /**
     * Extract image from article URL
     */
    public function extract_image_from_url($request) {
        $url = $request->get_param('url');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Некорректный URL',
            ], 400);
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Не удалось загрузить страницу',
            ], 400);
        }

        $html = wp_remote_retrieve_body($response);
        $images = [];

        // Try og:image first (most reliable)
        if (preg_match('/property=["\']og:image["\'][^>]*content=["\']([^"\']+)/i', $html, $m)) {
            $images[] = [
                'url' => $m[1],
                'type' => 'og:image',
                'priority' => 1,
            ];
        }

        // Try twitter:image
        if (preg_match('/name=["\']twitter:image["\'][^>]*content=["\']([^"\']+)/i', $html, $m)) {
            $images[] = [
                'url' => $m[1],
                'type' => 'twitter:image',
                'priority' => 2,
            ];
        }

        // Try to find images in article
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $i => $img_url) {
                // Filter out icons, logos, etc.
                if (preg_match('/(logo|icon|sprite|avatar|button|banner)/i', $img_url)) {
                    continue;
                }

                // Prefer larger images
                $is_large = preg_match('/(large|big|full|original|1200|1024|800)/i', $img_url);

                $images[] = [
                    'url' => $img_url,
                    'type' => 'img',
                    'priority' => $is_large ? 3 : 10 + $i,
                ];

                if (count($images) > 10) break;
            }
        }

        // Sort by priority
        usort($images, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        if (empty($images)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Изображения не найдены',
            ], 404);
        }

        // Make URLs absolute
        $base_url = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
        foreach ($images as &$img) {
            if (strpos($img['url'], '//') === 0) {
                $img['url'] = 'https:' . $img['url'];
            } elseif (strpos($img['url'], '/') === 0) {
                $img['url'] = $base_url . $img['url'];
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'images' => array_slice($images, 0, 5),
            'primary' => $images[0]['url'],
        ], 200);
    }

    /**
     * Handle public cron request (uses secret key for auth)
     * This allows TRUE scheduled execution via server cron
     */
    public function handle_public_cron($request) {
        $secret = $request->get_param('secret');
        $action = $request->get_param('action') ?: 'all';

        // Verify secret
        $stored_secret = get_option('aincc_cron_secret', '');

        // Auto-generate secret if not exists
        if (empty($stored_secret)) {
            $stored_secret = wp_generate_password(32, false);
            update_option('aincc_cron_secret', $stored_secret, 'no');
        }

        if (empty($secret) || !hash_equals($stored_secret, $secret)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Invalid or missing secret key',
                'help' => 'Get the secret from AI News Control Center > Settings > Cron Setup',
            ], 403);
        }

        // Set time limit for cron execution
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $results = [
            'status' => 'ok',
            'timestamp' => current_time('mysql'),
            'actions' => [],
        ];

        try {
            $scheduler = new AINCC_Scheduler();

            switch ($action) {
                case 'fetch':
                    $result = $scheduler->fetch_sources();
                    $results['actions']['fetch'] = $result;
                    break;

                case 'process':
                    $result = $scheduler->process_queue();
                    $results['actions']['process'] = $result;
                    break;

                case 'publish':
                    $result = $scheduler->auto_publish();
                    $results['actions']['publish'] = $result;
                    break;

                case 'cleanup':
                    $result = $scheduler->cleanup();
                    $results['actions']['cleanup'] = $result;
                    break;

                case 'all':
                default:
                    // 1. Fetch RSS
                    $fetch_result = $scheduler->fetch_sources();
                    $results['actions']['fetch'] = [
                        'success' => $fetch_result['success'] ?? false,
                        'fetched' => $fetch_result['fetched'] ?? 0,
                    ];

                    // Small delay between operations
                    usleep(500000);

                    // 2. Process queue
                    $process_result = $scheduler->process_queue();
                    $results['actions']['process'] = [
                        'success' => $process_result['success'] ?? false,
                        'processed' => $process_result['processed'] ?? 0,
                    ];

                    // 3. Auto-publish
                    $publish_result = $scheduler->auto_publish();
                    $results['actions']['publish'] = [
                        'success' => $publish_result['success'] ?? false,
                        'published' => $publish_result['published'] ?? 0,
                    ];
                    break;
            }

            // Summary
            $results['summary'] = [
                'articles_fetched' => $results['actions']['fetch']['fetched'] ?? 0,
                'drafts_created' => $results['actions']['process']['processed'] ?? 0,
                'posts_published' => $results['actions']['publish']['published'] ?? 0,
            ];

        } catch (Throwable $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            AINCC_Logger::error('Public cron error', ['error' => $e->getMessage()]);
        }

        return new WP_REST_Response($results, 200);
    }

    /**
     * Get cron setup information including secret key and instructions
     */
    public function get_cron_setup_info($request) {
        // Get or create secret
        $secret = get_option('aincc_cron_secret', '');
        if (empty($secret)) {
            $secret = wp_generate_password(32, false);
            update_option('aincc_cron_secret', $secret, 'no');
        }

        $site_url = get_site_url();
        $rest_url = rest_url('aincc/v1/cron');
        $cron_file_url = plugins_url('cron.php', AINCC_PLUGIN_DIR . '/ai-news-control-center-plugin.php');

        // Build example commands
        $curl_cmd = "curl -s \"{$rest_url}?secret={$secret}\" > /dev/null 2>&1";
        $wget_cmd = "wget -q -O /dev/null \"{$rest_url}?secret={$secret}\"";

        return new WP_REST_Response([
            'secret' => $secret,
            'endpoints' => [
                'rest_api' => $rest_url,
                'cron_file' => $cron_file_url,
            ],
            'commands' => [
                'curl' => $curl_cmd,
                'wget' => $wget_cmd,
            ],
            'crontab_examples' => [
                'every_5_min' => "*/5 * * * * {$curl_cmd}",
                'every_10_min' => "*/10 * * * * {$curl_cmd}",
                'every_15_min' => "*/15 * * * * {$curl_cmd}",
                'every_30_min' => "*/30 * * * * {$curl_cmd}",
                'hourly' => "0 * * * * {$curl_cmd}",
            ],
            'instructions' => [
                'ru' => [
                    '1. Откройте crontab на сервере: crontab -e',
                    '2. Добавьте строку из примера (every_5_min для каждых 5 минут)',
                    '3. Сохраните и закройте',
                    '4. Опционально: отключите WP Cron в wp-config.php:',
                    "   define('DISABLE_WP_CRON', true);",
                ],
                'actions' => [
                    'all' => 'Полный цикл: сбор → обработка → публикация',
                    'fetch' => 'Только сбор новостей из RSS',
                    'process' => 'Только обработка очереди (AI)',
                    'publish' => 'Только автопубликация',
                    'cleanup' => 'Очистка старых данных',
                ],
            ],
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        ], 200);
    }

    /**
     * Regenerate cron secret key
     */
    public function regenerate_cron_secret($request) {
        $new_secret = wp_generate_password(32, false);
        update_option('aincc_cron_secret', $new_secret, 'no');

        AINCC_Logger::info('Cron secret regenerated');

        return new WP_REST_Response([
            'success' => true,
            'secret' => $new_secret,
            'message' => 'Секретный ключ обновлён. Не забудьте обновить его в crontab!',
        ], 200);
    }
}
