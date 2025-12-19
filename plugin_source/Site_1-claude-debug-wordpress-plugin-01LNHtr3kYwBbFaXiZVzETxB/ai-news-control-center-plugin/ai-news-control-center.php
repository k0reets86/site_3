<?php
/**
 * Plugin Name: AI News Control Center
 * Plugin URI: https://github.com/your-repo/ai-news-control-center
 * Description: Autonomous news platform for content aggregation, AI processing, translation, and multi-channel publishing
 * Version: 1.0.0
 * Author: AI News Team
 * Author URI: https://your-site.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-news-center
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AINCC_VERSION', '1.0.0');
define('AINCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AINCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AINCC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AINCC_DB_VERSION', '1.0.0');

/**
 * Main Plugin Class - Optimized for Performance
 *
 * KEY OPTIMIZATIONS:
 * 1. Lazy loading - components only loaded when needed
 * 2. Admin-only loading for admin classes
 * 3. REST API only loaded on REST requests
 * 4. Error boundaries around all operations
 * 5. Memory-efficient singleton pattern
 */
final class AI_News_Control_Center {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Plugin components (lazy loaded)
     */
    private $components = [];

    /**
     * Whether core was loaded
     */
    private $core_loaded = false;

    /**
     * Get single instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Minimal initialization
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Magic getter for lazy-loaded components
     */
    public function __get($name) {
        return $this->get_component($name);
    }

    /**
     * Get component with lazy loading
     */
    public function get_component($name) {
        if (!isset($this->components[$name])) {
            $this->load_component($name);
        }
        return $this->components[$name] ?? null;
    }

    /**
     * Load a specific component
     */
    private function load_component($name) {
        try {
            $this->ensure_core_loaded();

            switch ($name) {
                case 'database':
                    $this->components['database'] = new AINCC_Database();
                    break;

                case 'ai_provider':
                    $this->load_ai_providers();
                    $this->components['ai_provider'] = AINCC_AI_Provider_Factory::create();
                    break;

                case 'rss_parser':
                    require_once AINCC_PLUGIN_DIR . 'includes/class-rss-parser.php';
                    $this->components['rss_parser'] = new AINCC_RSS_Parser();
                    break;

                case 'content_processor':
                    require_once AINCC_PLUGIN_DIR . 'includes/class-content-processor.php';
                    $this->components['content_processor'] = new AINCC_Content_Processor();
                    break;

                case 'image_handler':
                    require_once AINCC_PLUGIN_DIR . 'includes/class-image-handler.php';
                    $this->components['image_handler'] = new AINCC_Image_Handler();
                    break;

                case 'publisher':
                    require_once AINCC_PLUGIN_DIR . 'includes/class-publisher.php';
                    $this->components['publisher'] = new AINCC_Publisher();
                    break;

                case 'telegram':
                    require_once AINCC_PLUGIN_DIR . 'includes/class-telegram.php';
                    $this->components['telegram'] = new AINCC_Telegram();
                    break;

                case 'scheduler':
                    require_once AINCC_PLUGIN_DIR . 'includes/class-scheduler.php';
                    $this->components['scheduler'] = new AINCC_Scheduler();
                    break;

                case 'fact_checker':
                    require_once AINCC_PLUGIN_DIR . 'includes/class-fact-checker.php';
                    $this->components['fact_checker'] = new AINCC_Fact_Checker();
                    break;

                case 'seo_optimizer':
                    require_once AINCC_PLUGIN_DIR . 'includes/class-seo-optimizer.php';
                    $this->components['seo_optimizer'] = new AINCC_SEO_Optimizer();
                    break;
            }
        } catch (Throwable $e) {
            $this->log_error('Component load failed: ' . $name, $e);
            $this->components[$name] = null;
        }
    }

    /**
     * Load AI provider files
     */
    private function load_ai_providers() {
        require_once AINCC_PLUGIN_DIR . 'includes/ai-providers/interface-ai-provider.php';
        require_once AINCC_PLUGIN_DIR . 'includes/ai-providers/class-ai-provider-factory.php';
        require_once AINCC_PLUGIN_DIR . 'includes/ai-providers/class-deepseek-provider.php';
        require_once AINCC_PLUGIN_DIR . 'includes/ai-providers/class-openai-provider.php';
        require_once AINCC_PLUGIN_DIR . 'includes/ai-providers/class-anthropic-provider.php';
    }

