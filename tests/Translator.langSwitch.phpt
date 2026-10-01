<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/language.php';

use \LiveTranslator\Translator as Tr;
$trans = new Tr('en', new LanguageStorage, $container->getService('application'));

$trans->setCurrentLang('cz');

Assert::equal('Ahoj světe.', $trans->translate('Hello world.'));

$all = $trans->getAllStrings();
Assert::equal(1, count($all));
Assert::equal('Ahoj světe.', current($all));

$trans->translate('Goodbye home.');
$trans->setCurrentLang('de');

Assert::equal('Hallo Welt.', $trans->translate('Hello world.'));

// strings without translation are kept per language - "Goodbye home." was requested in cz only
$all = $trans->getAllStrings();
Assert::equal(1, count($all));
Assert::false(array_key_exists('Goodbye home.', $all));
Assert::equal('Hallo Welt.', $all['Hello world.']);

$trans->translate('Goodbye home.');
$all = $trans->getAllStrings();
Assert::equal(2, count($all));
Assert::false($all['Goodbye home.']);

// the cz list survives switching the language
$trans->setCurrentLang('cz');
$all = $trans->getAllStrings();
Assert::equal(2, count($all));
Assert::false($all['Goodbye home.']);
