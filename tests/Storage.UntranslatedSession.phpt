<?php

use LiveTranslator\Storage\UntranslatedSession;
use Tester\Assert;

$container = require __DIR__ . '/bootstrap.application.php';

/** @var Nette\Http\Session $session */
$session = $container->getByType(Nette\Http\Session::class);
$storage = new UntranslatedSession($session);


// reading does not start the session (bots get no session just because of the translator)
Assert::same(array(), $storage->getUntranslated('cs'));
Assert::same(array(), $storage->getUntranslated('cs', 'admin'));
Assert::false($session->isStarted());

// removing from an empty list does not start it either
$storage->removeUntranslated('Save', 'cs');
Assert::false($session->isStarted());

// the first recorded string starts it
$storage->addUntranslated('Save', 'cs');
Assert::true($session->isStarted());

$storage->addUntranslated('%d day', 'cs', null, true);
$storage->addUntranslated('Save', 'cs');
Assert::same(array('Save' => false, '%d day' => true), $storage->getUntranslated('cs'));

// the plural mark is added, never removed
$storage->addUntranslated('Save', 'cs', null, true);
$storage->addUntranslated('Save', 'cs', null, false);
Assert::same(array('Save' => true, '%d day' => true), $storage->getUntranslated('cs'));

// languages and namespaces are separate
$storage->addUntranslated('Delete', 'cs', 'admin');
$storage->addUntranslated('Löschen', 'de');
Assert::same(array('Delete' => false), $storage->getUntranslated('cs', 'admin'));
Assert::same(array('Löschen' => false), $storage->getUntranslated('de'));
Assert::same(array('Save' => true, '%d day' => true), $storage->getUntranslated('cs'));

// numeric originals
$storage->addUntranslated('0', 'en');
Assert::equal(array('0' => false), $storage->getUntranslated('en'));

// the section keeps one key per language and namespace, removed with the last string
$section = $session->getSection('LiveTranslator.untranslated');
Assert::same(array('Delete' => false), $section->get('cs.admin'));
$storage->removeUntranslated('Delete', 'cs', 'admin');
Assert::same(array(), $storage->getUntranslated('cs', 'admin'));
Assert::null($section->get('cs.admin'));

$storage->removeUntranslated('Save', 'cs');
Assert::same(array('%d day' => true), $storage->getUntranslated('cs'));
$storage->removeUntranslated('Unknown', 'cs');
Assert::same(array('%d day' => true), $storage->getUntranslated('cs'));
