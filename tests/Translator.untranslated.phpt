<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/simple.php';
require __DIR__.'/storage/untranslated.php';

use \LiveTranslator\Translator as Tr;

$untranslated = new MemoryUntranslatedStorage;
// recorded in a previous request
$untranslated->data['cs|'] = array('Old string' => false, '%d old day' => true);

$storage = new SimpleStorage;
$trans = new Tr('en', $storage, $container->getService('application'), $untranslated);


/***************DEFAULT LANGUAGE***************/

// nothing is recorded in the default language
Assert::same('Save', $trans->translate('Save'));
Assert::same('2 days', $trans->translate(array('%d day', '%d days'), 2));
Assert::same(array(), $untranslated->calls);


/***************RECORDING***************/

$trans->setCurrentLang('cs');

Assert::same('Save', $trans->translate('Save'));
Assert::same('Save', $trans->translate('Save'));
Assert::same('5 days', $trans->translate(array('%d day', '%d days'), 5));
Assert::same('1 day', $trans->translate(array('%d day', '%d days'), 1));
// already recorded - no write
Assert::same('Old string', $trans->translate('Old string'));
Assert::same('1 old day', $trans->translate(array('%d old day', '%d old days'), 1));
// a recorded string newly used as plural is marked once, the mark is never removed
Assert::same('Save', $trans->translate(array('Save', 'Saves'), 1));
Assert::same('Save', $trans->translate('Save'));
// translated strings are not recorded
Assert::same('Ahoj světe.', $trans->translate('Hello world.'));

// the storage is read once per language and namespace, written only for new strings
Assert::same(array(
	'get cs|',
	'add cs| Save',
	'add cs| %d day plural',
	'add cs| Save plural',
), $untranslated->calls);
Assert::same(
	array('Old string' => false, '%d old day' => true, 'Save' => true, '%d day' => true),
	$untranslated->data['cs|']
);


/***************ALL STRINGS, PLURAL STRINGS***************/

$all = $trans->getAllStrings();
Assert::same(10, count($all)); // 6 translations of SimpleStorage + 4 untranslated
Assert::same(array('Ahoj světe.'), $all['Hello world.']);
Assert::false($all['Old string']);
Assert::false($all['Save']);
Assert::false($all['%d day']);

$plurals = $trans->getPluralStrings();
ksort($plurals);
Assert::same(array(
	'%2$d man screams %1$s.' => true, // translated in more forms
	'%d day' => true, // used as plural in this request
	'%d old day' => true, // recorded as plural
	'%d pear' => true,
	'Save' => true,
	'here is %d apple' => true,
	'name %s' => true,
), $plurals);
Assert::false(isset($plurals['Hello world.']), 'translated in one form');
Assert::false(isset($plurals['Old string']), 'recorded, not plural');

Assert::same(1, count(array_filter($untranslated->calls, fn($call) => str_starts_with($call, 'get '))));


/***************TRANSLATING***************/

$untranslated->calls = array();

// a common string is removed from the list with its translation
$trans->setTranslation('Old string', 'Starý text');
Assert::same('Starý text', $trans->translate('Old string'));
Assert::false(isset($untranslated->data['cs|']['Old string']));

// a plural string stays in the list until all forms of the language (3 in Czech) are filled
$trans->setTranslation('%d day', array('%d den', null, null));
Assert::true($untranslated->data['cs|']['%d day']);
Assert::same('5 den', $trans->translate(array('%d day', '%d days'), 5));
$trans->setTranslation('%d day', array('%d den', '%d dny', '%d dní'));
Assert::false(isset($untranslated->data['cs|']['%d day']));
Assert::same('5 dní', $trans->translate(array('%d day', '%d days'), 5));
Assert::same('3 dny', $trans->translate(array('%d day', '%d days'), 3));

// an empty form between filled ones keeps the variant index
$trans->setTranslation('%d old day', array(null, '%d staré dny', ''));
Assert::same(array(1 => '%d staré dny'), $storage->getAllTranslations('cs')['%d old day']);
Assert::true($untranslated->data['cs|']['%d old day']);

// erased in the panel
$trans->setTranslation('Save', false);
Assert::false(isset($untranslated->data['cs|']['Save']));

// a string not recorded as untranslated - no write to the untranslated storage
$trans->setTranslation('Hello world.', 'Ahoj světe!');

Assert::same(array(
	'remove cs| Old string',
	'remove cs| %d day',
	'remove cs| Save',
), $untranslated->calls);

// the default language is not translated
$trans->setCurrentLang('en');
$untranslated->calls = array();
$trans->setTranslation('Brand new', 'Zbrusu nový');
Assert::same(array(), $untranslated->calls);
Assert::false(isset($storage->getAllTranslations('en')['Brand new']));


/***************NAMESPACES***************/

$trans->setCurrentLang('cs')->setNamespace('admin');
Assert::same('Save', $trans->translate('Save'));
Assert::same(array('get cs|admin', 'add cs|admin Save'), $untranslated->calls);
Assert::same(array('Save' => false), $untranslated->data['cs|admin']);

// plural marks (used in this request, recorded as untranslated) are per namespace
$plurals = $trans->getPluralStrings();
Assert::false(isset($plurals['%d old day']));
Assert::false(isset($plurals['Save']));


/***************NEXT REQUEST***************/

$trans = new Tr('en', new SimpleStorage, $container->getService('application'), $untranslated);
$trans->setCurrentLang('cs');
$all = $trans->getAllStrings();
Assert::false($all['%d old day']);
Assert::false(isset($all['Old string']));
Assert::true($trans->getPluralStrings()['%d old day']);


/***************WITHOUT UNTRANSLATED STORAGE***************/

// strings without translation are collected for the current request only
$trans = new Tr('en', new SimpleStorage, $container->getService('application'));
$trans->setCurrentLang('cs');
$trans->translate(array('%d week', '%d weeks'), 2);
Assert::false($trans->getAllStrings()['%d week']);
Assert::true($trans->getPluralStrings()['%d week']);
$trans->setTranslation('%d week', '%d týden');
Assert::same(array('%d týden'), $trans->getAllStrings()['%d week']);

$trans = new Tr('en', new SimpleStorage, $container->getService('application'));
$trans->setCurrentLang('cs');
Assert::false(isset($trans->getAllStrings()['%d week']));
