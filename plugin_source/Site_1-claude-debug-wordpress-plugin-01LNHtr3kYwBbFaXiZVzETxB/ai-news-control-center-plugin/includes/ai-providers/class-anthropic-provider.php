<?php
/**
 * Anthropic Provider
 * Implementation for Claude API
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Anthropic_Provider implements AINCC_AI_Provider_Interface {

    private $api_key;
    private $model;
    private $base_url = 'https://api.anthropic.com';

    /**
     * Constructor
     */
    public function __construct($config) {
        $this->api_key = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'claude-sonnet-4-20250514';
        if (!empty($config['base_url'])) {
            $this->base_url = $config['base_url'];
        }
    }

    /**
     * Get provider name
     */
    public function get_name() {
        return 'Anthropic Claude';
    }

    /**
     * Make API request
     */
    private function request($endpoint, $data) {
        $start_time = microtime(true);

        $url = rtrim($this->base_url, '/') . $endpoint;

        $response = wp_remote_post($url, [
            'timeout' => 120,
            'headers' => [
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
        ]);

        $duration = (microtime(true) - $start_time) * 1000;

        if (is_wp_error($response)) {
            AINCC_Logger::error('Anthropic API Error', [
                'error' => $response->get_error_message(),
            ]);

            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        AINCC_Logger::api_request('Anthropic', $endpoint, $data, $body, $duration);

        if ($status_code !== 200) {
            $error = $body['error']['message'] ?? 'Unknown API error';
            return [
                'success' => false,
                'error' => $error,
            ];
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    /**
     * Generate text completion
     */
    public function complete($prompt, $system_prompt = '', $options = []) {
        $data = [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? 4000,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if (!empty($system_prompt)) {
            $data['system'] = $system_prompt;
        }

        // Claude uses 'temperature' but it's optional
        if (isset($options['temperature'])) {
            $data['temperature'] = $options['temperature'];
        }

        $response = $this->request('/v1/messages', $data);

        if (!$response['success']) {
            return $response;
        }

        // Claude returns content in a different format
        $content = '';
        if (isset($response['data']['content']) && is_array($response['data']['content'])) {
            foreach ($response['data']['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        return [
            'success' => true,
            'content' => $content,
            'usage' => $response['data']['usage'] ?? [],
        ];
    }

    /**
     * Rewrite content
     */
    public function rewrite($content, $style, $language = 'de') {
        $lang_names = ['de' => 'German', 'ua' => 'Ukrainian', 'ru' => 'Russian', 'en' => 'English'];
        $lang_name = $lang_names[$language] ?? 'German';

        $system_prompt = <<<PROMPT
You are a professional news editor for a platform serving Ukrainians in Munich/Bavaria/Germany.

Create UNIQUE content in {$lang_name}:
- No plagiarism, no direct quotes except very short official statements
- Factual and neutral
- Target: Ukrainian audience (A2-B2 {$lang_name} level)
- Style: {$style}

Output only the rewritten article with Title, Lead, and Body sections.
PROMPT;

        return $this->complete($content, $system_prompt, ['temperature' => 0.7, 'max_tokens' => 4000]);
    }

    /**
     * Translate content
     */
    public function translate($content, $source_lang, $target_lang, $glossary = []) {
        $lang_names = ['de' => 'German', 'ua' => 'Ukrainian', 'ru' => 'Russian', 'en' => 'English'];
        $source_name = $lang_names[$source_lang] ?? $source_lang;
        $target_name = $lang_names[$target_lang] ?? $target_lang;

        $glossary_text = '';
        if (!empty($glossary)) {
            $glossary_text = "\n\nUse this glossary:\n";
            foreach ($glossary as $term => $translation) {
                $glossary_text .= "- {$term} → {$translation}\n";
            }
        }

        $system_prompt = "You are a professional translator. Translate from {$source_name} to {$target_name}. "
            . "Keep HTML structure intact. Natural, fluent translation. {$glossary_text}";

        return $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 6000]);
    }

    /**
     * Generate SEO metadata
     */
    public function generate_seo($content, $language = 'de') {
        $system_prompt = 'Generate SEO metadata. Return ONLY valid JSON: {"title": "max 60 chars", "description": "max 155 chars", "keywords": ["kw1", "kw2"], "slug": "url-slug"}';
        $result = $this->complete("Generate SEO for:\n\n" . substr($content, 0, 2000), $system_prompt, ['temperature' => 0.5, 'max_tokens' => 500]);

        if (!$result['success']) return $result;

        if (preg_match('/\{[\s\S]*\}/', $result['content'], $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return [
                    'success' => true,
                    'title' => $parsed['title'] ?? '',
                    'description' => $parsed['description'] ?? '',
                    'keywords' => $parsed['keywords'] ?? [],
                    'slug' => $parsed['slug'] ?? '',
                ];
            }
        }

        return ['success' => false, 'error' => 'Failed to parse SEO response'];
    }

    /**
     * Extract entities
     */
    public function extract_entities($content) {
        $system_prompt = 'Extract named entities and keywords. Return ONLY valid JSON: {"entities": {"persons": [], "organizations": [], "locations": [], "dates": []}, "keywords": [], "geo": [], "category": "string", "sentiment": 0.0}';
        $result = $this->complete("Analyze:\n\n" . substr($content, 0, 3000), $system_prompt, ['temperature' => 0.3]);

        if (!$result['success']) return $result;

        if (preg_match('/\{[\s\S]*\}/', $result['content'], $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return [
                    'success' => true,
                    'entities' => $parsed['entities'] ?? [],
                    'keywords' => $parsed['keywords'] ?? [],
                    'geo' => $parsed['geo'] ?? [],
                    'category' => $parsed['category'] ?? '',
                    'sentiment' => $parsed['sentiment'] ?? 0,
                ];
            }
        }

        return ['success' => false, 'error' => 'Failed to parse'];
    }

    /**
     * Classify content
     */
    public function classify($content, $categories) {
        $cats = implode(', ', array_keys($categories));
        $system_prompt = "Classify into one of: {$cats}. Return ONLY JSON: {\"category\": \"key\", \"confidence\": 0.9, \"tags\": []}";
        $result = $this->complete(substr($content, 0, 2000), $system_prompt, ['temperature' => 0.3]);

        if (!$result['success']) return $result;

        if (preg_match('/\{[\s\S]*\}/', $result['content'], $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return [
                    'success' => true,
                    'category' => $parsed['category'] ?? '',
                    'confidence' => $parsed['confidence'] ?? 0.5,
                    'tags' => $parsed['tags'] ?? [],
                ];
            }
        }

        return ['success' => false, 'error' => 'Failed to classify'];
    }

    /**
     * Summarize content
     */
    public function summarize($content, $max_length = 200, $language = 'de') {
        $lang_names = ['de' => 'German', 'ua' => 'Ukrainian', 'ru' => 'Russian', 'en' => 'English'];
        $lang_name = $lang_names[$language] ?? 'German';

        $system_prompt = "Summarize in {$lang_name}, max {$max_length} characters. Output only the summary.";
        $result = $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 300]);

        if (!$result['success']) return $result;

        return ['success' => true, 'summary' => substr($result['content'], 0, $max_length)];
    }

    /**
     * Test connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return ['success' => false, 'message' => 'API key not configured'];
        }

        $result = $this->complete('Say OK', '', ['max_tokens' => 10]);
        return $result['success']
            ? ['success' => true, 'message' => 'Connection successful', 'model' => $this->model]
            : ['success' => false, 'message' => $result['error'] ?? 'Failed'];
    }
}
