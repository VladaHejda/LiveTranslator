<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/dummy.php';
require __DIR__.'/storage/simple.php';

use \LiveTranslator\Translator as Tr;


/***************BUILT-IN RULES***************/

// just language codes - rules from PluralRules
$trans = new Tr('en', new DummyStorage, $container->getService('application'));
$trans->setAvailableLanguages(array('en', 'cs', 'pl', 'ja', 'pt_BR'));

Assert::same(array('en', 'cs', 'pl', 'ja', 'pt_BR'), $trans->getAvailableLanguages());
Assert::same(2, $trans->getVariantsCount());
Assert::same(2, $trans->getVariantsCount('en'));
Assert::same(3, $trans->getVariantsCount('cs'));
Assert::same(3, $trans->getVariantsCount('pl'));
Assert::same(1, $trans->getVariantsCount('ja'));
Assert::same(2, $trans->getVariantsCount('pt_BR'));

Assert::same(0, $trans->getVariant(1, 'cs'));
Assert::same(1, $trans->getVariant(4, 'cs'));
Assert::same(2, $trans->getVariant(5, 'cs'));
Assert::same(2, $trans->getVariant(22, 'cs'));
Assert::same(1, $trans->getVariant(22, 'pl'));
Assert::same(0, $trans->getVariant(0, 'pt_BR'));

// a negative count takes the form of its absolute value
Assert::same(0, $trans->getVariant(-1, 'cs'));
Assert::same(1, $trans->getVariant(-3, 'cs'));
Assert::same(2, $trans->getVariant(-7, 'cs'));

// without setAvailableLanguages() the built-in rule of the current language is used too
$trans = new Tr('cs', new DummyStorage, $container->getService('application'));
Assert::same(3, $trans->getVariantsCount());
Assert::same(1, $trans->getVariant(2));


/***************UNKNOWN LANGUAGE***************/

// neither an explicit nor a built-in rule - Translator::$defaultPluralForms (one form)
$trans = new Tr('en', new DummyStorage, $container->getService('application'));
$trans->setAvailableLanguages(array('en', 'xx'));
Assert::same(1, $trans->getVariantsCount('xx'));
Assert::same(0, $trans->getVariant(5, 'xx'));


/***************EXPLICIT RULES***************/

$trans = new Tr('en', new DummyStorage, $container->getService('application'));
$trans->setAvailableLanguages(array(
	'en',
	// an explicit rule overrides the built-in one (two forms instead of three)
	'cs' => 'nplurals=2; plural=(n==1) ? 0 : 1;',
	// a comparison rule evaluates to bool - cast to int
	'xx' => 'nplurals=2; plural=(n != 1);',
	// the trailing semicolon is optional
	'yy' => 'nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2))',
));

Assert::same(2, $trans->getVariantsCount('cs'));
Assert::same(1, $trans->getVariant(5, 'cs'));

Assert::same(2, $trans->getVariantsCount('xx'));
Assert::same(0, $trans->getVariant(1, 'xx'));
Assert::same(1, $trans->getVariant(0, 'xx'));
Assert::same(1, $trans->getVariant(2, 'xx'));

Assert::same(3, $trans->getVariantsCount('yy'));
Assert::same(1, $trans->getVariant(3, 'yy'));
Assert::same(2, $trans->getVariant(5, 'yy'));


/***************INVALID RULES***************/

$trans = new Tr('en', new DummyStorage, $container->getService('application'));

// a label instead of a rule used to end with a ParseError from eval()
$trans->setAvailableLanguages(array('en', 'cs' => 'Čeština'));
Assert::exception(function () use ($trans) {
	$trans->getVariantsCount('cs');
}, 'LiveTranslator\TranslatorException', '%a%Values of setAvailableLanguages() are plural-form rules, not labels.');

// PHP code is not let through to eval()
$trans->setAvailableLanguages(array('cs' => 'nplurals=2; plural=system("id");'));
Assert::exception(function () use ($trans) {
	$trans->getVariant(1, 'cs');
}, 'LiveTranslator\TranslatorException', "Invalid plural-form meta%a%");

$trans->setAvailableLanguages(array('cs' => 'nplurals=2; plural=n; $x=1;'));
Assert::exception(function () use ($trans) {
	$trans->getVariant(1, 'cs');
}, 'LiveTranslator\TranslatorException', "Invalid plural-form meta%a%");

$trans->setAvailableLanguages(array('cs' => ''));
Assert::exception(function () use ($trans) {
	$trans->getVariant(1, 'cs');
}, 'LiveTranslator\TranslatorException', 'Empty plural-form meta for language cs.');


/***************TRANSLATE WITH BUILT-IN RULES***************/

// SimpleStorage has three Czech forms of "here is %d apple", "cs" needs no explicit rule
$trans = new Tr('en', new SimpleStorage, $container->getService('application'));
$trans->setAvailableLanguages(array('en', 'cs'));
$trans->setCurrentLang('cs');

Assert::same('zde je 1 jablko', $trans->translate(array('here is %d apple', 'here are %d apples'), 1));
Assert::same('zde jsou 3 jablka', $trans->translate(array('here is %d apple', 'here are %d apples'), 3));
Assert::same('zde je 5 jablek', $trans->translate(array('here is %d apple', 'here are %d apples'), 5));

$trans->setCurrentLang('en');
Assert::same('here is 1 apple', $trans->translate(array('here is %d apple', 'here are %d apples'), 1));
Assert::same('here are 0 apples', $trans->translate(array('here is %d apple', 'here are %d apples'), 0));
