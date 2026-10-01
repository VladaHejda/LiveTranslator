<?php

class LanguageStorage implements \LiveTranslator\ITranslatorStorage
{
	private $translations = array(
		'cz' => array(
			'Hello world.' => 'Ahoj světe.',
		),
		'de' => array(
			'Hello world.' => 'Hallo Welt.',
		),
	);

	function getTranslation(string $original, string $lang, int $v = 0, ?string $n = null): ?string
	{
		if (!isset($this->translations[$lang][$original])) return NULL;
		return $this->translations[$lang][$original];
	}

	function getAllTranslations(string $lang, ?string $n = null): array
	{
		return $this->translations[$lang];
	}

	function setTranslation(string $o, string $t, string $l, int $v = 0, ?string $n = null)
	{}

	function removeTranslation(string $o, string $l, ?string $n = null)
	{}
}
