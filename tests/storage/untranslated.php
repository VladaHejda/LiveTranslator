<?php

/**
 * In-memory untranslated storage recording every call - checks how often the translator writes.
 */
class MemoryUntranslatedStorage implements \LiveTranslator\IUntranslatedStorage
{
	/** @var array<string, array<string, bool>> */
	public $data = array();

	/** @var array<int, string> "method lang|ns original" */
	public $calls = array();


	function getUntranslated(string $lang, ?string $namespace = null): array
	{
		$this->calls[] = "get $lang|$namespace";
		return $this->data["$lang|$namespace"] ?? array();
	}


	function addUntranslated(string $original, string $lang, ?string $namespace = null, bool $plural = false): void
	{
		$this->calls[] = "add $lang|$namespace $original" . ($plural ? ' plural' : '');
		$this->data["$lang|$namespace"][$original] = $plural || ($this->data["$lang|$namespace"][$original] ?? false);
	}


	function removeUntranslated(string $original, string $lang, ?string $namespace = null): void
	{
		$this->calls[] = "remove $lang|$namespace $original";
		unset($this->data["$lang|$namespace"][$original]);
	}
}
