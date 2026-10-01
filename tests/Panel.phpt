<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/simple.php';

$trans = new \LiveTranslator\Translator('en', new SimpleStorage, $container->getService('application'));
$panel = new \LiveTranslator\Panel\Panel($trans, $container->getService('httpRequest'));

Assert::type('LiveTranslator\Translator', $panel->getTranslator());
$panel->setLayout('horizontal')
	->setHeight(500);

Assert::equal('horizontal', $panel->getLayout());
Assert::equal(500, $panel->getHeight());
Assert::exception(function() use($panel){
	$panel->setLayout('nothing');
}, 'Nette\InvalidArgumentException');

Assert::true(is_string($panel->getTab()));
Assert::true(is_string($panel->getPanel()));

$trans->setCurrentLang('de');
Assert::true(is_string($panel->getPanel()));

// only plural texts get all plural forms of the language (2 in German, built-in rule), the others one field
$trans->translate(array('%d day', '%d days'), 2);
$trans->translate('Save');
$html = $panel->getPanel();
Assert::contains('variants: 2,', $html);
Assert::contains('plurals: {"%d day":true,', $html);
Assert::contains('"here is %d apple":true', $html); // translated in more forms
Assert::notContains('"Save":true', $html);
Assert::notContains('"Hello world.":true', $html);

$trans->setCurrentLang('cs');
Assert::contains('variants: 3,', $panel->getPanel());
