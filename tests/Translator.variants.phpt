<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/dummy.php';

$trans = new \LiveTranslator\Translator('cz', new DummyStorage, $container->getService('session'), $container->getService('application'), $container->getService('cache.storage'));
$trans::$defaultPluralForms = 'nplurals=2; plural=(n==1) ? 0 : 1;';

Assert::equal(2, $trans->getVariantsCount());
Assert::equal(0, $trans->getVariant(1));
Assert::equal(1, $trans->getVariant(2));

$trans->setAvailableLanguages(array(
	'en',
	'cz' => 'nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));',
	'zh' => 'nplurals=1; plural=0;'
));

Assert::equal(3, $trans->getVariantsCount());
Assert::equal(3, $trans->getVariantsCount('cz'));
Assert::equal(2, $trans->getVariantsCount('en'));
Assert::equal(1, $trans->getVariantsCount('zh'));

Assert::equal(0, $trans->getVariant(1));
Assert::equal(1, $trans->getVariant(2));
Assert::equal(2, $trans->getVariant(0));

Assert::equal(0, $trans->getVariant(1, 'en'));
Assert::equal(1, $trans->getVariant(2, 'en'));
Assert::equal(1, $trans->getVariant(0, 'en'));

Assert::equal(0, $trans->getVariant(1, 'zh'));
Assert::equal(0, $trans->getVariant(2, 'zh'));
Assert::equal(0, $trans->getVariant(0, 'zh'));

// wrong nplurals
$trans->setAvailableLanguages(array(
	'cz' => 'nplurals=1; plural=(n==1) ? 0 : 1;',
));

Assert::exception(function() use($trans){
	$trans->getVariant(2);
}, 'LiveTranslator\TranslatorException');
