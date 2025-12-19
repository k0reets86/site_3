<?php
/**
 * Advanced Translator Class
 * Handles translations in ALL directions between DE, UA, RU, EN
 * with comprehensive glossaries for Ukrainian audience in Germany
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Translator {

    /**
     * Supported languages
     */
    const LANGUAGES = ['de', 'ua', 'ru', 'en'];

    /**
     * Language names
     */
    private static $lang_names = [
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'flag' => '🇩🇪'],
        'ua' => ['name' => 'Ukrainian', 'native' => 'Українська', 'flag' => '🇺🇦'],
        'ru' => ['name' => 'Russian', 'native' => 'Русский', 'flag' => '🇷🇺'],
        'en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇬🇧'],
    ];

    /**
     * AI Provider instance
     */
    private $ai;

    /**
     * Constructor
     */
    public function __construct() {
        $this->ai = AINCC_AI_Provider_Factory::create();
    }

    /**
     * Get all supported languages
     */
    public static function get_languages() {
        return self::$lang_names;
    }

    /**
     * Get other languages (exclude given)
     */
    public static function get_other_languages($exclude) {
        $others = [];
        foreach (self::LANGUAGES as $lang) {
            if ($lang !== $exclude) {
                $others[] = $lang;
            }
        }
        return $others;
    }

    /**
     * Detect language of content
     */
    public function detect_language($content) {
        // Common words for detection
        $indicators = [
            'de' => ['der', 'die', 'das', 'und', 'ist', 'für', 'mit', 'werden', 'haben', 'nicht', 'auch', 'auf', 'bei'],
            'ua' => ['та', 'що', 'для', 'від', 'або', 'які', 'при', 'цей', 'але', 'після', 'його', 'вона', 'вони'],
            'ru' => ['что', 'для', 'это', 'как', 'или', 'при', 'его', 'она', 'они', 'был', 'были', 'также'],
            'en' => ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'been', 'will', 'they', 'are'],
        ];

        $content_lower = mb_strtolower($content);
        $scores = [];

        foreach ($indicators as $lang => $words) {
            $score = 0;
            foreach ($words as $word) {
                $score += substr_count($content_lower, ' ' . $word . ' ');
            }
            $scores[$lang] = $score;
        }

        arsort($scores);
        $detected = array_keys($scores)[0];

        return $scores[$detected] > 2 ? $detected : 'de'; // Default to German
    }

    /**
     * Translate content from any language to any language
     */
    public function translate($content, $source_lang, $target_lang, $extra_glossary = []) {
        if ($source_lang === $target_lang) {
            return ['success' => true, 'content' => $content];
        }

        // Get direction-specific glossary
        $glossary = $this->get_glossary($source_lang, $target_lang);
        $glossary = array_merge($glossary, $extra_glossary);

        // Build system prompt
        $system_prompt = $this->build_translation_prompt($source_lang, $target_lang, $glossary);

        // Translate
        $result = $this->ai->complete(
            "Translate the following text:\n\n{$content}",
            $system_prompt,
            ['temperature' => 0.3, 'max_tokens' => 6000]
        );

        return $result;
    }

    /**
     * Batch translate to all other languages
     */
    public function translate_to_all($content, $source_lang) {
        $results = [];
        $target_langs = self::get_other_languages($source_lang);

        foreach ($target_langs as $target_lang) {
            $results[$target_lang] = $this->translate($content, $source_lang, $target_lang);
        }

        return $results;
    }

    /**
     * Build translation prompt
     */
    private function build_translation_prompt($source_lang, $target_lang, $glossary) {
        $source = self::$lang_names[$source_lang];
        $target = self::$lang_names[$target_lang];

        $glossary_text = '';
        if (!empty($glossary)) {
            $glossary_text = "\n\n## ОБЯЗАТЕЛЬНЫЙ ГЛОССАРИЙ - Используйте эти переводы:\n";
            foreach ($glossary as $term => $translation) {
                $glossary_text .= "• {$term} → {$translation}\n";
            }
        }

        // Special instructions based on direction
        $special_instructions = $this->get_direction_instructions($source_lang, $target_lang);

        return <<<PROMPT
# РОЛЬ
Вы профессиональный переводчик новостного контента для украинской диаспоры в Германии.
Вы сочетаете лингвистическую точность с культурной адаптацией.

# ЗАДАЧА
Перевод с {$source['name']} ({$source['native']}) на {$target['name']} ({$target['native']}).

# ПРИНЦИПЫ ПЕРЕВОДА

## 1. СМЫСЛ ВАЖНЕЕ СЛОВ
- Переводите ИДЕИ, а не слово-в-слово
- Адаптируйте идиомы и выражения естественно
- Сохраняйте ТОН и ВОЗДЕЙСТВИЕ оригинала
- Результат должен читаться как будто изначально написан на {$target['name']}

## 2. КУЛЬТУРНАЯ АДАПТАЦИЯ
- Конвертируйте культурные отсылки при необходимости
- Сохраняйте немецкие названия институтов (BAMF, Jobcenter) но объясняйте
- Даты: формат DD.MM.YYYY
- Числа: используйте местные конвенции
- Валюта: EUR/€

## 3. СОХРАНЯЙТЕ СТРУКТУРУ
- HTML теги оставляйте как есть
- Сохраняйте разбивку на параграфы
- Сохраняйте списки и маркеры
- Сохраняйте теги <title>, <lead>, <body> если есть

## 4. НЕ ПЕРЕВОДИТЬ
- URL и ссылки
- Email адреса
- Названия немецких институтов (BAMF, Bundesregierung и т.д.)
- Имена людей
- Названия законов и нормативов на немецком
- Технические коды и номера

{$special_instructions}
{$glossary_text}

## 5. СТАНДАРТЫ КАЧЕСТВА
- Естественный, беглый {$target['name']}
- Никаких неуклюжих конструкций
- Никакого "переводческого языка"
- Соответствующий регистр для новостей

# ВЫВОД
Предоставьте ТОЛЬКО переведенный текст. Никаких комментариев, никаких объяснений.
PROMPT;
    }

    /**
     * Get direction-specific instructions
     */
    private function get_direction_instructions($source_lang, $target_lang) {
        $instructions = [
            // TO GERMAN
            'ua_de' => '
## СПЕЦИАЛЬНЫЕ ИНСТРУКЦИИ UA→DE
- Используйте формальный немецкий (Sie-Form)
- Адаптируйте украинские реалии для немецкой аудитории
- Объясняйте украинские термины в скобках
- Для официальных терминов используйте немецкие эквиваленты',

            'ru_de' => '
## СПЕЦИАЛЬНЫЕ ИНСТРУКЦИИ RU→DE
- Используйте формальный немецкий (Sie-Form)
- Адаптируйте российские/советские реалии для немецкой аудитории
- Объясняйте специфические термины в скобках',

            'en_de' => '
## СПЕЦИАЛЬНЫЕ ИНСТРУКЦИИ EN→DE
- Используйте формальный немецкий (Sie-Form)
- Адаптируйте англоязычные термины (многие можно оставить)
- Не калькируйте английские конструкции',

            // TO UKRAINIAN
            'de_ua' => '
## СПЕЦІАЛЬНІ ІНСТРУКЦІЇ DE→UA
- Пояснюйте німецькі терміни в дужках при першому згадуванні
- Зберігайте оригінальні назви установ: BAMF, Jobcenter, Ausländerbehörde
- Адаптуйте для B1-B2 рівня німецької (читачі вивчають німецьку)
- Використовуйте практичну термінологію для мігрантів',

            'ru_ua' => '
## СПЕЦІАЛЬНІ ІНСТРУКЦІЇ RU→UA
- Уникайте русизмів, використовуйте українську лексику
- Зберігайте німецькі терміни як є (BAMF, Jobcenter)
- Пам\'ятайте про українську аудиторію в Німеччині',

            'en_ua' => '
## СПЕЦІАЛЬНІ ІНСТРУКЦІЇ EN→UA
- Адаптуйте англіцизми природно
- Зберігайте німецькі терміни як є
- Орієнтуйтесь на українців в Німеччині',

            // TO RUSSIAN
            'de_ru' => '
## СПЕЦИАЛЬНЫЕ ИНСТРУКЦИИ DE→RU
- Объясняйте немецкие термины в скобках при первом упоминании
- Сохраняйте оригинальные названия учреждений: BAMF, Jobcenter
- Адаптируйте для уровня B1-B2 немецкого
- Используйте практическую терминологию для мигрантов',

            'ua_ru' => '
## СПЕЦИАЛЬНЫЕ ИНСТРУКЦИИ UA→RU
- Сохраняйте немецкие термины как есть (BAMF, Jobcenter)
- Помните об украинской аудитории в Германии
- Используйте нейтральный тон',

            'en_ru' => '
## СПЕЦИАЛЬНЫЕ ИНСТРУКЦИИ EN→RU
- Адаптируйте англицизмы естественно
- Сохраняйте немецкие термины как есть
- Ориентируйтесь на украинцев в Германии',

            // TO ENGLISH
            'de_en' => '
## SPECIAL INSTRUCTIONS DE→EN
- Use American English spelling
- Explain German terms in parentheses first time
- Keep institution names: BAMF, Jobcenter, etc.
- Adapt for readers learning about German system',

            'ua_en' => '
## SPECIAL INSTRUCTIONS UA→EN
- Use American English spelling
- Explain Ukrainian terms in parentheses
- Keep German terms as-is (BAMF, Jobcenter)
- Target: Ukrainians in Germany who prefer English',

            'ru_en' => '
## SPECIAL INSTRUCTIONS RU→EN
- Use American English spelling
- Keep German terms as-is
- Target audience: Ukrainians in Germany who prefer English',
        ];

        $key = "{$source_lang}_{$target_lang}";
        return $instructions[$key] ?? '';
    }

    /**
     * Get glossary for specific language pair
     */
    private function get_glossary($source_lang, $target_lang) {
        // Base German terms with translations to all languages
        $german_terms = [
            // Documents & Status
            'Aufenthaltstitel' => [
                'ua' => 'посвідка на проживання',
                'ru' => 'вид на жительство',
                'en' => 'residence permit',
            ],
            'Aufenthaltserlaubnis' => [
                'ua' => 'дозвіл на перебування',
                'ru' => 'разрешение на пребывание',
                'en' => 'residence permit',
            ],
            'Niederlassungserlaubnis' => [
                'ua' => 'дозвіл на постійне проживання',
                'ru' => 'разрешение на постоянное проживание',
                'en' => 'permanent residence permit',
            ],
            'Duldung' => [
                'ua' => 'толеранс (тимчасова відстрочка депортації)',
                'ru' => 'толеранс (временная отсрочка депортации)',
                'en' => 'temporary suspension of deportation',
            ],
            'Fiktionsbescheinigung' => [
                'ua' => 'фіктивне свідоцтво (тимчасовий документ)',
                'ru' => 'фиктивная справка (временный документ)',
                'en' => 'fictional certificate (temporary document)',
            ],

            // Institutions
            'BAMF' => [
                'ua' => 'BAMF (Федеральне відомство міграції)',
                'ru' => 'BAMF (Федеральное ведомство миграции)',
                'en' => 'BAMF (Federal Migration Office)',
            ],
            'Ausländerbehörde' => [
                'ua' => 'відділ у справах іноземців',
                'ru' => 'ведомство по делам иностранцев',
                'en' => 'Immigration Office',
            ],
            'Jobcenter' => [
                'ua' => 'Jobcenter (центр зайнятості)',
                'ru' => 'Jobcenter (центр занятости)',
                'en' => 'Jobcenter (employment office)',
            ],
            'Arbeitsagentur' => [
                'ua' => 'агентство праці',
                'ru' => 'агентство труда',
                'en' => 'Employment Agency',
            ],
            'Sozialamt' => [
                'ua' => 'соціальна служба',
                'ru' => 'социальная служба',
                'en' => 'Social Welfare Office',
            ],
            'Finanzamt' => [
                'ua' => 'податкова служба',
                'ru' => 'налоговая служба',
                'en' => 'Tax Office',
            ],
            'Standesamt' => [
                'ua' => 'РАЦС',
                'ru' => 'ЗАГС',
                'en' => 'Registry Office',
            ],
            'Jugendamt' => [
                'ua' => 'служба у справах молоді',
                'ru' => 'служба по делам молодёжи',
                'en' => 'Youth Welfare Office',
            ],
            'Gesundheitsamt' => [
                'ua' => 'санепідстанція',
                'ru' => 'санэпидстанция',
                'en' => 'Health Department',
            ],
            'Rathaus' => [
                'ua' => 'ратуша (міська адміністрація)',
                'ru' => 'ратуша (городская администрация)',
                'en' => 'City Hall',
            ],
            'Landratsamt' => [
                'ua' => 'районна адміністрація',
                'ru' => 'районная администрация',
                'en' => 'District Office',
            ],
            'Bundesregierung' => [
                'ua' => 'Федеральний уряд',
                'ru' => 'Федеральное правительство',
                'en' => 'Federal Government',
            ],

            // Benefits
            'Bürgergeld' => [
                'ua' => 'Bürgergeld (соціальна допомога)',
                'ru' => 'Bürgergeld (социальное пособие)',
                'en' => 'citizen\'s benefit (welfare)',
            ],
            'Kindergeld' => [
                'ua' => 'Kindergeld (допомога на дитину)',
                'ru' => 'Kindergeld (пособие на ребёнка)',
                'en' => 'child benefit',
            ],
            'Elterngeld' => [
                'ua' => 'Elterngeld (батьківська допомога)',
                'ru' => 'Elterngeld (родительское пособие)',
                'en' => 'parental allowance',
            ],
            'Wohngeld' => [
                'ua' => 'Wohngeld (житлова субсидія)',
                'ru' => 'Wohngeld (жилищная субсидия)',
                'en' => 'housing benefit',
            ],
            'Arbeitslosengeld' => [
                'ua' => 'допомога по безробіттю',
                'ru' => 'пособие по безработице',
                'en' => 'unemployment benefit',
            ],
            'BAföG' => [
                'ua' => 'BAföG (стипендія на навчання)',
                'ru' => 'BAföG (стипендия на обучение)',
                'en' => 'student financial aid',
            ],

            // Procedures
            'Anmeldung' => [
                'ua' => 'реєстрація за місцем проживання',
                'ru' => 'регистрация по месту жительства',
                'en' => 'residence registration',
            ],
            'Abmeldung' => [
                'ua' => 'зняття з реєстрації',
                'ru' => 'снятие с регистрации',
                'en' => 'deregistration',
            ],
            'Antrag' => [
                'ua' => 'заява',
                'ru' => 'заявление',
                'en' => 'application',
            ],
            'Bescheid' => [
                'ua' => 'офіційне рішення/повідомлення',
                'ru' => 'официальное решение/уведомление',
                'en' => 'official notice/decision',
            ],
            'Widerspruch' => [
                'ua' => 'оскарження',
                'ru' => 'обжалование',
                'en' => 'objection/appeal',
            ],
            'Termin' => [
                'ua' => 'призначена зустріч',
                'ru' => 'назначенная встреча',
                'en' => 'appointment',
            ],

            // Work
            'Arbeitsvertrag' => [
                'ua' => 'трудовий договір',
                'ru' => 'трудовой договор',
                'en' => 'employment contract',
            ],
            'Minijob' => [
                'ua' => 'Minijob (підробіток до 520€)',
                'ru' => 'Minijob (подработка до 520€)',
                'en' => 'mini-job (up to €520)',
            ],
            'Teilzeit' => [
                'ua' => 'часткова зайнятість',
                'ru' => 'частичная занятость',
                'en' => 'part-time',
            ],
            'Vollzeit' => [
                'ua' => 'повна зайнятість',
                'ru' => 'полная занятость',
                'en' => 'full-time',
            ],
            'Probezeit' => [
                'ua' => 'випробувальний термін',
                'ru' => 'испытательный срок',
                'en' => 'probation period',
            ],
            'Kündigung' => [
                'ua' => 'звільнення',
                'ru' => 'увольнение',
                'en' => 'termination',
            ],
            'Gehalt' => [
                'ua' => 'зарплата',
                'ru' => 'зарплата',
                'en' => 'salary',
            ],
            'Brutto' => [
                'ua' => 'брутто (до вирахувань)',
                'ru' => 'брутто (до вычетов)',
                'en' => 'gross (before deductions)',
            ],
            'Netto' => [
                'ua' => 'нетто (на руки)',
                'ru' => 'нетто (на руки)',
                'en' => 'net (take-home)',
            ],

            // Housing
            'Mietvertrag' => [
                'ua' => 'договір оренди',
                'ru' => 'договор аренды',
                'en' => 'rental agreement',
            ],
            'Kaution' => [
                'ua' => 'застава',
                'ru' => 'залог',
                'en' => 'security deposit',
            ],
            'Nebenkosten' => [
                'ua' => 'комунальні платежі',
                'ru' => 'коммунальные платежи',
                'en' => 'utilities/additional costs',
            ],
            'Warmmiete' => [
                'ua' => 'оренда з комунальними',
                'ru' => 'аренда с коммунальными',
                'en' => 'rent including utilities',
            ],
            'Kaltmiete' => [
                'ua' => 'оренда без комунальних',
                'ru' => 'аренда без коммунальных',
                'en' => 'rent excluding utilities',
            ],
            'WG' => [
                'ua' => 'спільна квартира',
                'ru' => 'совместная квартира',
                'en' => 'shared apartment',
            ],

            // Education
            'Kita' => [
                'ua' => 'дитячий садок',
                'ru' => 'детский сад',
                'en' => 'kindergarten/daycare',
            ],
            'Grundschule' => [
                'ua' => 'початкова школа',
                'ru' => 'начальная школа',
                'en' => 'primary school',
            ],
            'Gymnasium' => [
                'ua' => 'гімназія',
                'ru' => 'гимназия',
                'en' => 'grammar school',
            ],
            'Realschule' => [
                'ua' => 'реальна школа',
                'ru' => 'реальная школа',
                'en' => 'secondary school',
            ],
            'Hauptschule' => [
                'ua' => 'головна школа',
                'ru' => 'главная школа',
                'en' => 'secondary general school',
            ],
            'Ausbildung' => [
                'ua' => 'професійне навчання',
                'ru' => 'профессиональное обучение',
                'en' => 'vocational training',
            ],
            'Integrationskurs' => [
                'ua' => 'інтеграційні курси',
                'ru' => 'интеграционные курсы',
                'en' => 'integration course',
            ],

            // Healthcare
            'Krankenkasse' => [
                'ua' => 'медична страховка',
                'ru' => 'медицинская страховка',
                'en' => 'health insurance',
            ],
            'Hausarzt' => [
                'ua' => 'сімейний лікар',
                'ru' => 'семейный врач',
                'en' => 'family doctor/GP',
            ],
            'Facharzt' => [
                'ua' => 'лікар-спеціаліст',
                'ru' => 'врач-специалист',
                'en' => 'specialist doctor',
            ],
            'Krankenhaus' => [
                'ua' => 'лікарня',
                'ru' => 'больница',
                'en' => 'hospital',
            ],
            'Notaufnahme' => [
                'ua' => 'приймальний покій/швидка',
                'ru' => 'приёмный покой/скорая',
                'en' => 'emergency room',
            ],
            'Rezept' => [
                'ua' => 'рецепт',
                'ru' => 'рецепт',
                'en' => 'prescription',
            ],
            'Überweisung' => [
                'ua' => 'направлення до лікаря',
                'ru' => 'направление к врачу',
                'en' => 'referral',
            ],

            // Transport (Munich specific)
            'MVV' => [
                'ua' => 'MVV (транспортна мережа Мюнхена)',
                'ru' => 'MVV (транспортная сеть Мюнхена)',
                'en' => 'MVV (Munich transport network)',
            ],
            'S-Bahn' => [
                'ua' => 'S-Bahn (приміська електричка)',
                'ru' => 'S-Bahn (пригородная электричка)',
                'en' => 'S-Bahn (suburban train)',
            ],
            'U-Bahn' => [
                'ua' => 'U-Bahn (метро)',
                'ru' => 'U-Bahn (метро)',
                'en' => 'U-Bahn (subway)',
            ],
            'Deutschlandticket' => [
                'ua' => 'Deutschlandticket (проїзний по Німеччині)',
                'ru' => 'Deutschlandticket (проездной по Германии)',
                'en' => 'Germany ticket (nationwide transport pass)',
            ],
            'Monatskarte' => [
                'ua' => 'місячний проїзний',
                'ru' => 'месячный проездной',
                'en' => 'monthly pass',
            ],
        ];

        // Ukrainian terms (for UA → other)
        $ukrainian_terms = [
            'посвідчення особи' => [
                'de' => 'Personalausweis',
                'ru' => 'удостоверение личности',
                'en' => 'ID card',
            ],
            'закордонний паспорт' => [
                'de' => 'Reisepass',
                'ru' => 'загранпаспорт',
                'en' => 'passport',
            ],
            'внутрішній паспорт' => [
                'de' => 'Inlandspass',
                'ru' => 'внутренний паспорт',
                'en' => 'internal passport',
            ],
            'тимчасовий захист' => [
                'de' => 'vorübergehender Schutz',
                'ru' => 'временная защита',
                'en' => 'temporary protection',
            ],
            'біженці' => [
                'de' => 'Flüchtlinge/Geflüchtete',
                'ru' => 'беженцы',
                'en' => 'refugees',
            ],
        ];

        // Russian terms (for RU → other)
        $russian_terms = [
            'вид на жительство' => [
                'de' => 'Aufenthaltstitel',
                'ua' => 'посвідка на проживання',
                'en' => 'residence permit',
            ],
            'загранпаспорт' => [
                'de' => 'Reisepass',
                'ua' => 'закордонний паспорт',
                'en' => 'passport',
            ],
        ];

        // Build glossary based on direction
        $glossary = [];

        if ($source_lang === 'de') {
            // German → target
            foreach ($german_terms as $term => $translations) {
                if (isset($translations[$target_lang])) {
                    $glossary[$term] = $translations[$target_lang];
                }
            }
        } elseif ($target_lang === 'de') {
            // Source → German (reverse lookup)
            foreach ($german_terms as $de_term => $translations) {
                if (isset($translations[$source_lang])) {
                    $glossary[$translations[$source_lang]] = $de_term;
                }
            }
            // Add source-specific terms
            if ($source_lang === 'ua') {
                foreach ($ukrainian_terms as $term => $translations) {
                    if (isset($translations['de'])) {
                        $glossary[$term] = $translations['de'];
                    }
                }
            } elseif ($source_lang === 'ru') {
                foreach ($russian_terms as $term => $translations) {
                    if (isset($translations['de'])) {
                        $glossary[$term] = $translations['de'];
                    }
                }
            }
        } else {
            // Non-German to Non-German (e.g., UA → RU)
            // Use German as bridge
            foreach ($german_terms as $de_term => $translations) {
                if (isset($translations[$source_lang]) && isset($translations[$target_lang])) {
                    $glossary[$translations[$source_lang]] = $translations[$target_lang];
                }
            }
        }

        return $glossary;
    }

    /**
     * Get primary language for rewrite (based on source)
     */
    public function get_rewrite_language($source_lang) {
        // Always rewrite in source language first, then translate
        return $source_lang;
    }

    /**
     * Smart translate - detects language if not provided
     */
    public function smart_translate($content, $target_lang, $source_lang = null) {
        if ($source_lang === null) {
            $source_lang = $this->detect_language($content);
        }

        if ($source_lang === $target_lang) {
            return ['success' => true, 'content' => $content, 'detected_lang' => $source_lang];
        }

        $result = $this->translate($content, $source_lang, $target_lang);
        $result['detected_lang'] = $source_lang;

        return $result;
    }
}
