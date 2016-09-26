<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/language.php';

use \LiveTranslator\Translator as Tr;
$trans = new Tr('en', new LanguageStorage, $container->getService('session'), $container->getService('application'), $container->getService('cache.storage'));

$trans->setCurrentLang('cz');

Assert::equal('Ahoj světe.', $trans->translate('Hello world.'));

$all = $trans->getAllStrings();
Assert::equal(1, count($all));
Assert::equal('Ahoj světe.', current($all));

$trans->translate('Goodbye home.');
$trans->setCurrentLang('de');

Assert::equal('Hallo Welt.', $trans->translate('Hello world.'));

// checks if new string "Goodbye home." persists in session after switching language
$all = $trans->getAllStrings();
Assert::equal(2, count($all));
Assert::true(array_key_exists('Goodbye home.', $all));
Assert::equal('Hallo Welt.', $all['Hello world.']);
