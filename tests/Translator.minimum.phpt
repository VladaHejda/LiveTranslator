<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/dummy.php';

$trans = new \LiveTranslator\Translator('en', new DummyStorage, $container->getService('session'), $container->getService('application'), $container->getService('cache.storage'));

Assert::equal('hello', $trans->translate('hello'));
