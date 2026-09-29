<?php

use Tester\Assert;
$container = require __DIR__ . '/bootstrap.application.php';

require __DIR__.'/storage/untranslated.php';

use \LiveTranslator\Translator as Tr;

$dir = __DIR__ . '/temp/Translator.untranslatedFile';
if (!file_exists($dir)) {
	mkdir($dir);
}
array_map('unlink', glob("$dir/*"));

$application = $container->getService('application');


// Storage\File keeps strings without translation next to the translations by default (UntranslatedFile)
$trans = new Tr('en', new \LiveTranslator\Storage\File($dir), $application);
$trans->setCurrentLang('cs')->setNamespace('front');
$trans->translate('Save');
$trans->translate(array('%d day', '%d days'), 2);

Assert::same(
	array('Save' => false, '%d day' => true),
	json_decode(file_get_contents("$dir/cs.front.untranslated"), true)
);

// next request
$trans = new Tr('en', new \LiveTranslator\Storage\File($dir), $application);
$trans->setCurrentLang('cs')->setNamespace('front');
Assert::same(array('Save' => false, '%d day' => false), $trans->getAllStrings());
Assert::same(array('%d day' => true), $trans->getPluralStrings());

$trans->setTranslation('Save', 'Uložit');
$trans->setTranslation('%d day', array('%d den', '%d dny', '%d dní'));
Assert::false(file_exists("$dir/cs.front.untranslated"));
unset($trans); // Storage\File writes the translations in its destructor

$trans = new Tr('en', new \LiveTranslator\Storage\File($dir), $application);
$trans->setCurrentLang('cs')->setNamespace('front');
Assert::same(array('Save' => array('Uložit'), '%d day' => array('%d den', '%d dny', '%d dní')), $trans->getAllStrings());
Assert::same('5 dní', $trans->translate(array('%d day', '%d days'), 5));
// translated in more forms - still plural for the panel
Assert::same(array('%d day' => true), $trans->getPluralStrings());
Assert::false(file_exists("$dir/cs.front.untranslated"));


// an explicit untranslated storage replaces the default one
$untranslated = new MemoryUntranslatedStorage;
$trans = new Tr('en', new \LiveTranslator\Storage\File($dir), $application, $untranslated);
$trans->setCurrentLang('cs');
$trans->translate('Home');
Assert::same(array('Home' => false), $untranslated->data['cs|']);
Assert::false(file_exists("$dir/cs.untranslated"));
