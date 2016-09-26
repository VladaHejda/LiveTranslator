<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/simple.php';

use \LiveTranslator\Translator as Tr;
$trans = new Tr('en', new SimpleStorage, $container->getService('session'), $container->getService('application'), $container->getService('cache.storage'));

$trans->setCurrentLang('cz');

$all = $trans->getAllStrings();

Assert::equal(6, count($all));

$trans->translate('new string');
$all = $trans->getAllStrings();

Assert::equal(7, count($all));
Assert::true(array_key_exists('new string', $all));
