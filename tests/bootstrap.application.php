<?php

// todo mazat automaticky cache?

require __DIR__ . '/../vendor/autoload.php';

if (!class_exists('Tester\Assert')){
	echo "Install Nette Tester using `composer update --dev`\n";
	exit(1);
}

Tester\Environment::setup();

// For caching lock tests and clean up cache dir
@mkdir($dir = __DIR__ . '/temp/lock');
Tester\Environment::lock('cache', $dir);

if (is_dir($dir = __DIR__ . '/temp/cache/_VladaHejda.LiveTranslator')) {
	$h = opendir($dir);
	while ($file = readdir($h)) {
		if (is_dir($file)) {
			continue;
		}
		unlink("$dir/$file");
	}
}


$configurator = new Nette\Configurator;
$configurator->setTempDirectory(__DIR__ . '/temp');
return $configurator->createContainer();
