<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\LanguagesManager\Commands;

use Aws\CloudFront\Exception\Exception;
use Piwik\Container\StaticContainer;
use Piwik\Filesystem;
use Piwik\Http;
use Piwik\Piwik;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console Command to generate Intl-data files for Piwik
 *
 * This script uses the master data of unicode-cldr/cldr-localenames-full repository to fetch available translations
 */
class GenerateIntl extends TranslationBase
{
    protected function configure()
    {
        $this->setName('translations:generate-intl-data')
             ->setDescription('Generates Intl-data for Piwik');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $piwikLanguages = \Piwik\Plugins\LanguagesManager\API::getInstance()->getAvailableLanguages();

        $aliasesUrl = 'https://raw.githubusercontent.com/unicode-cldr/cldr-core/master/supplemental/aliases.json';
        $aliasesData = Http::fetchRemoteFile($aliasesUrl);
        $aliasesData = json_decode($aliasesData, true);
        $aliasesData = $aliasesData['supplemental']['metadata']['alias']['languageAlias'];

        foreach ($piwikLanguages AS $langCode) {

            if ($langCode == 'dev') {
                continue;
            }

            $requestLangCode = $langCode;

            if (substr_count($langCode, '-') == 1) {
                $langCodeParts = explode('-', $langCode, 2);
                $requestLangCode = sprintf('%s-%s', $langCodeParts[0], strtoupper($langCodeParts[1]));
            }

            if (array_key_exists($requestLangCode, $aliasesData)) {
                $requestLangCode = $aliasesData[$requestLangCode]['_replacement'];
            }

            // fix some locales
            $localFixes = array(
                'pt' => 'pr-PT',
                'pt-br' => 'pt',
                'zh-cn' => 'zh-Hans',
                'zh-tw' => 'zh-Hant'
            );

            if (array_key_exists($langCode, $localFixes)) {
                $requestLangCode = $localFixes[$langCode];
            }

            $this->fetchLanguageData($output, $langCode, $requestLangCode);
            $this->fetchTerritoryData($output, $langCode, $requestLangCode);
            $this->fetchCalendarData($output, $langCode, $requestLangCode);
        }
    }

    protected function fetchLanguageData(OutputInterface $output, $langCode, $requestLangCode)
    {
        $languageCodes = array_keys(StaticContainer::get('Piwik\Intl\Data\Provider\LanguageDataProvider')->getLanguageList());

        $languageDataUrl = 'https://raw.githubusercontent.com/unicode-cldr/cldr-localenames-full/master/main/%s/languages.json';
        $languageWritePath = Filesystem::getPathToPiwikRoot() . '/core/Intl/Data/Resources/languages/%s.json';

        try {
            $languageData = Http::fetchRemoteFile(sprintf($languageDataUrl, $requestLangCode));
            $languageData = json_decode($languageData, true);
            $languageData = $languageData['main'][$requestLangCode]['localeDisplayNames']['languages'];

            $translations = (array) @json_decode(file_get_contents(sprintf($languageWritePath, $langCode)));

            if (empty($translations)) {
                $translations = array_fill_keys($languageCodes, '');
            }

            foreach ($languageCodes AS $code) {
                if (!empty($languageData[$code]) && $languageData[$code] != $code) {
                    $translations[$code] = $languageData[$code];
                }
            }

            file_put_contents(sprintf($languageWritePath, $langCode), json_encode($translations, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            $output->writeln('Saved language data for '.$langCode);
        } catch (Exception $e) {
            $output->writeln('Unable to import language data for '.$langCode);
        }
    }

    protected function fetchTerritoryData(OutputInterface $output, $langCode, $requestLangCode)
    {
        $territoryDataUrl = 'https://raw.githubusercontent.com/unicode-cldr/cldr-localenames-full/master/main/%s/territories.json';
        $countryWritePath = Filesystem::getPathToPiwikRoot() . '/core/Intl/Data/Resources/countries/%s.json';
        $continentWritePath = Filesystem::getPathToPiwikRoot() . '/core/Intl/Data/Resources/continents/%s.json';

        $countryCodes = array_keys(StaticContainer::get('Piwik\Intl\Data\Provider\RegionDataProvider')->getCountryList());
        $countryCodes = array_map('strtoupper', $countryCodes);

        $continentMapping = array(
            "afr" => "002",
            "amc" => "013",
            "amn" => "003",
            "ams" => "005",
            "ant" => "AQ",
            "asi" => "142",
            "eur" => "150",
            "oce" => "009"
        );

        try {
            $territoryData = Http::fetchRemoteFile(sprintf($territoryDataUrl, $requestLangCode));
            $territoryData = json_decode($territoryData, true);
            $territoryData = $territoryData['main'][$requestLangCode]['localeDisplayNames']['territories'];

            $countryTranslations = (array) @json_decode(file_get_contents(sprintf($countryWritePath, $langCode)));

            if (empty($countryTranslations)) {
                $countryTranslations = array_fill_keys($countryCodes, '');
            }

            foreach ($countryCodes AS $code) {
                if (!empty($territoryData[$code]) && $territoryData[$code] != $code) {
                    $countryTranslations[$code] = $territoryData[$code];
                }
            }

            file_put_contents(sprintf($countryWritePath, $langCode), json_encode($countryTranslations, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

            $continentTranslations = (array) @json_decode(file_get_contents(sprintf($continentWritePath, $langCode)));

            foreach ($continentMapping as $shortCode => $code) {
                if (!empty($territoryData[$code]) && $territoryData[$code] != $code) {
                    $continentTranslations[$shortCode] = $territoryData[$code];
                }
            }

            file_put_contents(sprintf($continentWritePath, $langCode), json_encode($continentTranslations, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

            $output->writeln('Saved territory data for '.$langCode);
        } catch (Exception $e) {
            $output->writeln('Unable to import territory data for '.$langCode);
        }
    }

    protected function fetchCalendarData(OutputInterface $output, $langCode, $requestLangCode)
    {
        $calendarDataUrl = 'https://raw.githubusercontent.com/unicode-cldr/cldr-dates-full/master/main/%s/ca-gregorian.json';
        $calendarWritePath = Filesystem::getPathToPiwikRoot() . '/core/Intl/Data/Resources/calendar/%s.json';

        try {
            $calendarData = Http::fetchRemoteFile(sprintf($calendarDataUrl, $requestLangCode));
            $calendarData = json_decode($calendarData, true);
            $calendarData = $calendarData['main'][$requestLangCode]['dates']['calendars']['gregorian'];

            $calendarTranslations = (array) @json_decode(file_get_contents(sprintf($calendarWritePath, $langCode)), true);

            for ($i=1; $i<=12; $i++) {
                $calendarTranslations['months']['short'][$i] = $calendarData['months']['format']['abbreviated'][$i];
                $calendarTranslations['months']['long'][$i] = $calendarData['months']['format']['wide'][$i];
            }

            $months = array(
                'sun',
                'mon',
                'tue',
                'wed',
                'thu',
                'fri',
                'sat'
            );

            foreach ($months AS $month) {
                $calendarTranslations['days']['short'][$month] = $calendarData['days']['format']['short'][$month];
                $calendarTranslations['days']['long'][$month] = $calendarData['days']['format']['wide'][$month];
            }

            file_put_contents(sprintf($calendarWritePath, $langCode), json_encode($calendarTranslations, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

            $output->writeln('Saved calendar data for '.$langCode);
        } catch (Exception $e) {
            $output->writeln('Unable to import calendar data for '.$langCode);
        }
    }


}
