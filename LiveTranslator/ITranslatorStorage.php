<?php
declare(strict_types=1);

namespace LiveTranslator;

interface ITranslatorStorage
{

    /**
     * Return translated string.
     * For nonexistent variant return lower variant.
     * Return null if translation does not exist.
     */
    function getTranslation(string $original, string $lang, int $variant = 0, ?string $namespace = null): ?string;


	/**
	 * Return all translations in all variants for given language.
	 * If there is only one variant nested array could be omitted (except if the only variant is not singular).
	 * Example of returned array: 'bike' => 0 => 'Fahrrad', 1 => 'Fahrräder' 'Hello world.' => 'Hallo Welt.', ...
	 */
	function getAllTranslations(string $lang, ?string $namespace = null): array;


    /**
     * @return void
     */
    function setTranslation(string $original, string $translated, string $lang, int $variant = 0, ?string $namespace = null);


	/**
	 * @return void
	 */
	function removeTranslation(string $original, string $lang, ?string $namespace = null);
}
