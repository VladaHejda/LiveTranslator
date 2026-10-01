<?php

class NamespaceStorage implements \LiveTranslator\ITranslatorStorage
{
	private $translations = array(
		'first' => array(
			'Hello world.' => 'Ahoj světe.',
		),
		'second' => array(
			'My name is %s.' => 'Jmenuji se %s.',
		),
	);

	function getTranslation(string $original, string $l, int $v = 0, ?string $ns = null): ?string
	{
		if (!isset($this->translations[$ns][$original])) return NULL;
		return $this->translations[$ns][$original];
	}

	function getAllTranslations(string $l, ?string $ns = null): array
	{
		return $this->translations[$ns];
	}

	function setTranslation(string $o, string $t, string $l, int $variant = 0, ?string $n = null)
	{}

	function removeTranslation(string $o, string $l, ?string $n = null)
	{}
}
