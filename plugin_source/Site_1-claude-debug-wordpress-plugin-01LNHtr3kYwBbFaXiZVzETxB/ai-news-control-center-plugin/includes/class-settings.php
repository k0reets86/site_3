<?php
/**
 * Settings Handler
 * Manages plugin settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Settings {

    /**
     * Get a setting value
     */
    public static function get($key, $default = null) {
        $value = get_option('aincc_' . $key, $default);

        // Handle JSON stored values
        if (is_string($value) && self::is_json($value)) {
            return json_decode($value, true);
        }

        return $value;
    }

    /**
     * Set a setting value
     */
    public static function set($key, $value) {
        // Convert arrays to JSON
        if (is_array($value)) {
            $value = json_encode($value);
        }

        return update_option('aincc_' . $key, $value);
    }

    /**
     * Delete a setting
     */
    public static function delete($key) {
        return delete_option('aincc_' . $key);
    }

    /**
     * Get all settings
     */
    public static function get_all() {
        return [
            // AI Provider Settings
            'ai_provider' => self::get('ai_provider', 'deepseek'),
            'deepseek_api_key' => self::get('deepseek_api_key', ''),
            'deepseek_model' => self::get('deepseek_model', 'deepseek-chat'),
            'deepseek_base_url' => self::get('deepseek_base_url', 'https://api.deepseek.com'),
            'openai_api_key' => self::get('openai_api_key', ''),
            'openai_model' => self::get('openai_model', 'gpt-4o-mini'),
            'anthropic_api_key' => self::get('anthropic_api_key', ''),
            'anthropic_model' => self::get('anthropic_model', 'claude-sonnet-4-20250514'),

            // Media Settings
            'pexels_api_key' => self::get('pexels_api_key', ''),
            'unsplash_api_key' => self::get('unsplash_api_key', ''),
            'use_source_images' => self::get('use_source_images', true),

            // Social Media Settings
            'telegram_bot_token' => self::get('telegram_bot_token', ''),
            'telegram_channel_id' => self::get('telegram_channel_id', ''),
            'telegram_enabled' => self::get('telegram_enabled', false),
            'facebook_page_id' => self::get('facebook_page_id', ''),
            'facebook_access_token' => self::get('facebook_access_token', ''),
            'facebook_enabled' => self::get('facebook_enabled', false),
            'instagram_account_id' => self::get('instagram_account_id', ''),
            'instagram_enabled' => self::get('instagram_enabled', false),

            // Content Settings
            'default_language' => self::get('default_language', 'de'),
            'target_languages' => self::get('target_languages', ['de', 'ua', 'ru', 'en']),
            'default_category' => self::get('default_category', 'Nachrichten'),
            'geo_focus' => self::get('geo_focus', ['München', 'Bayern', 'Deutschland']),

            // Automation Settings
            'auto_publish_enabled' => self::get('auto_publish_enabled', false),
            'auto_publish_delay' => self::get('auto_publish_delay', 10),
            'fetch_interval' => self::get('fetch_interval', 5),
            'max_articles_per_day' => self::get('max_articles_per_day', 50),
            'categories_require_approval' => self::get('categories_require_approval', ['politik', 'wirtschaft']),

            // Quality Thresholds
            'fact_check_threshold' => self::get('fact_check_threshold', 0.6),
            'source_trust_threshold' => self::get('source_trust_threshold', 0.7),
            'translation_quality_threshold' => self::get('translation_quality_threshold', 0.7),
            'min_content_length' => self::get('min_content_length', 200),

            // SEO Settings
            'seo_title_max_length' => self::get('seo_title_max_length', 60),
            'meta_description_max_length' => self::get('meta_description_max_length', 155),
            'generate_schema_markup' => self::get('generate_schema_markup', true),

            // System Settings
            'debug_mode' => self::get('debug_mode', false),
            'log_retention_days' => self::get('log_retention_days', 30),

            // Cron Settings
            'fetch_interval' => self::get('fetch_interval', 5),
            'process_interval' => self::get('process_interval', 2),
            'auto_publish_interval' => self::get('auto_publish_interval', 5),
            'batch_size' => self::get('batch_size', 5),

            // AI Prompts Settings
            'prompt_rewrite_style' => self::get('prompt_rewrite_style', 'Профессиональная новостная статья. Факты, ясность, без эмоций. Стиль Deutsche Welle. Для украинской аудитории в Германии.'),
            'prompt_tone' => self::get('prompt_tone', 'neutral'),
            'prompt_custom_instructions' => self::get('prompt_custom_instructions', ''),
            'prompt_seo_focus' => self::get('prompt_seo_focus', 'Украинцы в Германии, миграция, интеграция'),
            'prompt_image_style' => self::get('prompt_image_style', 'professional news photography'),
            'prompt_translation_notes' => self::get('prompt_translation_notes', 'Сохраняй немецкие термины (BAMF, Jobcenter) с пояснениями'),

            // Location Priority Settings
            'location_priority' => self::get('location_priority', ['München', 'Bayern', 'Deutschland', 'Europa', 'International', 'Ukraine']),
            'location_priority_enabled' => self::get('location_priority_enabled', true),
        ];
    }

    /**
     * Update multiple settings
     */
    public static function update_all($settings) {
        $allowed_keys = array_keys(self::get_all());

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_keys)) {
                self::set($key, $value);
            }
        }

        return true;
    }

    /**
     * Get AI provider configuration
     */
    public static function get_ai_config() {
        $provider = self::get('ai_provider', 'deepseek');

        switch ($provider) {
            case 'deepseek':
                return [
                    'provider' => 'deepseek',
                    'api_key' => self::get('deepseek_api_key'),
                    'model' => self::get('deepseek_model', 'deepseek-chat'),
                    'base_url' => self::get('deepseek_base_url', 'https://api.deepseek.com'),
                ];

            case 'openai':
                return [
                    'provider' => 'openai',
                    'api_key' => self::get('openai_api_key'),
                    'model' => self::get('openai_model', 'gpt-4o-mini'),
                    'base_url' => 'https://api.openai.com/v1',
                ];

            case 'anthropic':
                return [
                    'provider' => 'anthropic',
                    'api_key' => self::get('anthropic_api_key'),
                    'model' => self::get('anthropic_model', 'claude-sonnet-4-20250514'),
                    'base_url' => 'https://api.anthropic.com',
                ];

            default:
                return [
                    'provider' => 'deepseek',
                    'api_key' => self::get('deepseek_api_key'),
                    'model' => 'deepseek-chat',
                    'base_url' => 'https://api.deepseek.com',
                ];
        }
    }

    /**
     * Check if JSON string
     */
    private static function is_json($string) {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate API keys
     */
    public static function validate_api_keys() {
        $errors = [];

        // Check current AI provider key
        $ai_config = self::get_ai_config();
        if (empty($ai_config['api_key'])) {
            $errors[] = sprintf(
                __('API key for %s is not set', 'ai-news-center'),
                ucfirst($ai_config['provider'])
            );
        }

        // Check Pexels key if enabled
        if (empty(self::get('pexels_api_key'))) {
            $errors[] = __('Pexels API key is not set', 'ai-news-center');
        }

        // Check Telegram if enabled
        if (self::get('telegram_enabled') && empty(self::get('telegram_bot_token'))) {
            $errors[] = __('Telegram bot token is not set', 'ai-news-center');
        }

        return $errors;
    }

    /**
     * Get language configuration
     */
    public static function get_language_config() {
        return [
            'de' => [
                'name' => 'Deutsch',
                'flag' => '🇩🇪',
                'locale' => 'de_DE',
                'voice' => 'de-DE-Neural2-F',
            ],
            'ua' => [
                'name' => 'Українська',
                'flag' => '🇺🇦',
                'locale' => 'uk_UA',
                'voice' => 'uk-UA-Standard-A',
            ],
            'ru' => [
                'name' => 'Русский',
                'flag' => '🇷🇺',
                'locale' => 'ru_RU',
                'voice' => 'ru-RU-Wavenet-A',
            ],
            'en' => [
                'name' => 'English',
                'flag' => '🇬🇧',
                'locale' => 'en_GB',
                'voice' => 'en-GB-Neural2-F',
            ],
        ];
    }

    /**
     * Get category configuration
     */
    public static function get_categories() {
        return [
            'politik' => [
                'name_de' => 'Politik',
                'name_ua' => 'Політика',
                'name_ru' => 'Политика',
                'name_en' => 'Politics',
                'requires_approval' => true,
                'priority' => 'hot',
            ],
            'wirtschaft' => [
                'name_de' => 'Wirtschaft',
                'name_ua' => 'Економіка',
                'name_ru' => 'Экономика',
                'name_en' => 'Economy',
                'requires_approval' => true,
                'priority' => 'hot',
            ],
            'gesellschaft' => [
                'name_de' => 'Gesellschaft',
                'name_ua' => 'Суспільство',
                'name_ru' => 'Общество',
                'name_en' => 'Society',
                'requires_approval' => false,
                'priority' => 'warm',
            ],
            'lokales' => [
                'name_de' => 'Lokales',
                'name_ua' => 'Місцеві новини',
                'name_ru' => 'Местные новости',
                'name_en' => 'Local News',
                'requires_approval' => false,
                'priority' => 'warm',
            ],
            'kultur' => [
                'name_de' => 'Kultur',
                'name_ua' => 'Культура',
                'name_ru' => 'Культура',
                'name_en' => 'Culture',
                'requires_approval' => false,
                'priority' => 'cold',
            ],
            'sport' => [
                'name_de' => 'Sport',
                'name_ua' => 'Спорт',
                'name_ru' => 'Спорт',
                'name_en' => 'Sports',
                'requires_approval' => false,
                'priority' => 'cold',
            ],
            'migration' => [
                'name_de' => 'Migration & Integration',
                'name_ua' => 'Міграція та інтеграція',
                'name_ru' => 'Миграция и интеграция',
                'name_en' => 'Migration & Integration',
                'requires_approval' => false,
                'priority' => 'hot',
            ],
            'verkehr' => [
                'name_de' => 'Verkehr',
                'name_ua' => 'Транспорт',
                'name_ru' => 'Транспорт',
                'name_en' => 'Transport',
                'requires_approval' => false,
                'priority' => 'warm',
            ],
            'wetter' => [
                'name_de' => 'Wetter & Warnungen',
                'name_ua' => 'Погода та попередження',
                'name_ru' => 'Погода и предупреждения',
                'name_en' => 'Weather & Alerts',
                'requires_approval' => false,
                'priority' => 'hot',
            ],
        ];
    }
}
