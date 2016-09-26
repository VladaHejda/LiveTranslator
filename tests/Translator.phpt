<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/simple.php';

$trans = new \LiveTranslator\Translator('en', new SimpleStorage, $container->getService('session'), $container->getService('application'), $container->getService('cache.storage'));

$trans->setCurrentLang('cz')
	->setAvailableLanguages(array(
		'en' => 'nplurals=2; plural=(n==1) ? 0 : 1;',
		'cz' => 'nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));'
	))
;

Assert::equal('Ahoj světe.', $trans->translate('Hello world.'));
Assert::equal('untranslated', $trans->translate('untranslated'));
Assert::equal('Jmenuji se Stephan.', $trans->translate('My name is %s.', 'Stephan'));
Assert::equal('zde je 1 jablko', $trans->translate(array('here is %d apple'), 1));
Assert::equal('zde jsou 2 jablka', $trans->translate(array('here is %d apple'), 2));
Assert::equal('zde jsou 3 jablka', $trans->translate(array('here is %d apple'), 3));
Assert::equal('zde je 5 jablek',   $trans->translate(array('here is %d apple'), 5));
Assert::equal('zde je 0 jablek',   $trans->translate(array('here is %d apple'), 0));
Assert::equal('zde je -7 jablek',  $trans->translate(array('here is %d apple'), -7));
Assert::equal('1 hruška',  $trans->translate(array('%d pear'), 1));
Assert::equal('2 hrušky',  $trans->translate(array('%d pear'), 2));
Assert::equal('5 hrušky',  $trans->translate(array('%d pear'), 5));
Assert::equal('jméno Johnny',  $trans->translate(array('name %s'), 'Johnny', 1));
Assert::equal('jména Johnny, George',  $trans->translate(array('name %s'), 'Johnny, George', 2));
Assert::equal('Woohoo křičí 1 muž.',  $trans->translate(array('%2$d man screams %1$s.'), 'Woohoo', 1));
Assert::equal('2 muži křičí "Woohoo!".', $trans->translate(array('%2$d man screams %1$s.'), 'Woohoo', 2));
Assert::equal('Woohoo křičí 5 mužů.', $trans->translate(array('%2$d man screams %1$s.'), 'Woohoo', 5));
Assert::equal('Tax is 21%', $trans->translate('Tax is 21%'));
Assert::equal('Tax is 21% or 15%', $trans->translate('Tax is 21% or 15%'));
Assert::equal('50%done', $trans->translate('50%done'));

$trans->setCurrentLang('en');

Assert::equal('Hello world.', $trans->translate('Hello world.'));
Assert::equal('untranslated', $trans->translate('untranslated'));
Assert::equal('My name is Stephan.', $trans->translate('My name is %s.', 'Stephan'));
Assert::equal('here is 1 apple', $trans->translate(array('here is %d apple', 'here is %d apples'), 1));
Assert::equal('here is 2 apples', $trans->translate(array('here is %d apple', 'here is %d apples'), 2));
Assert::equal('2 man screams Woohoo.', $trans->translate(array('%2$d man screams %1$s.'), 'Woohoo', 2));