    /**
     * Ensure core dependencies are loaded
     */
    private function ensure_core_loaded() {
        if ($this->core_loaded) {
            return;
        }

        require_once AINCC_PLUGIN_DIR . 'includes/class-database.php';
        require_once AINCC_PLUGIN_DIR . 'includes/class-logger.php';
        require_once AINCC_PLUGIN_DIR . 'includes/class-settings.php';

        $this->core_loaded = true;
    }

    /**
     * Initialize hooks - Minimal for fast loading
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Text domain
        add_action('init', [$this, 'load_textdomain']);

        // Cron schedules filter
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);

        // Admin only - load admin class
        if (is_admin()) {
            add_action('plugins_loaded', [$this, 'init_admin'], 15);
        }

        // REST API - only load on REST requests
        add_action('rest_api_init', [$this, 'init_rest_api']);

        // Cron handlers - lazy loaded
        add_action('aincc_fetch_sources', [$this, 'handle_cron_fetch']);
        add_action('aincc_process_queue', [$this, 'handle_cron_process']);
        add_action('aincc_auto_publish', [$this, 'handle_cron_publish']);
        add_action('aincc_cleanup_old_data', [$this, 'handle_cron_cleanup']);
    }

    /**
     * Initialize admin (only in admin context)
     */
    public function init_admin() {
        try {
            $this->ensure_core_loaded();
            require_once AINCC_PLUGIN_DIR . 'admin/class-admin.php';
            new AINCC_Admin();
        } catch (Throwable $e) {
            $this->log_error('Admin init failed', $e);
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>AI News Control Center:</strong> ';
                echo esc_html__('Admin initialization error. Check error logs.', 'ai-news-center');
                echo '</p></div>';
            });
        }
    }

    /**
     * Initialize REST API (only on REST requests)
     */
    public function init_rest_api() {
        try {
            $this->ensure_core_loaded();
            require_once AINCC_PLUGIN_DIR . 'includes/api/class-rest-api.php';
            $api = new AINCC_REST_API();
            $api->register_routes();
        } catch (Throwable $e) {
            $this->log_error('REST API init failed', $e);
        }
    }

    /**
     * Cron handler: Fetch sources
     */
    public function handle_cron_fetch() {
        try {
            // Set time limit for cron job
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            // Directly use RSS parser for reliable fetching
            $rss_parser = $this->get_component('rss_parser');
            if ($rss_parser) {
                $rss_parser->fetch_all_sources();
            }
        } catch (Throwable $e) {
            $this->log_error('Cron fetch failed', $e);
        }
    }

    /**
     * Cron handler: Process queue
     */
    public function handle_cron_process() {
        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            // Directly use content processor for reliable processing
            $processor = $this->get_component('content_processor');
            if ($processor) {
                $processor->process_queue();
            }
        } catch (Throwable $e) {
            $this->log_error('Cron process failed', $e);
        }
    }

    /**
     * Cron handler: Auto publish
     */
    public function handle_cron_publish() {
        try {
            $scheduler = $this->get_component('scheduler');
            if ($scheduler) {
                $scheduler->auto_publish();
            }
        } catch (Throwable $e) {
            $this->log_error('Cron publish failed', $e);
        }
    }

    /**
     * Cron handler: Cleanup old data
     */
    public function handle_cron_cleanup() {
        try {
            $scheduler = $this->get_component('scheduler');
            if ($scheduler) {
                $scheduler->cleanup();
            }
        } catch (Throwable $e) {
            $this->log_error('Cron cleanup failed', $e);
        }
    }

    /**
     * Plugin activation - with error handling
     */
    public function activate() {
        try {
            // Check PHP version
            if (version_compare(PHP_VERSION, '8.0', '<')) {
                deactivate_plugins(plugin_basename(__FILE__));
                wp_die(
                    __('AI News Control Center requires PHP 8.0 or higher.', 'ai-news-center'),
                    'Plugin Activation Error',
                    ['back_link' => true]
                );
            }

            // Check WordPress version
            global $wp_version;
            if (version_compare($wp_version, '6.0', '<')) {
                deactivate_plugins(plugin_basename(__FILE__));
                wp_die(
                    __('AI News Control Center requires WordPress 6.0 or higher.', 'ai-news-center'),
                    'Plugin Activation Error',
                    ['back_link' => true]
                );
            }

            // Create database tables
            require_once AINCC_PLUGIN_DIR . 'includes/class-database.php';
            require_once AINCC_PLUGIN_DIR . 'includes/class-logger.php';

            $db = new AINCC_Database();
            $db->create_tables();

            // Create default options
            $this->create_default_options();

            // Schedule cron jobs
            $this->schedule_cron_jobs();

            // Flush rewrite rules
            flush_rewrite_rules();

            // Log activation
            AINCC_Logger::info('Plugin activated', ['version' => AINCC_VERSION]);

        } catch (Throwable $e) {
            $this->log_error('Activation failed', $e);
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('Plugin activation failed: ', 'ai-news-center') . $e->getMessage(),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        try {
            // Clear scheduled hooks
            wp_clear_scheduled_hook('aincc_fetch_sources');
            wp_clear_scheduled_hook('aincc_process_queue');
            wp_clear_scheduled_hook('aincc_auto_publish');
            wp_clear_scheduled_hook('aincc_cleanup_old_data');

            // Flush rewrite rules
            flush_rewrite_rules();

            // Log if logger is available
            if (class_exists('AINCC_Logger')) {
                AINCC_Logger::info('Plugin deactivated');
            }
        } catch (Throwable $e) {
            // Silent fail on deactivation
            error_log('AINCC deactivation error: ' . $e->getMessage());
        }
    }

    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-news-center',
            false,
            dirname(AINCC_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_2_minutes'] = [
            'interval' => 120,
            'display' => __('Every 2 Minutes', 'ai-news-center')
        ];
        $schedules['every_5_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'ai-news-center')
        ];
        $schedules['every_10_minutes'] = [
            'interval' => 600,
            'display' => __('Every 10 Minutes', 'ai-news-center')
        ];
        $schedules['every_15_minutes'] = [
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'ai-news-center')
        ];
        return $schedules;
    }

    /**
     * Create default plugin options
     */
    private function create_default_options() {
        $defaults = [
            'aincc_ai_provider' => 'deepseek',
            'aincc_deepseek_api_key' => '',
            'aincc_deepseek_model' => 'deepseek-chat',
            'aincc_openai_api_key' => '',
            'aincc_openai_model' => 'gpt-4o-mini',
            'aincc_anthropic_api_key' => '',
            'aincc_anthropic_model' => 'claude-sonnet-4-20250514',
            'aincc_pexels_api_key' => '',
            'aincc_telegram_bot_token' => '',
            'aincc_telegram_channel_id' => '',
            'aincc_default_language' => 'de',
            'aincc_target_languages' => ['de', 'ua', 'ru', 'en'],
            'aincc_auto_publish_enabled' => false,
            'aincc_auto_publish_delay' => 10,
            'aincc_fact_check_threshold' => 0.6,
            'aincc_source_trust_threshold' => 0.7,
            'aincc_fetch_interval' => 5,
            'aincc_max_articles_per_day' => 50,
            'aincc_categories_require_approval' => ['politik', 'wirtschaft'],
            'aincc_db_version' => AINCC_DB_VERSION,
            // Performance settings
            'aincc_batch_size' => 5,
            'aincc_api_timeout' => 30,
            'aincc_max_memory_mb' => 256,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value, '', 'no'); // 'no' = don't autoload
            }
        }
    }

    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        // Fetch sources every 5 minutes
        if (!wp_next_scheduled('aincc_fetch_sources')) {
            wp_schedule_event(time() + 60, 'every_5_minutes', 'aincc_fetch_sources');
        }

        // Process queue every 2 minutes
        if (!wp_next_scheduled('aincc_process_queue')) {
            wp_schedule_event(time() + 120, 'every_2_minutes', 'aincc_process_queue');
        }

        // Auto publish check every 5 minutes
        if (!wp_next_scheduled('aincc_auto_publish')) {
            wp_schedule_event(time() + 180, 'every_5_minutes', 'aincc_auto_publish');
        }

        // Daily cleanup at 3 AM
        if (!wp_next_scheduled('aincc_cleanup_old_data')) {
            $tomorrow_3am = strtotime('tomorrow 3:00am');
            wp_schedule_event($tomorrow_3am, 'daily', 'aincc_cleanup_old_data');
        }
    }

    /**
     * Log error safely
     */
    private function log_error($message, Throwable $e) {
        $log_message = sprintf(
            '[AINCC Error] %s: %s in %s:%d',
            $message,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        error_log($log_message);

        // Also log to plugin logger if available
        if (class_exists('AINCC_Logger')) {
            AINCC_Logger::error($message, [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Check if running in safe mode
     */
    public function is_safe_mode() {
        return defined('AINCC_SAFE_MODE') && AINCC_SAFE_MODE;
    }
}

/**
 * Main function to get plugin instance
 */
function aincc() {
    return AI_News_Control_Center::instance();
}

/**
 * Helper function to safely get a component
 */
function aincc_get($component) {
    return aincc()->get_component($component);
}

// Initialize plugin only if not in maintenance mode
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    aincc();
}
