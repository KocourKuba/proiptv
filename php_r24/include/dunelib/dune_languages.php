<?php

/**
 * NOTE: this file must be UTF-8 encoded
 */
class DuneLanguages
{
    private static $VALUES;

    public static function static_init()
    {
        self::$VALUES = array(
           array('english', 'English', 'English', 'en'),
           array('french', 'Français', 'French', 'fr'),
           array('german', 'Deutsch', 'German', 'de'),
           array('dutch', 'Nederlandse', 'Dutch', 'nl'),
           array('spanish', 'Español', 'Spanish', 'es'),
           array('italian', 'Italiano', 'Italian', 'it'),
           array('russian', 'Русский', 'Russian', 'ru'),
           array('ukrainian', 'Українська', 'Ukrainian', 'uk'),
           array('romanian', 'Român', 'Romanian', 'ro'),
           array('hungarian', 'Magyar', 'Hungarian', 'hu'),
           array('polish', 'Polski', 'Polish', 'pl'),
           array('greek', 'Ελληνικά', 'Greek', 'el'),
           array('danish', 'Dansk', 'Danish', 'da'),
           array('czech', 'Čeština', 'Czech', 'cs'),
           array('swedish', 'Svenska', 'Swedish', 'sv'),
           array('estonian', 'Eesti', 'Estonian', 'et'),
           array('slovak', 'Slovenčina', 'Slovak', 'sk'),
           array('turkish', 'Türk', 'Turkish', 'tr'),
           array('hebrew', 'עברית', 'Hebrew', 'he'),
           array('chinese', '中文', 'Chinese', 'zh'),
           array('chinese_simplified', '中文', 'Chinese Simplified', 'zh'),
           array('chinese_traditional', '中文', 'Chinese Traditional', 'zh'),
           array('japanese', '日本語', 'Japanese', 'ja'),
           array('abkhazian', 'Abkhazian', 'Abkhazian', 'ab'),
           array('afan', 'Afan', 'Afan', 'om'),
           array('afar', 'Afar', 'Afar', 'aa'),
           array('afrikaans', 'Afrikaans', 'Afrikaans', 'af'),
           array('albanian', 'Albanian', 'Albanian', 'sq'),
           array('amharic', 'Amharic', 'Amharic', 'am'),
           array('arabic', 'Arabic', 'Arabic', 'ar'),
           array('armenian', 'Armenian', 'Armenian', 'hy'),
           array('assamese', 'Assamese', 'Assamese', 'as'),
           array('aymara', 'Aymara', 'Aymara', 'ay'),
           array('azerbaijani', 'Azerbaijani', 'Azerbaijani', 'az'),
           array('bashkir', 'Bashkir', 'Bashkir', 'ba'),
           array('basque', 'Basque', 'Basque', 'eu'),
           array('bengali', 'Bengali', 'Bengali', 'bn'),
           array('bhutani', 'Bhutani', 'Bhutani', 'dz'),
           array('bihari', 'Bihari', 'Bihari', 'bh'),
           array('bislama', 'Bislama', 'Bislama', 'bi'),
           array('breton', 'Breton', 'Breton', 'br'),
           array('bulgarian', 'Bulgarian', 'Bulgarian', 'bg'),
           array('burmese', 'Burmese', 'Burmese', 'my'),
           array('byelorussian', 'Byelorussian', 'Byelorussian', 'be'),
           array('cambodian', 'Cambodian', 'Cambodian', 'km'),
           array('catalan', 'Catalan', 'Catalan', 'ca'),
           array('corsican', 'Corsican', 'Corsican', 'co'),
           array('croatian', 'Croatian', 'Croatian', 'hr'),
           array('esperanto', 'Esperanto', 'Esperanto', 'eo'),
           array('faroese', 'Faroese', 'Faroese', 'fo'),
           array('fiji', 'Fiji', 'Fiji', 'fj'),
           array('finnish', 'Finnish', 'Finnish', 'fi'),
           array('frisian', 'Frisian', 'Frisian', 'fy'),
           array('galician', 'Galician', 'Galician', 'gl'),
           array('georgian', 'Georgian', 'Georgian', 'ka'),
           array('greenlandic', 'Greenlandic', 'Greenlandic', 'kl'),
           array('guarani', 'Guarani', 'Guarani', 'gn'),
           array('gujarati', 'Gujarati', 'Gujarati', 'gu'),
           array('hausa', 'Hausa', 'Hausa', 'ha'),
           array('hindi', 'Hindi', 'Hindi', 'hi'),
           array('icelandic', 'Icelandic', 'Icelandic', 'is'),
           array('indonesian', 'Indonesian', 'Indonesian', 'id'),
           array('interlingua', 'Interlingua', 'Interlingua', 'ia'),
           array('interlingue', 'Interlingue', 'Interlingue', 'ie'),
           array('inuktitut', 'Inuktitut', 'Inuktitut', 'iu'),
           array('inupiak', 'Inupiak', 'Inupiak', 'ik'),
           array('irish', 'Irish', 'Irish', 'ga'),
           array('javanese', 'Javanese', 'Javanese', 'jv'),
           array('kannada', 'Kannada', 'Kannada', 'kn'),
           array('kashmiri', 'Kashmiri', 'Kashmiri', 'ks'),
           array('kazakh', 'Kazakh', 'Kazakh', 'kk'),
           array('kinyarwanda', 'Kinyarwanda', 'Kinyarwanda', 'rw'),
           array('kirghiz', 'Kirghiz', 'Kirghiz', 'ky'),
           array('kurundi', 'Kurundi', 'Kurundi', 'rn'),
           array('korean', 'Korean', 'Korean', 'ko'),
           array('kurdish', 'Kurdish', 'Kurdish', 'ku'),
           array('laothian', 'Laothian', 'Laothian', 'lo'),
           array('latin', 'Latin', 'Latin', 'la'),
           array('latvian', 'Latvian', 'Latvian', 'lv'),
           array('lingala', 'Lingala', 'Lingala', 'ln'),
           array('lithuanian', 'Lithuanian', 'Lithuanian', 'lt'),
           array('macedonian', 'Macedonian', 'Macedonian', 'mk'),
           array('malagasy', 'Malagasy', 'Malagasy', 'mg'),
           array('malay', 'Malay', 'Malay', 'ms'),
           array('malayalam', 'Malayalam', 'Malayalam', 'ml'),
           array('maltese', 'Maltese', 'Maltese', 'mt'),
           array('maori', 'Maori', 'Maori', 'mi'),
           array('marathi', 'Marathi', 'Marathi', 'mr'),
           array('moldavian', 'Moldavian', 'Moldavian', 'mo'),
           array('mongolian', 'Mongolian', 'Mongolian', 'mn'),
           array('nauru', 'Nauru', 'Nauru', 'na'),
           array('nepali', 'Nepali', 'Nepali', 'ne'),
           array('norwegian', 'Norwegian', 'Norwegian', 'no'),
           array('occitan', 'Occitan', 'Occitan', 'oc'),
           array('oriya', 'Oriya', 'Oriya', 'or'),
           array('pashto', 'Pashto', 'Pashto', 'ps'),
           array('persian', 'Persian', 'Persian', 'fa'),
           array('portuguese', 'Portuguese', 'Portuguese', 'pt'),
           array('punjabi', 'Punjabi', 'Punjabi', 'pa'),
           array('quechua', 'Quechua', 'Quechua', 'qu'),
           array('rhaeto_romance', 'Rhaeto-romance', 'Rhaeto-romance', 'rm'),
           array('samoan', 'Samoan', 'Samoan', 'sm'),
           array('sangho', 'Sangho', 'Sangho', 'sg'),
           array('sanskrit', 'Sanskrit', 'Sanskrit', 'sa'),
           array('gaelic', 'Gaelic', 'Gaelic', 'gd'),
           array('serbian', 'Serbian', 'Serbian', 'sr'),
           array('sesotho', 'Sesotho', 'Sesotho', 'st'),
           array('setswana', 'Setswana', 'Setswana', 'tn'),
           array('shona', 'Shona', 'Shona', 'sn'),
           array('sindhi', 'Sindhi', 'Sindhi', 'sd'),
           array('singhalese', 'Singhalese', 'Singhalese', 'si'),
           array('siswati', 'Siswati', 'Siswati', 'ss'),
           array('slovenian', 'Slovenian', 'Slovenian', 'sl'),
           array('somali', 'Somali', 'Somali', 'so'),
           array('sundanese', 'Sundanese', 'Sundanese', 'su'),
           array('swahili', 'Swahili', 'Swahili', 'sw'),
           array('tagalog', 'Tagalog', 'Tagalog', 'tl'),
           array('tajik', 'Tajik', 'Tajik', 'tg'),
           array('tamil', 'Tamil', 'Tamil', 'ta'),
           array('tatar', 'Tatar', 'Tatar', 'tt'),
           array('telugu', 'Telugu', 'Telugu', 'te'),
           array('thai', 'Thai', 'Thai', 'th'),
           array('tibetan', 'Tibetan', 'Tibetan', 'bo'),
           array('tigrinya', 'Tigrinya', 'Tigrinya', 'ti'),
           array('tonga', 'Tonga', 'Tonga', 'to'),
           array('tsonga', 'Tsonga', 'Tsonga', 'ts'),
           array('turkmen', 'Turkmen', 'Turkmen', 'tk'),
           array('twi', 'Twi', 'Twi', 'tw'),
           array('uigur', 'Uigur', 'Uigur', 'ug'),
           array('urdu', 'Urdu', 'Urdu', 'ur'),
           array('uzbek', 'Uzbek', 'Uzbek', 'uz'),
           array('vietnamese', 'Vietnamese', 'Vietnamese', 'vi'),
           array('volapuk', 'Volapuk', 'Volapuk', 'vo'),
           array('welsh', 'Welsh', 'Welsh', 'cy'),
           array('wolof', 'Wolof', 'Wolof', 'wo'),
           array('xhosa', 'Xhosa', 'Xhosa', 'xh'),
           array('yiddish', 'Yiddish', 'Yiddish', 'yi'),
           array('yoruba', 'Yoruba', 'Yoruba', 'yo'),
           array('zhuang', 'Zhuang', 'Zhuang', 'za'),
           array('zulu', 'Zulu', 'Zulu', 'zu'),
        );
    }

    public static function get_code_by_id($id)
    {
        foreach (self::$VALUES as $v)
        {
            if ($v[0] == $id)
                return $v[3];
        }
        return null;
    }

    public static function get_en_caption_by_code($id)
    {
        foreach (self::$VALUES as $v)
        {
            if ($v[3] == $id)
                return $v[2];
        }
        return null;
    }

    public static function get_native_caption_by_code($id)
    {
        foreach (self::$VALUES as $v)
        {
            if ($v[3] == $id)
                return $v[1];
        }
        return null;
    }
}

?>
