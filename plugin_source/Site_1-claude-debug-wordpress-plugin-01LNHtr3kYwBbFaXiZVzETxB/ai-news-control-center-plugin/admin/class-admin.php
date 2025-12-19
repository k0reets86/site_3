<?php
/**
 * Admin Class
 * Управление административным интерфейсом плагина
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'check_and_init_database']);

        // Add admin bar menu
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);

        // Ajax handlers
        add_action('wp_ajax_aincc_quick_stats', [$this, 'ajax_quick_stats']);
    }

    /**
     * Check if database tables exist and create if not
     */
    public function check_and_init_database() {
        // Only check on our plugin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'ai-news-center') === false) {
            return;
        }

        // Check if we already initialized in this session
        $db_initialized = get_transient('aincc_db_initialized');
        if ($db_initialized) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'aincc_sources';

        // Check if main table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

        if (!$table_exists) {
            // Tables don't exist - create them
            $db = new AINCC_Database();
            $db->create_tables();

            AINCC_Logger::info('Database auto-initialized on first admin access');
        }

        // Set transient to avoid checking every page load (valid for 1 hour)
        set_transient('aincc_db_initialized', true, HOUR_IN_SECONDS);
    }

    /**
     * Add admin menu (Russian)
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'AI Новости',
            'AI Новости',
            'edit_posts',
            'ai-news-center',
            [$this, 'render_main_page'],
            'dashicons-rss',
            26
        );

        // Submenus
        add_submenu_page(
            'ai-news-center',
            'Панель управления',
            'Панель управления',
            'edit_posts',
            'ai-news-center',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ai-news-center',
            'Создать статью',
            'Создать статью',
            'edit_posts',
            'ai-news-center-create',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ai-news-center',
            'Из ссылки',
            'Из ссылки',
            'edit_posts',
            'ai-news-center-from-url',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ai-news-center',
            'Источники RSS',
            'Источники RSS',
            'manage_options',
            'ai-news-center-sources',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ai-news-center',
            'Аналитика',
            'Аналитика',
            'edit_posts',
            'ai-news-center-analytics',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ai-news-center',
            'Настройки',
            'Настройки',
            'manage_options',
            'ai-news-center-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on our pages
        if (strpos($hook, 'ai-news-center') === false) {
            return;
        }

        // Get current page for React router
        $current_page = 'dashboard';
        if (strpos($hook, '-create') !== false) {
            $current_page = 'create';
        } elseif (strpos($hook, '-from-url') !== false) {
            $current_page = 'from-url';
        } elseif (strpos($hook, '-analytics') !== false) {
            $current_page = 'analytics';
        } elseif (strpos($hook, '-sources') !== false) {
            $current_page = 'sources';
        } elseif (strpos($hook, '-settings') !== false) {
            $current_page = 'settings';
        }

        // Main React app CSS
        wp_enqueue_style(
            'aincc-admin',
            AINCC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AINCC_VERSION
        );

        // Google Fonts
        wp_enqueue_style(
            'aincc-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            null
        );

        // Tailwind CSS (from CDN for now)
        wp_enqueue_script(
            'aincc-tailwind',
            'https://cdn.tailwindcss.com',
            [],
            null,
            false
        );

        // React and ReactDOM
        wp_enqueue_script(
            'aincc-react',
            'https://unpkg.com/react@18/umd/react.production.min.js',
            [],
            '18',
            true
        );

        wp_enqueue_script(
            'aincc-react-dom',
            'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
            ['aincc-react'],
            '18',
            true
        );

        // Main admin app
        wp_enqueue_script(
            'aincc-admin-app',
            AINCC_PLUGIN_URL . 'assets/js/admin-app.js',
            ['aincc-react', 'aincc-react-dom'],
            AINCC_VERSION,
            true
        );

        // Pass data to JS (Russian)
        wp_localize_script('aincc-admin-app', 'ainccData', [
            'apiUrl' => rest_url('aincc/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'pluginUrl' => AINCC_PLUGIN_URL,
            'currentPage' => $current_page,
            'currentUser' => [
                'id' => get_current_user_id(),
                'name' => wp_get_current_user()->display_name,
                'email' => wp_get_current_user()->user_email,
                'isAdmin' => current_user_can('manage_options'),
            ],
            'settings' => [
                'languages' => AINCC_Settings::get_language_config(),
                'categories' => AINCC_Settings::get_categories(),
                'aiProvider' => AINCC_Settings::get('ai_provider'),
                'autoPublishEnabled' => AINCC_Settings::get('auto_publish_enabled'),
            ],
            'i18n' => [
                'dashboard' => 'Панель',
                'create' => 'Создать статью',
                'fromUrl' => 'Из ссылки',
                'analytics' => 'Аналитика',
                'settings' => 'Настройки',
                'sources' => 'Источники',
                'moderation' => 'Модерация',
                'calendar' => 'Календарь',
                'approve' => 'Одобрить',
                'reject' => 'Отклонить',
                'publish' => 'Опубликовать',
                'schedule' => 'Запланировать',
                'edit' => 'Редактировать',
                'delete' => 'Удалить',
                'save' => 'Сохранить',
                'cancel' => 'Отмена',
                'loading' => 'Загрузка...',
            ],
        ]);
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // AI Provider settings
        register_setting('aincc_settings', 'aincc_ai_provider');
        register_setting('aincc_settings', 'aincc_deepseek_api_key');
        register_setting('aincc_settings', 'aincc_deepseek_model');
        register_setting('aincc_settings', 'aincc_openai_api_key');
        register_setting('aincc_settings', 'aincc_anthropic_api_key');

        // Media settings
        register_setting('aincc_settings', 'aincc_pexels_api_key');

        // Social media settings
        register_setting('aincc_settings', 'aincc_telegram_bot_token');
        register_setting('aincc_settings', 'aincc_telegram_channel_id');
        register_setting('aincc_settings', 'aincc_telegram_enabled');

        // Automation settings
        register_setting('aincc_settings', 'aincc_auto_publish_enabled');
        register_setting('aincc_settings', 'aincc_auto_publish_delay');
        register_setting('aincc_settings', 'aincc_fetch_interval');
    }

    /**
     * Render main page (React app container)
     */
    public function render_main_page() {
        ?>
        <div id="aincc-root" class="aincc-app">
            <div class="aincc-loading">
                <div class="aincc-spinner"></div>
                <p><?php _e('Loading AI News Control Center...', 'ai-news-center'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page (classic WP settings) - Russian
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings if form submitted
        if (isset($_POST['aincc_save_settings']) && check_admin_referer('aincc_settings_nonce')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>Настройки сохранены.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>AI Новости - Настройки</h1>

            <form method="post" action="">
                <?php wp_nonce_field('aincc_settings_nonce'); ?>

                <h2 class="title">AI Провайдер</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Провайдер</th>
                        <td>
                            <select name="ai_provider">
                                <option value="deepseek" <?php selected(AINCC_Settings::get('ai_provider'), 'deepseek'); ?>>DeepSeek (рекомендуется)</option>
                                <option value="openai" <?php selected(AINCC_Settings::get('ai_provider'), 'openai'); ?>>OpenAI GPT</option>
                                <option value="anthropic" <?php selected(AINCC_Settings::get('ai_provider'), 'anthropic'); ?>>Anthropic Claude</option>
                            </select>
                            <p class="description">DeepSeek - самый дешёвый (~$0.001 за статью)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">DeepSeek API Key</th>
                        <td>
                            <input type="password" name="deepseek_api_key" value="<?php echo esc_attr(AINCC_Settings::get('deepseek_api_key')); ?>" class="regular-text">
                            <p class="description">Получить на <a href="https://platform.deepseek.com" target="_blank">platform.deepseek.com</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">DeepSeek Модель</th>
                        <td>
                            <select name="deepseek_model">
                                <option value="deepseek-chat" <?php selected(AINCC_Settings::get('deepseek_model'), 'deepseek-chat'); ?>>DeepSeek Chat (быстрая)</option>
                                <option value="deepseek-reasoner" <?php selected(AINCC_Settings::get('deepseek_model'), 'deepseek-reasoner'); ?>>DeepSeek Reasoner (умная)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="password" name="openai_api_key" value="<?php echo esc_attr(AINCC_Settings::get('openai_api_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Anthropic API Key</th>
                        <td>
                            <input type="password" name="anthropic_api_key" value="<?php echo esc_attr(AINCC_Settings::get('anthropic_api_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <h2 class="title">Изображения</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Pexels API Key</th>
                        <td>
                            <input type="password" name="pexels_api_key" value="<?php echo esc_attr(AINCC_Settings::get('pexels_api_key')); ?>" class="regular-text">
                            <p class="description">Бесплатный ключ на <a href="https://www.pexels.com/api/" target="_blank">pexels.com/api</a></p>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Telegram</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Включить Telegram</th>
                        <td>
                            <label>
                                <input type="checkbox" name="telegram_enabled" value="1" <?php checked(AINCC_Settings::get('telegram_enabled')); ?>>
                                Публиковать статьи в Telegram канал
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bot Token</th>
                        <td>
                            <input type="password" name="telegram_bot_token" value="<?php echo esc_attr(AINCC_Settings::get('telegram_bot_token')); ?>" class="regular-text">
                            <p class="description">Создать бота через @BotFather в Telegram</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Channel ID</th>
                        <td>
                            <input type="text" name="telegram_channel_id" value="<?php echo esc_attr(AINCC_Settings::get('telegram_channel_id')); ?>" class="regular-text">
                            <p class="description">Например: @yourchannel или -100XXXXXXXXXX</p>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Автоматизация</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Авто-публикация</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_publish_enabled" value="1" <?php checked(AINCC_Settings::get('auto_publish_enabled')); ?>>
                                Автоматически публиковать одобренный контент
                            </label>
                            <p class="description">На старте лучше отключить для ручной проверки</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Задержка авто-публикации</th>
                        <td>
                            <input type="number" name="auto_publish_delay" value="<?php echo esc_attr(AINCC_Settings::get('auto_publish_delay', 10)); ?>" min="1" max="60" class="small-text">
                            минут
                            <p class="description">Время ожидания перед авто-публикацией (позволяет отменить)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Интервал сбора</th>
                        <td>
                            <input type="number" name="fetch_interval" value="<?php echo esc_attr(AINCC_Settings::get('fetch_interval', 5)); ?>" min="2" max="60" class="small-text">
                            минут
                            <p class="description">Как часто проверять RSS источники</p>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Пороги качества</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Порог фактчекинга</th>
                        <td>
                            <input type="number" name="fact_check_threshold" value="<?php echo esc_attr(AINCC_Settings::get('fact_check_threshold', 0.6)); ?>" min="0" max="1" step="0.1" class="small-text">
                            <p class="description">Статьи ниже этого порога требуют ручной проверки (0-1)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Порог доверия источнику</th>
                        <td>
                            <input type="number" name="source_trust_threshold" value="<?php echo esc_attr(AINCC_Settings::get('source_trust_threshold', 0.7)); ?>" min="0" max="1" step="0.1" class="small-text">
                            <p class="description">Источники ниже этого порога требуют ручной проверки (0-1)</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="aincc_save_settings" class="button button-primary" value="Сохранить настройки">
                    <a href="<?php echo admin_url('admin.php?page=ai-news-center'); ?>" class="button">Вернуться на панель</a>
                </p>
            </form>

            <hr>

            <h2>Тесты подключения</h2>
            <p>
                <button type="button" class="button button-primary" id="test-ai">Тест AI</button>
                <button type="button" class="button" id="test-telegram">Тест Telegram</button>
                <button type="button" class="button" id="test-pexels">Тест Pexels</button>
            </p>
            <div id="test-results" style="margin-top: 15px;"></div>

            <hr>

            <h2>Управление плагином</h2>
            <p>
                <button type="button" class="button button-primary" id="fetch-news-now" style="background: #28a745; border-color: #28a745; color: #fff;">
                    &#x21bb; Собрать новости сейчас
                </button>
                <button type="button" class="button" id="reinit-plugin" style="background: #0073aa; border-color: #0073aa; color: #fff;">
                    &#x21ba; Переинициализировать БД
                </button>
            </p>
            <p class="description">
                "Собрать новости" - запустит сбор RSS прямо сейчас.<br>
                "Переинициализировать БД" - пересоздаст таблицы и загрузит 35+ источников (если пусто).
            </p>
            <div id="action-results" style="margin-top: 15px;"></div>

            <script>
            jQuery(function($) {
                var restNonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

                function testConnection(endpoint, button, originalText) {
                    $(button).prop('disabled', true).text('Проверка...');

                    $.ajax({
                        url: '<?php echo rest_url('aincc/v1/'); ?>' + endpoint,
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', restNonce);
                        },
                        success: function(response) {
                            var msg = response.message || (response.success ? 'Подключение успешно!' : 'Ошибка подключения');
                            $('#test-results').html('<div class="notice notice-' + (response.success ? 'success' : 'error') + '"><p>' + msg + '</p></div>');
                        },
                        error: function(xhr) {
                            var errMsg = 'Ошибка подключения';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errMsg = xhr.responseJSON.message;
                            }
                            $('#test-results').html('<div class="notice notice-error"><p>' + errMsg + '</p></div>');
                        },
                        complete: function() {
                            $(button).prop('disabled', false).text(originalText);
                        }
                    });
                }

                $('#test-ai').click(function() { testConnection('test/ai', this, 'Тест AI'); });
                $('#test-telegram').click(function() { testConnection('test/telegram', this, 'Тест Telegram'); });
                $('#test-pexels').click(function() { testConnection('test/pexels', this, 'Тест Pexels'); });

                $('#fetch-news-now').click(function() {
                    var btn = $(this);
                    var originalText = btn.html();
                    btn.prop('disabled', true).html('Сбор...');

                    $.ajax({
                        url: '<?php echo rest_url('aincc/v1/'); ?>fetch/now',
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', restNonce);
                        },
                        success: function(response) {
                            $('#action-results').html('<div class="notice notice-success"><p>' + (response.message || 'Сбор новостей запущен!') + '</p></div>');
                        },
                        error: function(xhr) {
                            var errMsg = 'Ошибка запуска сбора';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errMsg = xhr.responseJSON.message;
                            }
                            $('#action-results').html('<div class="notice notice-error"><p>' + errMsg + '</p></div>');
                        },
                        complete: function() {
                            btn.prop('disabled', false).html(originalText);
                        }
                    });
                });

                $('#reinit-plugin').click(function() {
                    if (!confirm('Пересоздать таблицы БД и загрузить источники?')) {
                        return;
                    }

                    var btn = $(this);
                    var originalText = btn.html();
                    btn.prop('disabled', true).html('Инициализация...');

                    $.ajax({
                        url: '<?php echo rest_url('aincc/v1/'); ?>system/reinitialize',
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', restNonce);
                        },
                        success: function(response) {
                            $('#action-results').html('<div class="notice notice-success"><p>' + (response.message || 'Плагин переинициализирован!') + '</p></div>');
                        },
                        error: function(xhr) {
                            var errMsg = 'Ошибка инициализации';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errMsg = xhr.responseJSON.message;
                            }
                            $('#action-results').html('<div class="notice notice-error"><p>' + errMsg + '</p></div>');
                        },
                        complete: function() {
                            btn.prop('disabled', false).html(originalText);
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Save settings from form
     */
    private function save_settings() {
        $fields = [
            'ai_provider',
            'deepseek_api_key',
            'deepseek_model',
            'openai_api_key',
            'anthropic_api_key',
            'pexels_api_key',
            'telegram_bot_token',
            'telegram_channel_id',
            'auto_publish_delay',
            'fetch_interval',
            'fact_check_threshold',
            'source_trust_threshold',
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                AINCC_Settings::set($field, sanitize_text_field($_POST[$field]));
            }
        }

        // Checkboxes
        AINCC_Settings::set('telegram_enabled', isset($_POST['telegram_enabled']));
        AINCC_Settings::set('auto_publish_enabled', isset($_POST['auto_publish_enabled']));
    }

    /**
     * Add admin bar menu (Russian)
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $db = new AINCC_Database();
        $pending = $db->count_drafts_by_status(['pending_ok']);

        $title = 'AI Новости';
        if ($pending > 0) {
            $title .= sprintf(' <span class="aincc-badge">%d</span>', $pending);
        }

        $wp_admin_bar->add_node([
            'id' => 'aincc-admin-bar',
            'title' => $title,
            'href' => admin_url('admin.php?page=ai-news-center'),
        ]);

        $wp_admin_bar->add_node([
            'id' => 'aincc-pending',
            'parent' => 'aincc-admin-bar',
            'title' => sprintf('На проверке (%d)', $pending),
            'href' => admin_url('admin.php?page=ai-news-center&status=pending_ok'),
        ]);

        $wp_admin_bar->add_node([
            'id' => 'aincc-create',
            'parent' => 'aincc-admin-bar',
            'title' => 'Создать статью',
            'href' => admin_url('admin.php?page=ai-news-center-create'),
        ]);

        $wp_admin_bar->add_node([
            'id' => 'aincc-from-url',
            'parent' => 'aincc-admin-bar',
            'title' => 'Из ссылки',
            'href' => admin_url('admin.php?page=ai-news-center-from-url'),
        ]);
    }

    /**
     * Ajax quick stats
     */
    public function ajax_quick_stats() {
        check_ajax_referer('aincc_nonce', 'nonce');

        $db = new AINCC_Database();

        wp_send_json([
            'pending' => $db->count_drafts_by_status(['pending_ok']),
            'auto_ready' => $db->count_drafts_by_status(['auto_ready']),
            'published_today' => $db->count_drafts_by_status(['published']),
        ]);
    }
}
