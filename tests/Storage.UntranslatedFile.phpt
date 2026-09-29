<?php

use LiveTranslator\Storage\UntranslatedFile;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

$dir = __DIR__ . '/temp/UntranslatedFile.storage';
if (!file_exists($dir)) {
	mkdir($dir);
}
array_map('unlink', glob("$dir/*"));

Assert::exception(function () use ($dir) {
	new UntranslatedFile("$dir/missing");
}, 'Nette\DirectoryNotFoundException');

$storage = new UntranslatedFile($dir);


/***************BASES***************/

Assert::same(array(), $storage->getUntranslated('cs'));
// removing from a missing list does not create a file
$storage->removeUntranslated('Save', 'cs');
Assert::false(file_exists("$dir/cs.untranslated"));

$storage->addUntranslated('Save', 'cs');
$storage->addUntranslated('%d day', 'cs', null, true);
$storage->addUntranslated('Žluťoučký kůň', 'cs');
$storage->addUntranslated('Save', 'cs');
// in order of discovery, without duplicates
Assert::same(array('Save' => false, '%d day' => true, 'Žluťoučký kůň' => false), $storage->getUntranslated('cs'));

// a JSON object next to the translations, readable by hand
Assert::true(is_file("$dir/cs.untranslated"));
Assert::same(
	array('Save' => false, '%d day' => true, 'Žluťoučký kůň' => false),
	json_decode(file_get_contents("$dir/cs.untranslated"), true)
);
Assert::contains('Žluťoučký kůň', file_get_contents("$dir/cs.untranslated"));

// a new instance (next request) reads the same list
$storage = new UntranslatedFile($dir);
Assert::same(array('Save' => false, '%d day' => true, 'Žluťoučký kůň' => false), $storage->getUntranslated('cs'));


/***************PLURAL MARK***************/

// a string is marked as plural later, the mark is never removed
$storage->addUntranslated('Save', 'cs', null, true);
Assert::true($storage->getUntranslated('cs')['Save']);
$storage->addUntranslated('Save', 'cs', null, false);
Assert::true($storage->getUntranslated('cs')['Save']);


/***************WRITE ONLY ON CHANGE***************/

$content = file_get_contents("$dir/cs.untranslated");
file_put_contents("$dir/cs.untranslated", $content . ' '); // any rewrite would drop the trailing space
$storage->addUntranslated('Save', 'cs');
$storage->addUntranslated('%d day', 'cs', null, true);
$storage->removeUntranslated('Unknown', 'cs');
Assert::same($content . ' ', file_get_contents("$dir/cs.untranslated"));


/***************REMOVE***************/

$storage->removeUntranslated('Save', 'cs');
Assert::same(array('%d day' => true, 'Žluťoučký kůň' => false), $storage->getUntranslated('cs'));
$storage->removeUntranslated('%d day', 'cs');
$storage->removeUntranslated('Žluťoučký kůň', 'cs');
Assert::same(array(), $storage->getUntranslated('cs'));
// an empty list removes the file
Assert::false(file_exists("$dir/cs.untranslated"));


/***************LANGUAGES, NAMESPACES***************/

$storage->addUntranslated('Save', 'cs', 'admin');
$storage->addUntranslated('Delete', 'de', 'admin');
$storage->addUntranslated('Home', 'cs');

Assert::true(is_file("$dir/cs.admin.untranslated"));
Assert::true(is_file("$dir/de.admin.untranslated"));
Assert::true(is_file("$dir/cs.untranslated"));
Assert::same(array('Save' => false), $storage->getUntranslated('cs', 'admin'));
Assert::same(array('Delete' => false), $storage->getUntranslated('de', 'admin'));
Assert::same(array('Home' => false), $storage->getUntranslated('cs'));
Assert::same(array(), $storage->getUntranslated('de'));

$storage->removeUntranslated('Save', 'cs');
Assert::same(array('Save' => false), $storage->getUntranslated('cs', 'admin'));


/***************NUMERIC ORIGINALS***************/

// originals "0", "1" are written as a JSON object, not a list
$storage->addUntranslated('0', 'en');
$storage->addUntranslated('1', 'en', null, true);
Assert::same('{', file_get_contents("$dir/en.untranslated")[0]);
Assert::equal(array('0' => false, '1' => true), $storage->getUntranslated('en'));
$storage->removeUntranslated('0', 'en');
Assert::equal(array('1' => true), $storage->getUntranslated('en'));


/***************FORMATS***************/

// a JSON list of originals (3.0 pre-release) is still read
file_put_contents("$dir/sk.untranslated", json_encode(array('Save', '%d day')));
Assert::same(array('Save' => false, '%d day' => false), $storage->getUntranslated('sk'));
// and rewritten as an object on change
$storage->addUntranslated('%d day', 'sk', null, true);
Assert::same(array('Save' => false, '%d day' => true), json_decode(file_get_contents("$dir/sk.untranslated"), true));

// a broken file is an empty list
file_put_contents("$dir/pl.untranslated", '{broken');
Assert::same(array(), $storage->getUntranslated('pl'));
file_put_contents("$dir/pl.untranslated", '');
Assert::same(array(), $storage->getUntranslated('pl'));
file_put_contents("$dir/pl.untranslated", '"string"');
Assert::same(array(), $storage->getUntranslated('pl'));
