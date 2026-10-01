<?php
declare(strict_types=1);

namespace LiveTranslator;

/**
 * Keeps the list of strings that were requested but have no translation yet, so the translation panel can offer them.
 * Implementations: Storage\UntranslatedFile (default with Storage\File), Storage\UntranslatedSession,
 * Storage\UntranslatedNetteDatabase.
 */
interface IUntranslatedStorage
{

	/**
	 * Original strings without translation recorded for given language, in order of discovery.
	 *
	 * @return array<string, bool> original => used as plural (the panel offers all plural forms of the language)
	 */
	function getUntranslated(string $lang, ?string $namespace = null): array;


	/**
	 * Records a string without translation. An already recorded string is only marked as plural when $plural is true
	 * (a plural mark is never removed).
	 */
	function addUntranslated(string $original, string $lang, ?string $namespace = null, bool $plural = false): void;


	/**
	 * Forgets a recorded string (it was translated or erased in the panel).
	 */
	function removeUntranslated(string $original, string $lang, ?string $namespace = null): void;
}
