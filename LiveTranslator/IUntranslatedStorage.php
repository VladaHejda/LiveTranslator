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
	 * @return string[]
	 */
	function getUntranslated(string $lang, ?string $namespace = null): array;


	/**
	 * Records a string without translation (already recorded string is ignored).
	 */
	function addUntranslated(string $original, string $lang, ?string $namespace = null): void;


	/**
	 * Forgets a recorded string (it was translated or erased in the panel).
	 */
	function removeUntranslated(string $original, string $lang, ?string $namespace = null): void;
}
