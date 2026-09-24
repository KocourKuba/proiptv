<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

/**
 * EPG description parser: the rules and the code that applies them.
 *
 * The file is published on the server (build/update_desc_parser.cmd) and updated there without a new
 * plugin release, the copy installed with the plugin is the fallback when the server is not available.
 * The plugin uses the newest REVISION of the downloaded and the installed file.
 *
 * Rules for this file:
 * - self-contained: nothing but the PHP 5.3 runtime and the PluginTvExtEpgProgram constants
 * - bump REVISION on every change, otherwise the plugin keeps the file it already has
 * - bump API (and Default_Dune_Plugin::DESC_PARSER_API) only when the interface below changes,
 *   a plugin loads only the file of its own API level
 * - keep the end marker as the last line, the plugin refuses a file without it (truncated download)
 */
class Desc_Parser
{
    const API = 1;
    const REVISION = 2026092501;

    /**
     * Stages: matcher pick (icon/detect) -> 'prepare' -> chunks -> 'cleanup' -> newline cap.
     * A rule is a pattern (the match is removed) or a [pattern, replacement] pair.
     * array('if' => needles, 'rules' => rules) is a group used only when the text contains one of the needles.
     *
     * @var array
     */
    public static $rules = array(
        'cleanup' => array(
            '/Нет описания/',
            '/Описание отсутствует/',
            '/Смотреть онлайн.*вас время!/',
            '/[Сс]мотрите.*на Wink/u',
            '/Описание[ \t]+последует\.*(?:[ \t]*Desk wait\.*)?/u',
            '/Донат.*/s',
            '/,\s{2}\((.*?)\)/',
            array(
                'if' => array('/', '@', 'www', 'WWW'),
                'rules' => array(
                    '/\n[^\n]{0,600}(?:https?:\/\/|www\.|[\w.+-]+@[\w-]+\.[a-z]{2,}\b|(?:t\.me|vk\.com|ok\.ru|wa\.me|fb\.com|dzen\.ru|youtu\.be|instagram\.com|facebook\.com|twitter\.com|rutube\.ru|boosty\.to|patreon\.com|bit\.ly)\/)[^\n]*/iu',
                    '/^[^\n]{0,600}(?:https?:\/\/|www\.|[\w.+-]+@[\w-]+\.[a-z]{2,}\b|(?:t\.me|vk\.com|ok\.ru|wa\.me|fb\.com|dzen\.ru|youtu\.be|instagram\.com|facebook\.com|twitter\.com|rutube\.ru|boosty\.to|patreon\.com|bit\.ly)\/)[^\n]*\n/iu',
                ),
            ),
            '/\n[^\n]{0,600}(?:\+\d[\d \t()-]{8,16}\d|\b8[ \t-]?\(?\d{3,4}\)?[ \t-]?\d{2,3}[ \t-]?\d{2}[ \t-]?\d{2}\b|\berid\s*[=:]|\bИНН[ \t]*:?\s*\d{9,12})[^\n]*/iu',
            '/^[^\n]{0,600}(?:\+\d[\d \t()-]{8,16}\d|\b8[ \t-]?\(?\d{3,4}\)?[ \t-]?\d{2,3}[ \t-]?\d{2}[ \t-]?\d{2}\b|\berid\s*[=:]|\bИНН[ \t]*:?\s*\d{9,12})[^\n]*\n/iu',
            '/(?:\n[ \t]*\d{1,2}:\d{2}(?::\d{2})?[^\n]*){2,}/u',
            '/\n[ \t]*\d{1,2}:\d{2}(?::\d{2})?[ \t]*[-–—][^\n]*/u',
            '/Реклама\.[ \t]*(?:ООО|ИП|ТОО|АО|ОАО|ЗАО)[^\n]*/u',
            '/\n[^\n\p{L}\d \t]{1,6}[ \t]*[^\n:]{1,28}:[ \t]*(?=\n|$)/u',
            '/\n[^\n:]{0,24}(?:[Сс]оцсети|социальны[ех] сет|[Тт]елеграм|Telegram|[Ии]нстаграм|Instagram|[Вв][Кк]онтакте|Facebook|[Сс]сылк|[Ии]сточники|[Тт]айм-?коды|[Пп]атрион|Patreon|[Дд]онат|[Сс]отрудничеств|[Пп]ромокод|[Пп]одпиш|[Пп]оддерж|[Пп]ереходите|[Сс]мотрите больше|[Зз]аказ авто)[^\n:]{0,24}:[ \t]*(?=\n|$)/u',
            '/\n[^\n\p{L}\d]{3,}(?=\n|$)/u',
            array(
                'if' => array('#'),
                'rules' => array(
                    '/(?:(?:^|[ \t\n])#[^\s#]*\p{L}[^\s#]*){2,}/u',
                    '/(?:^|[ \t\n])#[^\s#]*\p{L}[^\s#]*\s*$/u',
                ),
            ),
            array(
                'if' => array('@', '://', 'www.'),
                'rules' => array(
                    '/(?:^|[ \t\n])@[A-Za-z0-9_.]{2,30}\b/u',
                    '/[ \t]*(?:https?:\/\/|www\.)\S+/u',
                    '/[ \t]*[\w.+-]+@[\w-]+\.[a-z]{2,}\b/iu',
                ),
            ),
            '/^Рейтинг:?[ \t]*(?:\n|$)/u',
            '/\nРейтинг:?[ \t]*(?=\n|$)/u',
            '/^[ \t]*\d+[ \t]*(?:ч(?:ас(?:а|ов)?)?\.?(?:[ \t]*\d+[ \t]*мин(?:ут[аы]?)?\.?)?|мин(?:ут[аы]?)?\.?)[ \t]*(?:\n|$)/u',
            '/\n[ \t]*\d+[ \t]*(?:ч(?:ас(?:а|ов)?)?\.?(?:[ \t]*\d+[ \t]*мин(?:ут[аы]?)?\.?)?|мин(?:ут[аы]?)?\.?)[ \t]*(?=\n|$)/u',
            '/^[ \t.,;:]+(?:\n|$)/',
            '/\n[ \t.,;:]+(?=\n|$)/',
        ),
        'prepare' => array(
            array(
                '/\n[ \t]*(?:[-=_*~─━═▬╾╼][ \t]?){3,}\.?[ \t]*(?=\n|$)/u',
                "\n",
            ),
            '/^[ \t]*(?:[-=_*~─━═▬╾╼][ \t]?){3,}\.?[ \t]*(?:\n|$)/u',
            array(
                '/\n[ \t]*[-=_*~─━═▬╾╼]{3,}[^\n]{0,40}?[-=_*~─━═▬╾╼]{3,}[ \t]*(?=\n|$)/u',
                "\n",
            ),
            array('/-----+\.?[ \t]*(?=\n|$)/', "\n"),
            array('/=====+\.?[ \t]*(?=\n|$)/', "\n"),
            array('/_____+\.?[ \t]*(?=\n|$)/', "\n"),
            array('/(?:─|━|═|▬|╾|╼){5,}\.?[ \t]*(?=\n|$)/u', "\n"),
            '/^Продолжительность: \d+ мин\.?[ \t]*(?:\n|$)/u',
            '/\nПродолжительность: \d+ мин\.?[ \t]*(?=\n|$)/u',
            '/Продолжительность[ \t]*(?::|▪|►)[ \t]*\d+[ \t]*(?:ч(?:ас(?:а|ов)?)?\.?(?:[ \t]*\d+[ \t]*мин(?:ут[аы]?)?\.?)?|мин(?:ут[аы]?)?\.?)/u',
            array(
                '/\][ \t]+\d+[ \t]*(?:ч(?:ас(?:а|ов)?)?\.?(?:[ \t]*\d+[ \t]*мин(?:ут[аы]?)?\.?)?|мин(?:ут[аы]?)?\.?)[ \t]*(?=\n|$)/u',
                ']',
            ),
            array(
                '/(?<!ч)\.[ \t]+\d+[ \t]*(?:ч(?:ас(?:а|ов)?)?\.?(?:[ \t]*\d+[ \t]*мин(?:ут[аы]?)?\.?)?|мин(?:ут[аы]?)?\.?)[ \t]*(?=\n|$)/u',
                '.',
            ),
            '/Наш Рейтинг[ \t]*\[[^\]\n]*\]/u',
            '/Кино[Пп]оиск[ \t]*[:▪]?[ \t]*\[[–-]?\]/u',
            '/IMDb[ \t]*[:▪]?[ \t]*\[[–-]?\]/u',
            array(
                '/^\("[^"\n]*", ([^,()\n]+), (?:[A-Z]{1,3}(?:\/[A-Z]{1,3})* )?(\d{4})\)\.\.\./u',
                "Жанр: \$1.\nГод: \$2\n",
            ),
            array(
                '/^\("[^"\n]*", ([^,()\n]+)(?:, [A-Z]{1,3}(?:\/[A-Z]{1,3})*)?\)\.\.\./u',
                "Жанр: \$1.\n",
            ),
            array(
                '/^(Страна: [^.\n]+\. )?((?:S\d+,)?Ep\d+)\. [^\n]*? \2 \(([^)\n]+)\), (\d{4})\.\.\./u',
                "\$1Год: \$4\n\$2 (\$3)\n",
            ),
            array('/(Ep\.\d+ "[^"\n]+")\.?[ \t]*\((\d{4})\)/u', '$1. Год: $2.'),
            array('/\. (?:[^\n.]{1,60}? )?(\d{4})\.\.\./u', ".\nГод: \$1\n"),
            array(
                'if' => array('▪', '►'),
                'rules' => array(
                    array(
                        '/^▪ сз\. (\d+),[ \t]*▪ с\. (\d+)\.[ \t]*/u',
                        "\$1 сезон, \$2-я серия\n",
                    ),
                    '/Прод\.[ \t]*▪[ \t]*\d+[ \t]*(?:ч(?:ас(?:а|ов)?)?\.?(?:[ \t]*\d+[ \t]*мин(?:ут[аы]?)?\.?)?|мин(?:ут[аы]?)?\.?)/u',
                    '/▪[ \t]*\d+[ \t]*(?:ч(?:ас(?:а|ов)?)?\.?(?:[ \t]*\d+[ \t]*мин(?:ут[аы]?)?\.?)?|мин(?:ут[аы]?)?\.?)(?=[ \t]*(?:▪|\n|$))/u',
                    array(
                        '/►[ \t]*Режисс[её]р: ([^\n\/]{2,80}?) \/ /u',
                        "Режиссёр: \$1\n",
                    ),
                    array(
                        '/^●[ \t]*([^▪●\n]+?)[ \t]*▪[ \t]*(\d{4})\. г\.[ \t]*●\.?[ \t]*/u',
                        "Жанр: \$1.\nГод: \$2\n",
                    ),
                    array(
                        '/^●[ \t]*([^▪●\n]+?)[ \t]*▪[ \t]*●\.?[ \t]*/u',
                        "Жанр: \$1.\n",
                    ),
                    array('/(?<!\p{L})Режия(?=[ \t]*[▪►:])/u', 'Режиссер'),
                    array(
                        '/(?<!\p{L})Оригинал(?=[ \t]*[▪►])/u',
                        'Оригинальное название',
                    ),
                    array('/(?<!\p{L})Cлоган(?=[ \t]*[▪►])/u', 'Слоган'),
                    array('/(?<!\p{L})Рейтинг[ \t]*▪[ \t]*/u', "\n"),
                    array(
                        '/(?<![^\n])(Жанр|Страна|Год|Режисс[её]ры?|В [Рр]олях|Сценари[йи]|Сценаристы?|Композиторы?|Операторы?|Продюсеры?|Ведущ(?:ий|ая|ие)|Оригинальное название|Альтернативное название|Бюджет|Премьера|Награды|Производство|Художники?|Официальный слоган|Российский слоган|Слоган|Из серии|Автор|Рейтинг IMDb|IMDb|Кино[Пп]оиск)[ \t]*(?:▪|►)[ \t]*/u',
                        '$1: ',
                    ),
                    array(
                        '/(?<!\p{L})(Жанр|Страна|Год|Режисс[её]ры?|В [Рр]олях|Сценари[йи]|Сценаристы?|Композиторы?|Операторы?|Продюсеры?|Ведущ(?:ий|ая|ие)|Оригинальное название|Альтернативное название|Бюджет|Премьера|Награды|Производство|Художники?|Официальный слоган|Российский слоган|Слоган|Из серии|Автор|Рейтинг IMDb|IMDb|Кино[Пп]оиск)[ \t]*(?:▪|►)[ \t]*/u',
                        "\n\$1: ",
                    ),
                    '/(?:▪|►)[ \t]*(?=\[\d{1,2} ?\+\])/u',
                    '/(?:▪|►)[ \t]*(?=\n|$)/u',
                    '/(?<![^\n])(?:►|▶|▪)\x{FE0F}?[ \t]*(?:\.[ \t]+)?/u',
                    array('/►\x{FE0F}?[ \t]*/u', "\n"),
                    array('/[ \t]+▪[ \t]+/u', ', '),
                ),
            ),
            array('/\[(\d{1,2}) \+\]/', '[$1+]'),
            array('/,\.(?=[ \t]*(?:\n|$))/', '.'),
            '/(?<![^\n])▶\x{FE0F}?[ \t]*/u',
            array('/\n[ \t]*\.\.\.[ \t]*(?=\S)/u', "\n"),
            array(
                '/Название оригинала:/u',
                'Оригинальное название:',
            ),
            array('/,(?:[ \t]+,)+/u', ','),
            array('/,[ \t]{2,}/u', ', '),
            '/^\s+/u',
        ),
        'matchers' => array(
            'mail.ru' => array(
                'icon' => array('resizer.mail.ru', 'kinopoisk-ru'),
                'chunks' => array(
                    'year' => '/(?:Год|Гoд|Year): (\d{4}(?:[ \t]*[-–—][ \t]*(?:\d{4}|по н\.[ \t]?в\.?))?)(?:[ \t]*\([^)\n]*\))?\.?/u',
                    'country' => '/Страна: (.*?)(?:\.|\s{2}|\n|$)/u',
                    'genre' => '/Жанр: (.*?)(?:\.|(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\n|$)/u',
                    'imdb_rating' => '/(?:Рейтинг:?\s*)?IMDb\s*:?\s*\[?(\d+(?:[.,]\d+)?)\]?/u',
                    'kp_rating' => '/(?:Рейтинг\s+)?Кино[Пп]оиск[аи]?\s*:?\s*\[?(\d+(?:[.,]\d+)?)\]?/u',
                    'km_rating' => '/(?:Рейтинг\s+)?KinoMail\s*\[(.*?)\]\.?/u',
                    'director' => '/Режисс[её]ры?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'actor' => '/В [Рр]олях: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'writer' => '/Сценари(?:й|ст[ыа]?): (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'editor' => '/Операторы?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'composer' => '/Композиторы?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'rating' => '/Рейтинг: \((.*?)\)/u',
                    'producer' => '/Продюсеры?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'presenter' => '/Ведущ[а-яё]*: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'budget' => '/Бюджет: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'original_name' => '/Оригинальное название: (.*?)\.?(?:\n|$|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                ),
            ),
            '24h.tv' => array(
                'icon' => 'media.24h.tv',
                'icon_suffix' => '?cover=true&w=320&h=180&crop=true',
                'chunks' => array(
                    'year' => '/(?:Год|Гoд|Year): (\d{4}(?:[ \t]*[-–—][ \t]*(?:\d{4}|по н\.[ \t]?в\.?))?)(?:[ \t]*\([^)\n]*\))?\.?/u',
                    'country' => '/Страна: (.*?)(?:\.|\n|$)/u',
                    'genre' => '/Жанр: (.*?)(?:\.|(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\n|$)/u',
                    'imdb_rating' => '/(?:Рейтинг:?\s*)?IMDb\s*:?\s*\[?(\d+(?:[.,]\d+)?)\]?/u',
                    'kp_rating' => '/(?:Рейтинг\s+)?Кино[Пп]оиск[аи]?\s*:?\s*\[?(\d+(?:[.,]\d+)?)\]?/u',
                    'director' => '/Режисс[её]ры?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'actor' => '/В [Рр]олях: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'writer' => '/Сценари(?:й|ст[ыа]?): (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'editor' => '/Операторы?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'composer' => '/Композиторы?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'producer' => '/Продюсеры?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'presenter' => '/Ведущ[а-яё]*: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                ),
            ),
            'teleman' => array(
                'detect' => '/(?:^|\n)Opis:\s|^[^\n"()]{1,40}\s\d{4}\s"[^"]+"/u',
                'extends' => 'default',
                'chunks' => array(
                    'country' => '/^([^\n"]{2,60}?)(?=\s\d{4}\s")/u',
                    'year' => '/^\s*(\d{4})(?=\s")/u',
                    'original_name' => '/^\s*"([^"]+)"/u',
                    'genre' => '/^\s*\(([^)]+)\)/u',
                    'age' => '/^\s*\[(\d+\+)\]/u',
                    'fw_rating' => '/FilmWeb\s*\[([\d.,]+)\]/u',
                    'opis' => '/(?:^|\n)(Opis):\s*/u',
                ),
            ),
            'tvdirekt' => array(
                'detect' => '/^\s*"[^"]{2,90}",/u',
                'extends' => 'default',
                'chunks' => array(
                    'original_name' => '/^\s*"([^"]+)",/u',
                ),
            ),
            'tvpassport' => array(
                'detect' => '/^Жанр: [^\n]+\.\s[^\n]*\([^)]+\)\.\./u',
                'extends' => 'default',
                'chunks' => array(
                    'sub_title' => '/(?<=\.\s)([^\n.(]{1,60}\([^)]+\))(?=\.\.)/u',
                ),
            ),
            'default' => array(
                'chunks' => array(
                    'country' => '/Страна: (.*?)(?:\.|,\s*(?=(?:Год|Гoд|Year(?!s)|Жанр|Страна)\s*:|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|(?=Количество|Хронометраж|Производство|Жанр|Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\n|$)/u',
                    'year' => array(
                        '/(?:Год|Гoд|Year): (\d{4}(?:[ \t]*[-–—][ \t]*(?:\d{4}|по н\.[ \t]?в\.?))?)(?:[ \t]*\([^)\n]*\))?\.?/u',
                        '/^(?:[^\n.]{1,60}? )?(\d{4})\.\.\./u',
                    ),
                    'genre' => array(
                        '/Жанр: (.*?)(?:\.|(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\n|$)/u',
                        '/Category: (.*?)[;\n]/u',
                    ),
                    'imdb_rating' => '/(?:Рейтинг:?\s*)?IMDb\s*:?\s*\[?(\d+(?:[.,]\d+)?)\]?/u',
                    'kp_rating' => '/(?:Рейтинг\s+)?Кино[Пп]оиск[аи]?\s*:?\s*\[?(\d+(?:[.,]\d+)?)\]?/u',
                    'director' => array(
                        '/Режисс[её]ры?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                        '/Реж\.: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    ),
                    'actor' => array(
                        '/В [Рр]олях: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                        '/Акт[её]ры: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    ),
                    'composer' => '/Композиторы?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'writer' => '/Сценари(?:й|ст[ыа]?): (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'editor' => '/Операторы?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'producer' => '/Продюсеры?: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'presenter' => '/Ведущ[а-яё]*: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'budget' => '/Бюджет: (.*?)(?:\.?\n|\.?$|\.\s+(?=Режисс|Реж\.|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s)|Рейтинг|IMDb|Кино[Пп]оиск|KinoMail)|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Оригинальное|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'original_name' => '/Оригинальное название: (.*?)\.?(?:\n|$|\s+(?=(?:Режисс|Реж|В [Рр]олях|Акт[её]ры|Сценари|Оператор|Композитор|Продюсер|Бюджет|Ведущ|Жанр|Страна|Год|Гoд|Year(?!s))[^:\n]{0,14}:))/u',
                    'sub_title' => array(
                        '/^(S\.\d+ Ep\.\d+:[^\n]*?)\.?[ \t]+\1(?=\s|$)[ \t]*/u',
                        '/(?<=^|\. )((?:S\.\d+,\s?)?Ep\.\d+ "[^"\n]+")\.?[ \t]*/u',
                        '/^((?:S\d+,)?Ep\d+ \([^)\n]+\))\n/u',
                        '/^(\d+-я с\. - "[^"\n]+")\.?[ \t]*/u',
                        '/^((?:Сезон \d+\. )?Серия \d+)\.[ \t]*/u',
                        '/^\[([^\]\n\d][^\]\n]{1,60})\][ \t]*/u',
                        '/^((?:\d+ сезон, )?\d+-я(?: и \d+-я)? сери(?:я|и))[ \t]*(?:\n|$)/u',
                        '/\n((?:\d+ сезон, )?\d+-я(?: и \d+-я)? сери(?:я|и))(?=[ \t]*(?:\n|$))/u',
                    ),
                ),
            ),
        ),
    );

    /**
     * Parse epg description for extended epg tags
     *
     * @param string $title
     * @param string $raw_descr
     * @param string $icon
     * @return array
     */
    public static function reformat($title, $raw_descr, $icon)
    {
        $desc_parsers = self::$rules;

        $result = array();
        $result[PluginTvExtEpgProgram::title] = $title;
        $result[PluginTvExtEpgProgram::desc] = $raw_descr;
        $result[PluginTvExtEpgProgram::main_icon] = $icon;

        if (empty($raw_descr)) {
            return $result;
        }

        // some sources (tv team) use \r\n, all rules expect \n
        $raw_descr = str_replace("\r\n", "\n", $raw_descr);

        $find_chunks = function (&$total, $chunks, $raw_descr) {
            foreach ($chunks as $key => $pattern) {
                // $items must be rebuilt for each key, otherwise patterns of the
                // previous keys are kept and retried before the current one
                $items = is_string($pattern) ? array($pattern) : $pattern;
                foreach ($items as $item) {
                    $m = preg_split($item, $raw_descr, 0, PREG_SPLIT_DELIM_CAPTURE);
                    if (!isset($m[1])) continue;

                    $total[$key] = trim($m[1]);
                    $raw_descr = preg_replace($item, '', $raw_descr);
                    break;
                }
            }

            return trim($raw_descr, ", \n\r\t\v\0");
        };

        // Pick the matcher from the config: 'icon' matches the picon host, 'detect' matches
        // the shape of the description itself (needed because a provider may fall back to its
        // own picon host for any feed). 'default' has neither and is used when nothing matched.
        $matchers = isset($desc_parsers['matchers']) ? $desc_parsers['matchers'] : array();
        $matcher = 'default';
        foreach ($matchers as $name => $cfg) {
            $found = false;
            if (isset($cfg['icon'])) {
                foreach ((array)$cfg['icon'] as $needle) {
                    if (strpos($icon, $needle) !== false) {
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found && isset($cfg['detect'])) {
                $found = (bool)preg_match($cfg['detect'], $raw_descr);
            }
            if ($found) {
                $matcher = $name;
                break;
            }
        }

        if (isset($matchers[$matcher]['icon_suffix'])) {
            $icon .= $matchers[$matcher]['icon_suffix'];
        }

        // a matcher may define only what is special about its format and inherit the rest.
        // '+' keeps the matcher's own keys (and their order) and appends the missing ones.
        $chunks = isset($matchers[$matcher]['chunks']) ? $matchers[$matcher]['chunks'] : array();
        if (isset($matchers[$matcher]['extends'])) {
            $base = $matchers[$matcher]['extends'];
            if (isset($matchers[$base]['chunks'])) {
                $chunks += $matchers[$base]['chunks'];
            }
        }

        // a rule is either a pattern (the match is removed) or a [pattern, replacement] pair.
        // {"if": [needles], "rules": [...]} is a group used only when the text contains one of the needles:
        // every /u pattern costs a few us even without a match, which adds up on the box.
        // all rules taken go into one preg_replace call, which applies them in order.
        $apply_rules = function ($rules, $raw_descr) {
            $patterns = array();
            $replacements = array();
            foreach ($rules as $rule) {
                // is_array first: php 5.3 answers isset($string['rules']) with true
                if (is_array($rule) && isset($rule['rules'])) {
                    $found = false;
                    foreach ((array)$rule['if'] as $needle) {
                        if (strpos($raw_descr, $needle) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    $group = $found ? $rule['rules'] : array();
                } else {
                    $group = array($rule);
                }

                foreach ($group as $item) {
                    $patterns[] = is_array($item) ? $item[0] : $item;
                    $replacements[] = is_array($item) ? $item[1] : '';
                }
            }
            return empty($patterns) ? $raw_descr : preg_replace($patterns, $replacements, $raw_descr);
        };

        // 'prepare' rewrites decorated layouts (e.g. "Жанр ▪ драма ► Год ▪ 1990") to the plain
        // "Label: value" lines the chunks expect, so it runs before them and for every matcher
        if (isset($desc_parsers['prepare'])) {
            $raw_descr = $apply_rules($desc_parsers['prepare'], $raw_descr);
        }

        $parsed = array();
        if (!empty($chunks)) {
            $raw_descr = $find_chunks($parsed, $chunks, $raw_descr);
        }

        if (isset($desc_parsers['cleanup'])) {
            $raw_descr = $apply_rules($desc_parsers['cleanup'], $raw_descr);
        }

        $raw_descr = str_replace(array('“', '”'), '', $raw_descr);
        $raw_descr = str_replace(array('<br>', "<'>br>"), "\n", $raw_descr);
        // keep paragraphs, but no more than one empty line between them
        $raw_descr = preg_replace(array('/[ \t]+(?=\n)/', '/\n{3,}/'), array('', "\n\n"), $raw_descr);
        $raw_descr = trim($raw_descr, " .,;\n\r\t\v\0");

        $result[PluginTvExtEpgProgram::desc] = $raw_descr;
        $result[PluginTvExtEpgProgram::main_icon] = $icon;

        if (isset($parsed['genre']))
            $result[PluginTvExtEpgProgram::main_category] = $parsed['genre'];
        if (isset($parsed['sub_title']))
            $result[PluginTvExtEpgProgram::sub_title] = $parsed['sub_title'];
        if (isset($parsed['year']))
            $result[PluginTvExtEpgProgram::year] = $parsed['year'];
        if (isset($parsed['country']))
            $result[PluginTvExtEpgProgram::country] = $parsed['country'];
        if (isset($parsed['director']))
            $result[PluginTvExtEpgProgram::director] = $parsed['director'];
        if (isset($parsed['actor']))
            $result[PluginTvExtEpgProgram::actor] = $parsed['actor'];
        if (isset($parsed['producer']))
            $result[PluginTvExtEpgProgram::producer] = $parsed['producer'];
        if (isset($parsed['imdb_rating']))
            $result[PluginTvExtEpgProgram::imdb_rating] = $parsed['imdb_rating'];
        if (isset($parsed['kp_rating']))
            $result[PluginTvExtEpgProgram::kp_rating] = $parsed['kp_rating'];
        if (isset($parsed['km_rating']))
            $result[PluginTvExtEpgProgram::km_rating] = $parsed['km_rating'];
        if (isset($parsed["writer"]))
            $result[PluginTvExtEpgProgram::writer] = $parsed['writer'];
        if (isset($parsed["editor"]))
            $result[PluginTvExtEpgProgram::editor] = $parsed['editor'];
        if (isset($parsed["composer"]))
            $result[PluginTvExtEpgProgram::composer] = $parsed['composer'];
        if (isset($parsed["presenter"]))
            $result[PluginTvExtEpgProgram::presenter] = $parsed['presenter']; //Ведущий

        return $result;
    }
}
// end of Desc_Parser
