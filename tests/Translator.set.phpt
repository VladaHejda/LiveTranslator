<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/simple.php';

$trans = new \LiveTranslator\Translator('en', new SimpleStorage, $container->getService('session'), $container->getService('application'), $container->getService('cache.storage'));

$trans->setCurrentLang('cz')
	->setAvailableLanguages(array(
		'en',
		'cz' => 'nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));'
	))
;

$trans->setTranslation('Goodbye home.', 'Sbohem domove.');
Assert::equal('Sbohem domove.', $trans->translate('Goodbye home.'));

$trans->translate('Damned!');
$trans->setTranslation('Damned!', 'Zatraceně!');
Assert::equal('Zatraceně!', $trans->translate('Damned!'));

$trans->setTranslation('%d egg', array('%d vejce', '%d vejce', '%d vajec'));
Assert::equal('1 vejce', $trans->translate(array('%d egg'), 1));
Assert::equal('5 vajec', $trans->translate(array('%d egg'), 5));
