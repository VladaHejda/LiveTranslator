<?php

class SimpleStorage implements \LiveTranslator\ITranslatorStorage
{
	private $translations = array(
		'Hello world.' => array('Ahoj světe.'),
		'My name is %s.' => array('Jmenuji se %s.'),
		'here is %d apple' => array('zde je %d jablko', 'zde jsou %d jablka', 'zde je %d jablek'),
		'%d pear' => array('%d hruška', '%d hrušky'),
		'name %s' => array('jméno %s', 'jména %s'),
		'%2$d man screams %1$s.' => array('%1$s křičí %2$d muž.', '%2$d muži křičí "%1$s!".', '%1$s křičí %2$d mužů.'),
	);

	function getTranslation(string $original, string $l, int $variant = 0, ?string $n = null): ?string
	{
		if (!isset($this->translations[$original])) return NULL;
		return isset($this->translations[$original][$variant])
			? $this->translations[$original][$variant]
			: end($this->translations[$original])
		;
	}

	function getAllTranslations(string $l, ?string $n = null): array
	{
		return $this->translations;
	}

	function setTranslation(string $original, string $translated, string $l, int $variant = 0, ?string $n = null)
	{
		if (!isset($this->translations[$original])) $this->translations[$original] = array();
		$this->translations[$original][$variant] = $translated;
	}

	function removeTranslation(string $original, string $l, ?string $n = null)
	{
		unset($this->translations[$original]);
	}
}
