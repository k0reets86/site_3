<?php
/**
 * DeepSeek AI Provider
 * Professional news rewriting with Deutsche Welle quality standards
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_DeepSeek_Provider implements AINCC_AI_Provider_Interface {

    private $api_key;
    private $model;
    private $base_url;

    /**
     * Constructor
     */
    public function __construct($config) {
        $this->api_key = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'deepseek-chat';
        $this->base_url = $config['base_url'] ?? 'https://api.deepseek.com';
    }

    /**
     * Get provider name
     */
    public function get_name() {
        return 'DeepSeek';
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
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
        ]);

        $duration = (microtime(true) - $start_time) * 1000;

        if (is_wp_error($response)) {
            AINCC_Logger::error('DeepSeek API Error', [
                'error' => $response->get_error_message(),
                'endpoint' => $endpoint,
            ]);

            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        AINCC_Logger::api_request('DeepSeek', $endpoint, $data, $body, $duration);

        if ($status_code !== 200) {
            $error = $body['error']['message'] ?? 'Unknown API error';
            AINCC_Logger::error('DeepSeek API Error', [
                'status' => $status_code,
                'error' => $error,
            ]);

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
        $messages = [];

        if (!empty($system_prompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $system_prompt,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $data = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4000,
        ];

        $response = $this->request('/v1/chat/completions', $data);

        if (!$response['success']) {
            return $response;
        }

        return [
            'success' => true,
            'content' => $response['data']['choices'][0]['message']['content'] ?? '',
            'usage' => $response['data']['usage'] ?? [],
        ];
    }

    /**
     * Get professional rewrite system prompt - Deutsche Welle quality
     */
    private function get_rewrite_system_prompt($language, $style_hints = '') {
        $lang_configs = [
            'de' => [
                'name' => 'German',
                'native_name' => 'Deutsch',
                'level' => 'B1-B2',
                'examples' => [
                    'clear' => 'Die Bundesregierung hat neue Regelungen beschlossen.',
                    'avoid' => 'Die Regierung fasste in einer außerordentlichen Kabinettsitzung weitreichende Beschlüsse.',
                ],
            ],
            'ua' => [
                'name' => 'Ukrainian',
                'native_name' => 'Українська',
                'level' => 'native',
                'examples' => [
                    'clear' => 'Уряд Німеччини прийняв нові правила.',
                    'avoid' => 'Федеральний уряд ФРН на позачерговому засіданні кабінету ухвалив далекосяжні рішення.',
                ],
            ],
            'ru' => [
                'name' => 'Russian',
                'native_name' => 'Русский',
                'level' => 'native',
                'examples' => [
                    'clear' => 'Правительство Германии приняло новые правила.',
                    'avoid' => 'Федеральное правительство на внеочередном заседании кабинета приняло далеко идущие решения.',
                ],
            ],
            'en' => [
                'name' => 'English',
                'native_name' => 'English',
                'level' => 'B1-B2',
                'examples' => [
                    'clear' => 'The German government has announced new regulations.',
                    'avoid' => 'The Federal Cabinet convened an extraordinary session to deliberate far-reaching legislative amendments.',
                ],
            ],
        ];

        $config = $lang_configs[$language] ?? $lang_configs['de'];

        return <<<PROMPT
# ROLE & IDENTITY
You are a senior editor at Deutsche Welle ({$config['native_name']} edition), specializing in news for Ukrainian audiences in Germany. You have 15+ years of journalism experience and deep understanding of both German society and Ukrainian diaspora needs.

# YOUR MISSION
Transform raw news into compelling, accessible, and genuinely helpful articles that inform and empower Ukrainian readers in Germany.

# CORE PRINCIPLES

## 1. ABSOLUTE ORIGINALITY
- Rewrite 100% in your own words - NEVER copy-paste
- Only use direct quotes for official statements (max 10 words, with quotation marks)
- Your article must be UNIQUELY YOURS while preserving all facts
- If caught plagiarizing, your career is over - treat this seriously

## 2. CLARITY IS KING (Deutsche Welle Standard)
Language level: {$config['level']} for non-native speakers

✅ GOOD: "{$config['examples']['clear']}"
❌ AVOID: "{$config['examples']['avoid']}"

Rules:
- One idea per sentence (15-20 words max)
- Active voice always ("The government announced" not "It was announced")
- Simple words over complex ones
- Explain German bureaucratic terms in parentheses
- NO jargon, NO academic language, NO officialese
- Break down complex topics into digestible steps

## 3. STRUCTURE FOR SCANNABILITY
```
HEADLINE: Informative, 8-12 words, main point clear
LEAD: Who/What/When/Where in 2-3 sentences (50-80 words)
BODY: Inverted pyramid - most important first
```

Each paragraph: 2-4 sentences, one main idea
Use subheadings to break long articles
Bullet points for lists, steps, or requirements

## 4. RELEVANCE TO UKRAINIAN AUDIENCE
Always answer: "Why should a Ukrainian in Munich/Bavaria care about this?"

Priorities:
1. 🏠 PRACTICAL: Housing, work, documents, integration
2. 💶 FINANCIAL: Benefits, jobs, costs, rights
3. 📋 LEGAL: Visa, residence, regulations
4. 🏫 FAMILY: Schools, healthcare, children
5. 🚌 DAILY LIFE: Transport, shopping, services
6. 🇺🇦 UKRAINE: War updates, family support, returns
7. 🌍 CONTEXT: German politics affecting migrants

## 5. TONE & VOICE
- Informative but warm (like explaining to a friend)
- Respectful (no condescension)
- Practical (focus on actionable information)
- Neutral on politics (present facts, not opinions)
- Empathetic without being emotional

## 6. GERMAN TERMINOLOGY HANDLING
When using German terms, format as:
"Aufenthaltstitel (residence permit / посвідка на проживання)"

Keep German names for:
- Institutions: BAMF, Jobcenter, Ausländerbehörde
- Laws: Bürgergeld, Aufenthaltsgesetz
- Places: Rathaus, Landratsamt
- Forms: Anmeldung, Abmeldung

Always explain what these mean the first time!

## 7. FACTUAL ACCURACY
- Only include verifiable facts from the source
- If something is unclear, say "According to [source]..."
- Distinguish between confirmed facts and rumors/plans
- Use present/past tense appropriately (is vs. will be vs. was)
- Include dates, numbers, and specific details

## 8. CALL TO ACTION
End articles with practical next steps when relevant:
- Where to get more info (websites, phone numbers)
- What documents are needed
- Deadlines to remember
- Who to contact

# OUTPUT FORMAT

Write in {$config['name']} ({$config['native_name']}).

IMPORTANT: Output ONLY the article text. Do NOT use JSON format. Do NOT wrap in code blocks.

Structure your output EXACTLY as follows (use these exact XML-like tags):

<title>[Compelling headline, 8-12 words]</title>

<lead>
[2-3 sentences summarizing the key news. Answer: What happened? Who is affected? Why does it matter?]
</lead>

<body>
[Main article body with clear paragraphs. Use <h2> for section headers if article is long.]

[Include relevant context for Ukrainian audience]

[End with practical information or next steps if applicable]
</body>

NEVER output JSON like {"title":..., "body":...}. Use ONLY the <title>, <lead>, <body> tags above.

{$style_hints}

# QUALITY CHECKLIST (Apply mentally before submitting)
☐ Is every sentence clear to someone with {$config['level']} language level?
☐ Did I explain all German bureaucratic terms?
☐ Is the relevance to Ukrainians in Germany clear?
☐ Are all facts accurate and properly attributed?
☐ Is the structure easy to scan?
☐ Would this pass Deutsche Welle editorial review?
PROMPT;
    }

    /**
     * Rewrite content - Professional Deutsche Welle quality
     */
    public function rewrite($content, $style, $language = 'de') {
        $system_prompt = $this->get_rewrite_system_prompt($language, $style);

        $prompt = <<<PROMPT
Transform this source material into a professional news article:

---SOURCE MATERIAL---
{$content}
---END SOURCE---

Requirements:
1. Completely rewrite in your own words
2. Keep all important facts
3. Make it relevant for Ukrainians in Germany
4. Follow Deutsche Welle quality standards
5. Include practical information if applicable

Write the article now:
PROMPT;

        return $this->complete($prompt, $system_prompt, ['temperature' => 0.7, 'max_tokens' => 4000]);
    }

    /**
     * Get translation system prompt
     */
    private function get_translation_system_prompt($source_lang, $target_lang, $glossary = []) {
        $lang_names = [
            'de' => ['name' => 'German', 'native' => 'Deutsch'],
            'ua' => ['name' => 'Ukrainian', 'native' => 'Українська'],
            'ru' => ['name' => 'Russian', 'native' => 'Русский'],
            'en' => ['name' => 'English', 'native' => 'English'],
        ];

        $source = $lang_names[$source_lang] ?? ['name' => $source_lang, 'native' => $source_lang];
        $target = $lang_names[$target_lang] ?? ['name' => $target_lang, 'native' => $target_lang];

        // Comprehensive glossary for Ukrainian audience in Germany
        $full_glossary = $this->get_comprehensive_glossary($target_lang);
        $glossary = array_merge($full_glossary, $glossary);

        $glossary_text = '';
        if (!empty($glossary)) {
            $glossary_text = "\n\n## MANDATORY GLOSSARY - Use these exact translations:\n";
            foreach ($glossary as $term => $translation) {
                $glossary_text .= "• {$term} → {$translation}\n";
            }
        }

        return <<<PROMPT
# ROLE
You are a professional translator specialized in news content for Ukrainian diaspora in Germany. You combine linguistic precision with cultural adaptation.

# TASK
Translate from {$source['name']} ({$source['native']}) to {$target['name']} ({$target['native']}).

# TRANSLATION PRINCIPLES

## 1. MEANING OVER WORDS
- Translate IDEAS, not word-for-word
- Adapt idioms and expressions naturally
- Preserve the TONE and IMPACT of original
- Result should read as if originally written in {$target['name']}

## 2. CULTURAL ADAPTATION
- Convert cultural references when needed
- Keep German institution names (BAMF, Jobcenter) but explain if not already explained
- Dates: Keep DD.MM.YYYY format
- Numbers: Use local conventions
- Currency: Keep EUR/€

## 3. PRESERVE STRUCTURE
- Keep HTML tags exactly as they are (<p>, <h2>, <ul>, <li>, etc.)
- Maintain paragraph breaks
- Preserve bullet points and lists
- Keep <title>, <lead>, <body> tags if present
- NEVER convert to JSON format - keep the same structure as input

## 4. DO NOT TRANSLATE
- URLs and links
- Email addresses
- German institution names (BAMF, Bundesregierung, etc.)
- Proper names of people
- Names of laws and regulations in German
- Technical codes and reference numbers

## 5. QUALITY STANDARDS
- Natural, fluent {$target['name']}
- No awkward constructions
- No "translationese"
- Appropriate register for news
{$glossary_text}

# OUTPUT
Provide ONLY the translated text. No commentary, no explanations.
PROMPT;
    }

    /**
     * Get comprehensive glossary for Ukrainian audience
     */
    private function get_comprehensive_glossary($target_lang) {
        $glossaries = [
            'de' => [
                // Keep German as-is for German articles
            ],
            'ua' => [
                // Documents & Status
                'Aufenthaltstitel' => 'посвідка на проживання (Aufenthaltstitel)',
                'Aufenthaltserlaubnis' => 'дозвіл на перебування',
                'Niederlassungserlaubnis' => 'постійний дозвіл на проживання',
                'Duldung' => 'толеранс/тимчасова відстрочка депортації (Duldung)',
                'Visum' => 'віза',
                'Reiseausweis' => 'проїзний документ',

                // Institutions
                'BAMF' => 'BAMF (Федеральне відомство міграції та біженців)',
                'Bundesregierung' => 'Федеральний уряд Німеччини',
                'Ausländerbehörde' => 'відділ у справах іноземців (Ausländerbehörde)',
                'Jobcenter' => 'Jobcenter (центр зайнятості)',
                'Arbeitsagentur' => 'Arbeitsagentur (агентство праці)',
                'Sozialamt' => 'соціальна служба (Sozialamt)',
                'Rathaus' => 'ратуша/міська адміністрація',
                'Landratsamt' => 'районна адміністрація (Landratsamt)',
                'Finanzamt' => 'податкова служба (Finanzamt)',
                'Standesamt' => 'РАЦС (Standesamt)',
                'Jugendamt' => 'служба у справах молоді (Jugendamt)',
                'Gesundheitsamt' => 'санепідстанція (Gesundheitsamt)',

                // Benefits & Money
                'Bürgergeld' => 'Bürgergeld (соціальна допомога)',
                'Kindergeld' => 'Kindergeld (допомога на дитину)',
                'Elterngeld' => 'Elterngeld (батьківська допомога)',
                'Wohngeld' => 'Wohngeld (житлова субсидія)',
                'BAföG' => 'BAföG (стипендія на навчання)',
                'Arbeitslosengeld' => 'допомога по безробіттю',
                'Sozialhilfe' => 'соціальна допомога',
                'Grundsicherung' => 'базове забезпечення',

                // Procedures
                'Anmeldung' => 'реєстрація за місцем проживання (Anmeldung)',
                'Abmeldung' => 'зняття з реєстрації (Abmeldung)',
                'Antrag' => 'заява/заявка',
                'Termin' => 'призначена зустріч/термін',
                'Bescheid' => 'офіційне рішення/повідомлення',
                'Einspruch' => 'заперечення/апеляція',
                'Widerspruch' => 'оскарження',

                // Housing
                'Mietvertrag' => 'договір оренди',
                'Kaution' => 'застава за оренду',
                'Nebenkosten' => 'комунальні платежі',
                'WG' => 'спільна квартира (WG)',
                'Wohnungsamt' => 'житловий відділ',

                // Work
                'Arbeitsvertrag' => 'трудовий договір',
                'Minijob' => 'Minijob (підробіток до 520€)',
                'Teilzeit' => 'часткова зайнятість',
                'Vollzeit' => 'повна зайнятість',
                'Probezeit' => 'випробувальний термін',
                'Kündigung' => 'звільнення',
                'Gehalt' => 'зарплата',
                'Brutto' => 'брутто (до вирахувань)',
                'Netto' => 'нетто (на руки)',

                // Education
                'Kita' => 'дитячий садок (Kita)',
                'Grundschule' => 'початкова школа',
                'Gymnasium' => 'гімназія',
                'Realschule' => 'реальна школа',
                'Hauptschule' => 'головна школа',
                'Hochschule' => 'вища школа/університет',
                'Ausbildung' => 'професійне навчання',
                'Sprachkurs' => 'мовні курси',
                'Integrationskurs' => 'інтеграційні курси',

                // Healthcare
                'Krankenkasse' => 'медична страховка',
                'Hausarzt' => 'сімейний лікар',
                'Facharzt' => 'лікар-спеціаліст',
                'Krankenhaus' => 'лікарня',
                'Notaufnahme' => 'приймальний покій/швидка',
                'Rezept' => 'рецепт',
                'Überweisung' => 'направлення до лікаря',
                'Versicherungskarte' => 'картка страхування',

                // Transport
                'MVV' => 'MVV (транспортна мережа Мюнхена)',
                'S-Bahn' => 'S-Bahn (приміська електричка)',
                'U-Bahn' => 'U-Bahn (метро)',
                'Straßenbahn' => 'трамвай',
                'Monatskarte' => 'місячний проїзний',
                'Deutschlandticket' => 'Deutschlandticket (проїзний по Німеччині)',

                // Legal
                'Gesetz' => 'закон',
                'Verordnung' => 'постанова',
                'Aufenthaltsgesetz' => 'Закон про перебування іноземців',
                'Asylgesetz' => 'Закон про притулок',
            ],
            'ru' => [
                // Documents & Status
                'Aufenthaltstitel' => 'вид на жительство (Aufenthaltstitel)',
                'Aufenthaltserlaubnis' => 'разрешение на пребывание',
                'Niederlassungserlaubnis' => 'постоянный вид на жительство',
                'Duldung' => 'толеранс/временная отсрочка депортации (Duldung)',
                'Visum' => 'виза',
                'Reiseausweis' => 'проездной документ',

                // Institutions
                'BAMF' => 'BAMF (Федеральное ведомство миграции и беженцев)',
                'Bundesregierung' => 'Федеральное правительство Германии',
                'Ausländerbehörde' => 'ведомство по делам иностранцев (Ausländerbehörde)',
                'Jobcenter' => 'Jobcenter (центр занятости)',
                'Arbeitsagentur' => 'Arbeitsagentur (агентство труда)',
                'Sozialamt' => 'социальная служба (Sozialamt)',
                'Rathaus' => 'ратуша/городская администрация',
                'Landratsamt' => 'районная администрация (Landratsamt)',
                'Finanzamt' => 'налоговая служба (Finanzamt)',
                'Standesamt' => 'ЗАГС (Standesamt)',
                'Jugendamt' => 'служба по делам молодёжи (Jugendamt)',
                'Gesundheitsamt' => 'санэпидстанция (Gesundheitsamt)',

                // Benefits & Money
                'Bürgergeld' => 'Bürgergeld (социальное пособие)',
                'Kindergeld' => 'Kindergeld (пособие на ребёнка)',
                'Elterngeld' => 'Elterngeld (родительское пособие)',
                'Wohngeld' => 'Wohngeld (жилищная субсидия)',
                'BAföG' => 'BAföG (стипендия на обучение)',
                'Arbeitslosengeld' => 'пособие по безработице',
                'Sozialhilfe' => 'социальная помощь',
                'Grundsicherung' => 'базовое обеспечение',

                // Procedures
                'Anmeldung' => 'регистрация по месту жительства (Anmeldung)',
                'Abmeldung' => 'снятие с регистрации (Abmeldung)',
                'Antrag' => 'заявление/заявка',
                'Termin' => 'назначенная встреча/срок',
                'Bescheid' => 'официальное решение/уведомление',
                'Einspruch' => 'возражение/апелляция',
                'Widerspruch' => 'обжалование',

                // Housing
                'Mietvertrag' => 'договор аренды',
                'Kaution' => 'залог за аренду',
                'Nebenkosten' => 'коммунальные платежи',
                'WG' => 'совместная квартира (WG)',
                'Wohnungsamt' => 'жилищный отдел',

                // Work
                'Arbeitsvertrag' => 'трудовой договор',
                'Minijob' => 'Minijob (подработка до 520€)',
                'Teilzeit' => 'частичная занятость',
                'Vollzeit' => 'полная занятость',
                'Probezeit' => 'испытательный срок',
                'Kündigung' => 'увольнение',
                'Gehalt' => 'зарплата',
                'Brutto' => 'брутто (до вычетов)',
                'Netto' => 'нетто (на руки)',

                // Education
                'Kita' => 'детский сад (Kita)',
                'Grundschule' => 'начальная школа',
                'Gymnasium' => 'гимназия',
                'Realschule' => 'реальная школа',
                'Hauptschule' => 'главная школа',
                'Hochschule' => 'высшая школа/университет',
                'Ausbildung' => 'профессиональное обучение',
                'Sprachkurs' => 'языковые курсы',
                'Integrationskurs' => 'интеграционные курсы',

                // Healthcare
                'Krankenkasse' => 'медицинская страховка',
                'Hausarzt' => 'семейный врач',
                'Facharzt' => 'врач-специалист',
                'Krankenhaus' => 'больница',
                'Notaufnahme' => 'приёмный покой/скорая',
                'Rezept' => 'рецепт',
                'Überweisung' => 'направление к врачу',
                'Versicherungskarte' => 'карта страхования',

                // Transport
                'MVV' => 'MVV (транспортная сеть Мюнхена)',
                'S-Bahn' => 'S-Bahn (пригородная электричка)',
                'U-Bahn' => 'U-Bahn (метро)',
                'Straßenbahn' => 'трамвай',
                'Monatskarte' => 'месячный проездной',
                'Deutschlandticket' => 'Deutschlandticket (проездной по Германии)',

                // Legal
                'Gesetz' => 'закон',
                'Verordnung' => 'постановление',
                'Aufenthaltsgesetz' => 'Закон о пребывании иностранцев',
                'Asylgesetz' => 'Закон об убежище',
            ],
            'en' => [
                'BAMF' => 'BAMF (Federal Office for Migration and Refugees)',
                'Bundesregierung' => 'German Federal Government',
                'Ausländerbehörde' => 'Foreigners\' Registration Office (Ausländerbehörde)',
                'Jobcenter' => 'Jobcenter (employment office)',
                'Bürgergeld' => 'Bürgergeld (citizen\'s benefit)',
                'Kindergeld' => 'Kindergeld (child benefit)',
                'Aufenthaltstitel' => 'residence permit (Aufenthaltstitel)',
                'Anmeldung' => 'residence registration (Anmeldung)',
            ],
        ];

        return $glossaries[$target_lang] ?? [];
    }

    /**
     * Translate content
     */
    public function translate($content, $source_lang, $target_lang, $glossary = []) {
        $system_prompt = $this->get_translation_system_prompt($source_lang, $target_lang, $glossary);

        $prompt = "Translate the following text:\n\n{$content}";

        return $this->complete($prompt, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 6000]);
    }

    /**
     * Generate SEO metadata
     */
    public function generate_seo($content, $language = 'de') {
        $lang_configs = [
            'de' => [
                'name' => 'German',
                'examples' => [
                    'title' => 'Neue Regeln für Aufenthaltstitel 2024: Was Ukrainer wissen müssen',
                    'description' => 'Ab Januar gelten neue Regeln für Aufenthaltstitel. Wir erklären, was sich ändert und was Sie tun müssen.',
                ],
            ],
            'ua' => [
                'name' => 'Ukrainian',
                'examples' => [
                    'title' => 'Нові правила для посвідок на проживання 2024: що треба знати',
                    'description' => 'З січня діють нові правила для посвідок. Пояснюємо, що змінюється і що потрібно робити.',
                ],
            ],
            'ru' => [
                'name' => 'Russian',
                'examples' => [
                    'title' => 'Новые правила для видов на жительство 2024: что нужно знать',
                    'description' => 'С января действуют новые правила для ВНЖ. Объясняем, что меняется и что нужно делать.',
                ],
            ],
            'en' => [
                'name' => 'English',
                'examples' => [
                    'title' => 'New Residence Permit Rules 2024: What Ukrainians Need to Know',
                    'description' => 'New residence permit rules take effect in January. We explain what\'s changing and what to do.',
                ],
            ],
        ];

        $config = $lang_configs[$language] ?? $lang_configs['de'];

        $system_prompt = <<<PROMPT
# ROLE
You are an SEO specialist for a news website targeting Ukrainians in Germany.

# TASK
Generate SEO metadata in {$config['name']} for the provided article.

# REQUIREMENTS

## SEO Title (max 60 characters)
- Main keyword near the beginning
- Clear, compelling, click-worthy
- Include year if time-sensitive
- Example: "{$config['examples']['title']}"

## Meta Description (max 155 characters)
- Summarize the value for reader
- Include primary keyword naturally
- End with implicit call to action
- Example: "{$config['examples']['description']}"

## Keywords (5-10)
- Mix of broad and specific terms
- Include German terms if relevant (Bürgergeld, BAMF, etc.)
- Think: what would Ukrainians search for?

## Slug (URL-friendly)
- Lowercase, hyphens between words
- Max 60 characters
- No special characters
- Descriptive and keyword-rich

# OUTPUT FORMAT (JSON only)
{
  "title": "SEO title here",
  "description": "Meta description here",
  "keywords": ["keyword1", "keyword2", "keyword3"],
  "slug": "url-slug-here"
}
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.5, 'max_tokens' => 500]);

        if (!$result['success']) {
            return $result;
        }

        // Parse JSON from response
        $json_content = $result['content'];

        if (preg_match('/\{[\s\S]*\}/', $json_content, $matches)) {
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

        return [
            'success' => false,
            'error' => 'Failed to parse SEO response',
        ];
    }

    /**
     * Extract entities and keywords
     */
    public function extract_entities($content) {
        $system_prompt = <<<PROMPT
# ROLE
You are a news analyst specializing in content for Ukrainian audiences in Germany.

# TASK
Analyze the provided news article and extract structured data.

# EXTRACT

## Named Entities
- PERSONS: Politicians, officials, experts mentioned
- ORGANIZATIONS: Companies, agencies, NGOs, parties
- LOCATIONS: Cities, regions, countries, addresses
- DATES: Specific dates, deadlines, time periods

## Keywords
5-10 most important terms for this article
Focus on: topics, issues, affected groups

## Geographic Relevance
Rate relevance (high/medium/low) for:
- München (local)
- Bayern (regional)
- Deutschland (national)
- EU (European)
- Ukraine (Ukrainian)
- International

## Category
Choose ONE primary category:
- politik (political news, government decisions)
- wirtschaft (economy, jobs, business)
- migration (visa, residence, integration)
- gesellschaft (society, education, culture)
- lokales (local Munich/Bavaria news)
- verkehr (transport, MVV, traffic)
- wetter (weather, alerts, warnings)
- sport (sports)
- kultur (culture, events)
- nachrichten (general news)

## Sentiment
-1 (very negative) to +1 (very positive), 0 is neutral

# OUTPUT FORMAT (JSON only)
{
  "entities": {
    "persons": ["Name 1", "Name 2"],
    "organizations": ["Org 1", "Org 2"],
    "locations": ["Location 1", "Location 2"],
    "dates": ["Date 1", "Date 2"]
  },
  "keywords": ["keyword1", "keyword2"],
  "geo": {
    "muenchen": "high|medium|low|none",
    "bayern": "high|medium|low|none",
    "deutschland": "high|medium|low|none",
    "eu": "high|medium|low|none",
    "ukraine": "high|medium|low|none"
  },
  "category": "category_key",
  "sentiment": 0.0
}
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 1000]);

        if (!$result['success']) {
            return $result;
        }

        if (preg_match('/\{[\s\S]*\}/', $result['content'], $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                // Convert geo to simple array for backward compatibility
                $geo_array = [];
                if (isset($parsed['geo']) && is_array($parsed['geo'])) {
                    foreach ($parsed['geo'] as $location => $relevance) {
                        if ($relevance === 'high' || $relevance === 'medium') {
                            $geo_array[] = ucfirst($location);
                        }
                    }
                }

                return [
                    'success' => true,
                    'entities' => $parsed['entities'] ?? [],
                    'keywords' => $parsed['keywords'] ?? [],
                    'geo' => $geo_array,
                    'category' => $parsed['category'] ?? 'nachrichten',
                    'sentiment' => $parsed['sentiment'] ?? 0,
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Failed to parse entities response',
        ];
    }

    /**
     * Classify content
     */
    public function classify($content, $categories) {
        $categories_list = implode(', ', array_keys($categories));

        $system_prompt = <<<PROMPT
# TASK
Classify the provided news article into exactly ONE of these categories:
{$categories_list}

# CATEGORIES GUIDE
- politik: Government, laws, elections, parties, political decisions
- wirtschaft: Economy, jobs, companies, inflation, benefits, financial news
- migration: Visas, residence permits, integration, asylum, BAMF decisions
- gesellschaft: Education, healthcare, social issues, demographics
- lokales: Munich/Bavaria specific local news
- verkehr: Public transport, MVV, traffic, roads, mobility
- wetter: Weather forecasts, warnings, climate events
- sport: All sports news
- kultur: Culture, events, entertainment, art, festivals
- nachrichten: General news that doesn't fit other categories

# OUTPUT FORMAT (JSON only)
{
  "category": "category_key",
  "confidence": 0.95,
  "subcategory": "optional detail",
  "tags": ["tag1", "tag2", "tag3"]
}
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 300]);

        if (!$result['success']) {
            return $result;
        }

        if (preg_match('/\{[\s\S]*\}/', $result['content'], $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return [
                    'success' => true,
                    'category' => $parsed['category'] ?? 'nachrichten',
                    'confidence' => $parsed['confidence'] ?? 0.5,
                    'subcategory' => $parsed['subcategory'] ?? '',
                    'tags' => $parsed['tags'] ?? [],
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Failed to parse classification response',
        ];
    }

    /**
     * Summarize content
     */
    public function summarize($content, $max_length = 200, $language = 'de') {
        $lang_names = [
            'de' => 'German',
            'ua' => 'Ukrainian',
            'ru' => 'Russian',
            'en' => 'English',
        ];

        $lang_name = $lang_names[$language] ?? 'German';

        $system_prompt = <<<PROMPT
# TASK
Create a concise summary in {$lang_name}.

# REQUIREMENTS
- Maximum {$max_length} characters
- Capture the MAIN point
- Include key facts (who, what, when)
- Neutral, factual tone
- Complete sentences
- Must stand alone (reader needs no other context)

# OUTPUT
Provide only the summary text, nothing else.
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 300]);

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'summary' => mb_substr(trim($result['content']), 0, $max_length),
        ];
    }

    /**
     * Test connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => 'API key not configured',
            ];
        }

        $result = $this->complete('Reply with exactly: "Connection OK"', '', [
            'max_tokens' => 20,
            'temperature' => 0,
        ]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Connection successful',
                'model' => $this->model,
            ];
        }

        return [
            'success' => false,
            'message' => $result['error'] ?? 'Connection failed',
        ];
    }

    /**
     * Generate full article from multiple sources (event clustering)
     */
    public function generate_article($facts, $sources, $language = 'de') {
        $sources_text = '';
        foreach ($sources as $source) {
            $sources_text .= "### Source: {$source['name']} ({$source['date']})\n";
            $sources_text .= "Title: {$source['title']}\n";
            if (!empty($source['summary'])) {
                $sources_text .= "Content: {$source['summary']}\n";
            }
            $sources_text .= "\n";
        }

        $system_prompt = $this->get_rewrite_system_prompt($language, '
## SPECIAL INSTRUCTIONS FOR MULTI-SOURCE ARTICLE
- You have information from MULTIPLE sources about the SAME event
- Cross-reference facts - only include what 2+ sources confirm
- If sources disagree, present both perspectives
- Prioritize official sources over media speculation
- Create a comprehensive picture, not just a rehash of one source
');

        $prompt = <<<PROMPT
Create a comprehensive article synthesizing these sources:

{$sources_text}

Key verified facts:
{$facts}

Requirements:
1. Synthesize information from ALL sources
2. Fact-check: only include claims that appear in 2+ sources
3. Note any contradictions between sources
4. Create original, comprehensive coverage
5. Follow Deutsche Welle quality standards
PROMPT;

        return $this->complete($prompt, $system_prompt, ['temperature' => 0.7, 'max_tokens' => 4000]);
    }

    /**
     * Generate Telegram-optimized summary
     */
    public function generate_telegram_summary($content, $language = 'de') {
        $lang_configs = [
            'de' => ['flag' => '🇩🇪', 'read_more' => 'Mehr lesen'],
            'ua' => ['flag' => '🇺🇦', 'read_more' => 'Читати далі'],
            'ru' => ['flag' => '🇷🇺', 'read_more' => 'Читать дальше'],
            'en' => ['flag' => '🇬🇧', 'read_more' => 'Read more'],
        ];

        $config = $lang_configs[$language] ?? $lang_configs['de'];

        $system_prompt = <<<PROMPT
# TASK
Create a Telegram post summary optimized for mobile reading.

# FORMAT
📰 [Attention-grabbing headline]

[2-3 sentence summary - the essential facts]

{$config['flag']} [1 emoji relevant to topic] [Category tag]

# REQUIREMENTS
- Max 280 characters total (excluding headline)
- Use 1-2 relevant emojis only
- Punchy, scannable text
- Must make reader want to click through
- End with implicit "read more" hook

# OUTPUT
Provide only the formatted Telegram post, nothing else.
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.7, 'max_tokens' => 300]);

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'telegram_text' => trim($result['content']),
        ];
    }

    /**
     * Check if article is relevant for publication
     * Returns relevance score and reason
     *
     * EUROPULSE.TODAY = "Пульс Європи" - широкий охват европейских новостей
     * Основная аудитория: украинская диаспора в Германии, но берём шире
     */
    public function check_relevance($title, $summary) {
        $content = $title . "\n\n" . $summary;

        $system_prompt = <<<PROMPT
# РОЛЬ
Ты редактор новостного портала europulse.today - "Пульс Європи".
Сайт для украинской диаспоры в Германии, но охватываем ШИРОКИЙ спектр европейских новостей.
Редактор делает финальный отбор вручную - твоя задача НЕ отсеять потенциально интересное.

# ЗАДАЧА
Оцени, стоит ли передать новость на ручную модерацию (relevant: true) или сразу отклонить (relevant: false).
ВАЖНО: При сомнениях - пропускай! Лучше пропустить лишнее, чем потерять важное.

# ПУБЛИКУЕМ (relevant: true):

## ВЫСОКИЙ ПРИОРИТЕТ (priority: high):
- Миграция, визы, ВНЖ, BAMF, законы о беженцах
- Помощь украинцам: выплаты, Bürgergeld, Jobcenter
- Интеграция: курсы языка, признание дипломов, работа
- Политика Германии/ЕС по Украине
- Война в Украине - важные события
- Бавария и Мюнхен - ВСЕ значимые новости
- Образование, детсады, школы, университеты
- Медицина, страхование, Krankenkasse

## СРЕДНИЙ ПРИОРИТЕТ (priority: medium):
- Экономика Германии и Европы
- Рынок труда, зарплаты, безработица
- Транспорт: MVV, Deutsche Bahn, авиа
- Технологии, IT, стартапы
- Недвижимость, аренда, Wohnung
- СПОРТ: футбол (FC Bayern, Бундеслига, ЛЧ), крупные турниры
- Украинские спортсмены в Европе
- Культура, фестивали, концерты
- Крупные криминальные новости (терроризм, громкие дела)
- Общеевропейские новости (ЕС, политика)
- Климат, экология (крупные события)

## НИЗКИЙ ПРИОРИТЕТ (priority: low):
- Развлечения, кино, сериалы
- Путешествия, туризм
- Общие новости других стран ЕС
- Наука, исследования

# НЕ ПУБЛИКУЕМ (relevant: false):
- Жёлтая пресса, сплетни о знаменитостях
- Чистая реклама, пресс-релизы компаний
- Очень мелкие локальные новости деревень
- Прогноз погоды (кроме катастроф)
- Мелкая криминальная хроника (кражи, ДТП без жертв)
- Спам, кликбейт без содержания

# ФОРМАТ ОТВЕТА (только JSON):
{
  "relevant": true или false,
  "priority": "high" или "medium" или "low",
  "reason": "краткое объяснение почему"
}
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 500]);

        if (!$result['success']) {
            // On error, assume relevant to not lose articles
            return [
                'success' => true,
                'relevant' => true,
                'priority' => 'medium',
                'reason' => 'AI check failed, defaulting to relevant',
            ];
        }

        if (preg_match('/\{[\s\S]*\}/', $result['content'], $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return [
                    'success' => true,
                    'relevant' => $parsed['relevant'] ?? true,
                    'priority' => $parsed['priority'] ?? 'medium',
                    'reason' => $parsed['reason'] ?? '',
                ];
            }
        }

        // Fallback - assume relevant
        return [
            'success' => true,
            'relevant' => true,
            'priority' => 'medium',
            'reason' => 'Could not parse AI response',
        ];
    }
}
