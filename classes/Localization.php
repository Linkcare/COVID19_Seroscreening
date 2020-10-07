<?php

class Localization {
    const SUPPORTED_LOCALES = ["ca", "en", "es", "zh"];
    const DB_PREFIX = "KitInfo.Language.";

    // Private members
    private static $locale = "en";

    /**
     * Initializes the localization system in the language indicated by $locale
     *
     * @param string $locale: 2-Digit ISO language
     */
    static public function init($locale = "en") {
        $locale = strtolower($locale);
        if (in_array($locale, self::SUPPORTED_LOCALES)) {
            self::$locale = $locale;
        }
    }

    /**
     * Getter function for the language parameter in order to obtain the language of the site
     *
     * @return string the current language of the site
     */
    static public function getLang() {
        return self::$locale;
    }

    /**
     * Returns the text corresponding to the $key passed translated to the active language
     *
     * @param string $key
     * @return string
     */
    static public function translate($key, $replacements = null) {
        $description = self::searchDescription('Web', $key);
        if (!empty($replacements)) {
            foreach ($replacements as $key => $value) {
                $description = str_replace("{{" . $key . "}}", $value, $description);
            }
        }

        return $description;
    }

    /**
     * Returns the text corresponding to the $errorCode passed translated to the active language
     *
     * @param string $errorCode
     * @return string
     */
    static public function translateError($errorCode) {
        return self::searchDescription('Errors', $errorCode);
    }

    /**
     * Returns the text corresponding to the $status passed translated to the active language
     *
     * @param string $status
     * @return string
     */
    static public function translateStatus($status) {
        return self::searchDescription('Status', $status);
    }

    /**
     * Returns the text corresponding to the $language passed translated to the active language
     *
     * @param string $languageCode Index of the language from SUPPORTED_LOCALES array
     * @return string
     */
    static public function translateLanguage($languageCode) {
        return self::searchDescription('Language', self::DB_PREFIX . self::SUPPORTED_LOCALES[$languageCode]);
    }

    /**
     * Obtains the literal from the DB according to the group and language provided
     *
     * @param string $group
     * @param string $key
     * @return string
     */
    static private function searchDescription($group, $key) {
        $arrVariables[":descGroup"] = $group;
        $arrVariables[":lang"] = self::$locale;
        $arrVariables[":key"] = $key;
        $sql = "SELECT 
                    dt.DESCRIPTION
                FROM 
                    DESCRIPTION_TRANSLATIONS dt
                LEFT JOIN DESCRIPTIONS d ON dt.ID_DESCRIPTION = d.ID_DESCRIPTION
                WHERE d.DESCRIPTION_GROUP = :descGroup AND dt.ISO2_LANGUAGE IN (:lang, 'en') AND d.DESCRIPTION_KEY = :key
                ORDER BY CASE WHEN dt.ISO2_LANGUAGE = :lang THEN 0 ELSE 1 END";
        $result = Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);

        if ($result->Next()) {
            return $result->GetField('DESCRIPTION');
        } else {
            return $key;
        }
    }
}