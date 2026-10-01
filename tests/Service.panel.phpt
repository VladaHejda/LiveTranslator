<?php

require __DIR__.'/bootstrap.php';

use Tester\Assert;
require __DIR__.'/storage/dummy.php';

$configurator = new Nette\Configurator;
$configurator->setTempDirectory(__DIR__ . '/temp');
$configurator->addConfig(__DIR__.'/config/panel.neon');
$container = $configurator->createContainer();


Assert::type('LiveTranslator\Panel\Panel', $container->getService('translatorPanel'));
Assert::equal('horizontal', $container->getService('translatorPanel')->getLayout());
Assert::equal(500, $container->getService('translatorPanel')->getHeight());

\Tracy\Debugger::getBar()->addPanel($container->getService('translatorPanel'));
