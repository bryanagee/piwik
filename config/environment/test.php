<?php

use Interop\Container\ContainerInterface;
use Piwik\Common;
use Piwik\DbHelper;
use Piwik\Option;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\Mock\TestConfig;
use Piwik\Tests\Framework\TestingEnvironmentVariables;

return array(

    // Disable logging
    'Psr\Log\LoggerInterface' => DI\object('Psr\Log\NullLogger'),

    'Piwik\Cache\Backend' => function () {
        return \Piwik\Cache::buildBackend('file');
    },
    'cache.eager.cache_id' => 'eagercache-test-',

    // Disable loading core translations
    'Piwik\Translation\Translator' => DI\decorate(function ($previous, ContainerInterface $c) {
        $testingEnvironment = $c->get('Piwik\Tests\Framework\TestingEnvironmentVariables');
        if (!$testingEnvironment->loadRealTranslations) {
            return new \Piwik\Translation\Translator($c->get('Piwik\Translation\Loader\LoaderInterface'), $directories = array());
        } else {
            return $previous;
        }
    }),

    'Piwik\Config' => DI\decorate(function ($previous, ContainerInterface $c) {
        $testingEnvironment = $c->get('Piwik\Tests\Framework\TestingEnvironmentVariables');
        if (!$testingEnvironment->dontUseTestConfig) {
            $settingsProvider = $c->get('Piwik\Application\Kernel\GlobalSettingsProvider');
            return new TestConfig($settingsProvider, $testingEnvironment, $allowSave = false, $doSetTestEnvironment = true);
        } else {
            return $previous;
        }
    }),

    'observers.global' => DI\add(array(

        array('Access.createAccessSingleton', function () {
            $testingEnvironment = new TestingEnvironmentVariables();
            if ($testingEnvironment->testUseMockAuth) {
                $access = new FakeAccess();
                FakeAccess::$superUser = true;
                FakeAccess::$superUserLogin = 'superUserLogin';
                \Piwik\Access::setSingletonInstance($access);
            }
        }),

        array('AssetManager.getStylesheetFiles', function (&$stylesheets) {
            $testingEnvironment = new TestingEnvironmentVariables();
            if ($testingEnvironment->useOverrideCss) {
                $stylesheets[] = 'tests/resources/screenshot-override/override.css';
            }
        }),

        array('AssetManager.getJavaScriptFiles', function (&$jsFiles) {
            $testingEnvironment = new TestingEnvironmentVariables();
            if ($testingEnvironment->useOverrideJs) {
                $jsFiles[] = 'tests/resources/screenshot-override/override.js';
            }
        }),

        array('Updater.checkForUpdates', function () {
            try {
                @\Piwik\Filesystem::deleteAllCacheOnUpdate();
            } catch (Exception $ex) {
                // pass
            }
        }),

        array('Test.Mail.send', function (\Zend_Mail $mail) {
            $outputFile = PIWIK_INCLUDE_PATH . '/tmp/' . Common::getRequestVar('module', '') . '.' . Common::getRequestVar('action', '') . '.mail.json';
            $outputContent = str_replace("=\n", "", $mail->getBodyText($textOnly = true));
            $outputContent = str_replace("=0A", "\n", $outputContent);
            $outputContent = str_replace("=3D", "=", $outputContent);
            $outputContents = array(
                'from' => $mail->getFrom(),
                'to' => $mail->getRecipients(),
                'subject' => $mail->getSubject(),
                'contents' => $outputContent
            );
            file_put_contents($outputFile, json_encode($outputContents));
        }),
    )),
);
