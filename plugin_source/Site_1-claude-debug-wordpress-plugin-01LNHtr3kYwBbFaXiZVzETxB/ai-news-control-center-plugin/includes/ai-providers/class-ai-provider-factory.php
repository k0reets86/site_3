<?php
/**
 * AI Provider Factory
 * Creates appropriate AI provider instance based on settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_AI_Provider_Factory {

    /**
     * Available providers
     */
    private static $providers = [
        'deepseek' => 'AINCC_DeepSeek_Provider',
        'openai' => 'AINCC_OpenAI_Provider',
        'anthropic' => 'AINCC_Anthropic_Provider',
    ];

    /**
     * Create provider instance
     *
     * @param string|null $provider Provider name (null = use settings)
     * @return AINCC_AI_Provider_Interface
     */
    public static function create($provider = null) {
        if ($provider === null) {
            $provider = AINCC_Settings::get('ai_provider', 'deepseek');
        }

        if (!isset(self::$providers[$provider])) {
            AINCC_Logger::error("Unknown AI provider: {$provider}, falling back to DeepSeek");
            $provider = 'deepseek';
        }

        $class = self::$providers[$provider];
        $config = self::get_provider_config($provider);

        return new $class($config);
    }

    /**
     * Get configuration for provider
     */
    private static function get_provider_config($provider) {
        switch ($provider) {
            case 'deepseek':
                return [
                    'api_key' => AINCC_Settings::get('deepseek_api_key'),
                    'model' => AINCC_Settings::get('deepseek_model', 'deepseek-chat'),
                    'base_url' => AINCC_Settings::get('deepseek_base_url', 'https://api.deepseek.com'),
                ];

            case 'openai':
                return [
                    'api_key' => AINCC_Settings::get('openai_api_key'),
                    'model' => AINCC_Settings::get('openai_model', 'gpt-4o-mini'),
                    'base_url' => 'https://api.openai.com/v1',
                ];

            case 'anthropic':
                return [
                    'api_key' => AINCC_Settings::get('anthropic_api_key'),
                    'model' => AINCC_Settings::get('anthropic_model', 'claude-sonnet-4-20250514'),
                    'base_url' => 'https://api.anthropic.com',
                ];

            default:
                return [];
        }
    }

    /**
     * Get all available providers
     */
    public static function get_available_providers() {
        return [
            'deepseek' => [
                'name' => 'DeepSeek',
                'description' => 'Cost-effective AI with good multilingual support',
                'models' => [
                    'deepseek-chat' => 'DeepSeek Chat (Fast, Cheap)',
                    'deepseek-reasoner' => 'DeepSeek Reasoner (Smarter)',
                ],
                'pricing' => '$0.14/1M input, $0.28/1M output',
            ],
            'openai' => [
                'name' => 'OpenAI',
                'description' => 'Industry standard, excellent quality',
                'models' => [
                    'gpt-4o-mini' => 'GPT-4o Mini (Fast, Affordable)',
                    'gpt-4o' => 'GPT-4o (Best Quality)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                ],
                'pricing' => '$0.15-10/1M tokens depending on model',
            ],
            'anthropic' => [
                'name' => 'Anthropic Claude',
                'description' => 'Excellent for long content and analysis',
                'models' => [
                    'claude-sonnet-4-20250514' => 'Claude Sonnet 4 (Balanced)',
                    'claude-opus-4-20250514' => 'Claude Opus 4 (Best)',
                    'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku (Fast)',
                ],
                'pricing' => '$3-15/1M tokens depending on model',
            ],
        ];
    }

    /**
     * Test all configured providers
     */
    public static function test_all_providers() {
        $results = [];

        foreach (array_keys(self::$providers) as $provider_name) {
            try {
                $provider = self::create($provider_name);
                $results[$provider_name] = $provider->test_connection();
            } catch (Exception $e) {
                $results[$provider_name] = [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
