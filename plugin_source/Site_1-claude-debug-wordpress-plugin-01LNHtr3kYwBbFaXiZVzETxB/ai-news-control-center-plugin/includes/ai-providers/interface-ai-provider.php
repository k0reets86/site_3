<?php
/**
 * AI Provider Interface
 * Defines the contract for all AI providers
 */

if (!defined('ABSPATH')) {
    exit;
}

interface AINCC_AI_Provider_Interface {

    /**
     * Generate text completion
     *
     * @param string $prompt The user prompt
     * @param string $system_prompt Optional system prompt
     * @param array $options Additional options (temperature, max_tokens, etc.)
     * @return array ['success' => bool, 'content' => string, 'usage' => array, 'error' => string]
     */
    public function complete($prompt, $system_prompt = '', $options = []);

    /**
     * Rewrite content in a specific style
     *
     * @param string $content Original content
     * @param string $style Writing style/instructions
     * @param string $language Target language
     * @return array ['success' => bool, 'content' => string, 'error' => string]
     */
    public function rewrite($content, $style, $language = 'de');

    /**
     * Translate content to target language
     *
     * @param string $content Content to translate
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @param array $glossary Key terms with specific translations
     * @return array ['success' => bool, 'content' => string, 'error' => string]
     */
    public function translate($content, $source_lang, $target_lang, $glossary = []);

    /**
     * Generate SEO metadata
     *
     * @param string $content Article content
     * @param string $language Target language
     * @return array ['success' => bool, 'title' => string, 'description' => string, 'keywords' => array]
     */
    public function generate_seo($content, $language = 'de');

    /**
     * Extract entities and keywords from content
     *
     * @param string $content Content to analyze
     * @return array ['success' => bool, 'entities' => array, 'keywords' => array]
     */
    public function extract_entities($content);

    /**
     * Classify content into categories
     *
     * @param string $content Content to classify
     * @param array $categories Available categories
     * @return array ['success' => bool, 'category' => string, 'confidence' => float]
     */
    public function classify($content, $categories);

    /**
     * Summarize content
     *
     * @param string $content Content to summarize
     * @param int $max_length Maximum summary length
     * @param string $language Target language
     * @return array ['success' => bool, 'summary' => string]
     */
    public function summarize($content, $max_length = 200, $language = 'de');

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name();

    /**
     * Test connection to API
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function test_connection();
}
