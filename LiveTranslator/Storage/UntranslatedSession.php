<?php
declare(strict_types=1);

namespace LiveTranslator\Storage;

use LiveTranslator\IUntranslatedStorage;
use Nette;

/**
 * Strings without translation in the session - each user sees only strings found in his own requests (the behavior
 * before 3.0). Opt-in instead of the default UntranslatedFile:
 *
 *     services:
 *         - LiveTranslator\Storage\UntranslatedSession
 *
 * The session is not started here, Nette starts it with the first recorded string. When that happens during template
 * rendering after the output has been sent, the session can no longer be started - render the template into a string
 * first or start the session earlier.
 */
class UntranslatedSession implements IUntranslatedStorage
{
	private const Section = 'LiveTranslator.untranslated';

	private Nette\Http\Session $session;


	public function __construct(Nette\Http\Session $session)
	{
		$this->session = $session;
	}


	/**
	 * @return string[]
	 */
	public function getUntranslated(string $lang, ?string $namespace = null): array
	{
		// reading does not start the session (SessionSection::get() without an existing session returns null)
		$originals = $this->getSection()->get($this->getKey($lang, $namespace));

		return is_array($originals) ? array_values(array_filter($originals, 'is_string')) : array();
	}


	public function addUntranslated(string $original, string $lang, ?string $namespace = null): void
	{
		$originals = $this->getUntranslated($lang, $namespace);
		if (in_array($original, $originals, true)) {
			return;
		}

		$originals[] = $original;
		$this->getSection()->set($this->getKey($lang, $namespace), $originals);
	}


	public function removeUntranslated(string $original, string $lang, ?string $namespace = null): void
	{
		$originals = $this->getUntranslated($lang, $namespace);
		if (!in_array($original, $originals, true)) {
			return;
		}

		$originals = array_values(array_filter($originals, function (string $item) use ($original): bool {
			return $item !== $original;
		}));

		$originals
			? $this->getSection()->set($this->getKey($lang, $namespace), $originals)
			: $this->getSection()->remove($this->getKey($lang, $namespace));
	}


	private function getSection(): Nette\Http\SessionSection
	{
		return $this->session->getSection(self::Section);
	}


	private function getKey(string $lang, ?string $namespace): string
	{
		return $lang . ($namespace === null ? '' : ".$namespace");
	}
}
