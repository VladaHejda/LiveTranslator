<?php

class DummyStorage implements \LiveTranslator\ITranslatorStorage
{
	function getTranslation(string $o, string $l, int $v = 0, ?string $n = null): ?string
	{
		return null;
	}

	function getAllTranslations(string $l, ?string $n = null): array
	{
		return [];
	}

	function setTranslation(string $o, string $t, string $l, int $v = 0, ?string $n = null)
	{}

	function removeTranslation(string $o, string $l, ?string $n = null)
	{}
}
