<?php

/**
 * Needs MySQL/MariaDB, skipped otherwise:
 * LIVETRANSLATOR_TEST_DSN="mysql:host=127.0.0.1;dbname=test" LIVETRANSLATOR_TEST_USER=... LIVETRANSLATOR_TEST_PASSWORD=... composer test
 */

use LiveTranslator\Storage\UntranslatedNetteDatabase;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

$dsn = getenv('LIVETRANSLATOR_TEST_DSN');
if (!$dsn) {
	Tester\Environment::skip('Set LIVETRANSLATOR_TEST_DSN (and _USER, _PASSWORD) to test with MySQL/MariaDB.');
}

$connection = new Nette\Database\Connection($dsn, getenv('LIVETRANSLATOR_TEST_USER') ?: null, getenv('LIVETRANSLATOR_TEST_PASSWORD') ?: null);
$db = new Nette\Database\Explorer(
	$connection,
	new Nette\Database\Structure($connection, new Nette\Caching\Storages\MemoryStorage),
);

// own table for each run - tests run in parallel
$table = 'lt_untranslated_' . getmypid();
$sql = file_get_contents(__DIR__ . '/../LiveTranslator/Storage/NetteDatabase.createTable.untranslated.sql');
$db->query("DROP TABLE IF EXISTS `$table`");
$db->query(str_replace('`localization_untranslated`', "`$table`", $sql));

try {
	$storage = new UntranslatedNetteDatabase("`$table`", $db); // backticks are optional

	Assert::same(array(), $storage->getUntranslated('cs'));

	$storage->addUntranslated('Save', 'cs');
	$storage->addUntranslated('%d day', 'cs', null, true);
	$storage->addUntranslated('Žluťoučký kůň', 'cs');
	$storage->addUntranslated('Save', 'cs');
	// in order of discovery, without duplicates
	Assert::same(array('Save' => false, '%d day' => true, 'Žluťoučký kůň' => false), $storage->getUntranslated('cs'));
	Assert::same(3, $db->fetchField("SELECT COUNT(*) FROM `$table`"));

	// the plural mark is added, never removed
	$storage->addUntranslated('Save', 'cs', null, true);
	$storage->addUntranslated('Save', 'cs', null, false);
	Assert::true($storage->getUntranslated('cs')['Save']);

	// texts longer than any index - unique by hash
	$long = str_repeat('Lorem ipsum dolor sit amet. ', 100);
	$storage->addUntranslated($long, 'cs');
	$storage->addUntranslated($long, 'cs');
	Assert::false($storage->getUntranslated('cs')[$long]);
	Assert::same(4, $db->fetchField("SELECT COUNT(*) FROM `$table`"));

	// case-sensitive originals
	$storage->addUntranslated('save', 'cs');
	Assert::same(array('Save', '%d day', 'Žluťoučký kůň', $long, 'save'), array_keys($storage->getUntranslated('cs')));

	// languages and namespaces are separate, no namespace is an empty string
	$storage->addUntranslated('Save', 'cs', 'admin');
	$storage->addUntranslated('Save', 'de');
	Assert::same(array('Save' => false), $storage->getUntranslated('cs', 'admin'));
	Assert::same(array('Save' => false), $storage->getUntranslated('de'));
	Assert::same(array(), $storage->getUntranslated('de', 'admin'));
	Assert::same('', $db->fetchField("SELECT `ns` FROM `$table` WHERE `lang` = 'de'"));

	$storage->removeUntranslated('Save', 'cs');
	$storage->removeUntranslated($long, 'cs');
	$storage->removeUntranslated('Unknown', 'cs');
	Assert::same(array('%d day' => true, 'Žluťoučký kůň' => false, 'save' => false), $storage->getUntranslated('cs'));
	Assert::same(array('Save' => false), $storage->getUntranslated('cs', 'admin'));
	Assert::same(array('Save' => false), $storage->getUntranslated('de'));

	// numeric originals
	$storage->addUntranslated('0', 'en');
	Assert::equal(array('0' => false), $storage->getUntranslated('en'));

} finally {
	$db->query("DROP TABLE IF EXISTS `$table`");
}
