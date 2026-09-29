<?php
declare(strict_types=1);

namespace LiveTranslator;

use Nette;

/**
 * Translator implementation.
 *
 * Does not use the session itself. Strings without translation (offered by the translation panel) are kept
 * by IUntranslatedStorage: given in the constructor (Storage\UntranslatedSession, Storage\UntranslatedNetteDatabase), by default Storage\UntranslatedFile
 * next to the translations of Storage\File (`<lang>.<namespace>.untranslated`), otherwise for the current request only.
 *
 * Plural: a text given as an array of forms (`translate(['%d day', '%d days'], $n)`) is plural - the panel offers all
 * plural forms of the language only for such texts, one field for the others. Plural rules of common languages are
 * built in (PluralRules), setAvailableLanguages() needs just language codes; an explicit rule overrides them.
 *
 * @author Vladislav Hejda
 *
 * @property ?string $namespace
 * @property string $currentLang
 * @property string $defaultLang
 * @property ?array $availableLanguages
 * @property ?string $presenterLanguageParam
 * @property-read bool $currentLangDefault
 */
class Translator implements Nette\Localization\Translator
{
	use Nette\SmartObject;

	/** plural-form meta for a language without an explicit rule and unknown to PluralRules */
	public static string $defaultPluralForms = 'nplurals=1; plural=0;';

	/* @var string */
	private $namespace;

	private ?string $defaultLang = null;

	private ?string $lang = null;

	/** @var array<string, ?string> language => explicit plural-form meta, NULL = built-in rule (PluralRules) */
	private array $availableLanguages = array();

	private ?string $presenterLanguageParam = null;

	private ITranslatorStorage $translatorStorage;

	private Nette\Application\Application $application;

	private ?IUntranslatedStorage $untranslatedStorage;

	/** @var array<string, array<string, bool>> strings without translation by "lang|namespace": original => used as plural */
	private array $untranslated = array();

	/** @var array<string, array<string, true>> texts used as plural in this request by namespace (plurality does not depend on the language) */
	private array $plurals = array();


	public function __construct(
		string $defaultLang,
		ITranslatorStorage $translatorStorage,
		Nette\Application\Application $application,
		?IUntranslatedStorage $untranslatedStorage = null,
	) {
		$this->setDefaultLang($defaultLang);
		$this->translatorStorage = $translatorStorage;
		$this->application = $application;
		$this->untranslatedStorage = $untranslatedStorage ?? ($translatorStorage instanceof Storage\File
			? new Storage\UntranslatedFile($translatorStorage->getStorageDir())
			: null);
	}


	public function getNamespace(): ?string
	{
		return $this->namespace;
	}


	public function getCurrentLang(): string
	{
		if ($this->lang) {
			return $this->lang;
		}
		if ($this->presenterLanguageParam) {
			$presenter = $this->application->getPresenter();
			if (isset($presenter->{$this->presenterLanguageParam})) {
				$this->setCurrentLang($presenter->{$this->presenterLanguageParam});
				return $this->lang;
			}
		}
		return $this->lang = $this->defaultLang;
	}


	public function getDefaultLang(): string
	{
		return $this->defaultLang;
	}


	public function isCurrentLangDefault(): bool
	{
		return $this->getCurrentLang() === $this->defaultLang;
	}


	public function getAvailableLanguages(): ?array
	{
		return $this->availableLanguages ? array_keys($this->availableLanguages) : null;
	}


	/**
	 * Number of plural forms of the language (fields the panel offers for a plural text)
	 */
	public function getVariantsCount(?string $lang = null): int
	{
		list($nplurals) = $this->evalPluralForms(1, $lang);
		return $nplurals;
	}


	public function getVariant(int $count, ?string $lang = null): int
	{
		list(, $plural) = $this->evalPluralForms($count, $lang);
		return $plural;
	}


	public function getPresenterLanguageParam(): ?string
	{
		return $this->presenterLanguageParam;
	}


	public function getPresenterLink(string $switchLang): ?string
	{
		if (!$this->presenterLanguageParam) {
			return null;
		}
		return $this->application->getPresenter()?->link('this', array($this->presenterLanguageParam => $switchLang));
	}


	/**
	 * @throws TranslatorException on invalid namespace
	 */
	public function setNamespace(string $namespace): self
	{
		if (!is_string($namespace) || empty($namespace)) {
			throw new TranslatorException('Namespace must be nonempty string.');
		}

		$this->namespace = $namespace;
		return $this;
	}



	/**
	 * Set current language.
	 * @throws TranslatorException
	 */
	public function setCurrentLang(string $lang): self
	{
		if (!is_string($lang) || empty($lang)) {
			throw new TranslatorException('Language must be nonempty string.');
		}
		if ($this->lang === $lang) {
			return $this;
		}
		if ($this->availableLanguages && !array_key_exists($lang, $this->availableLanguages)) {
			throw new TranslatorException("Language $lang is not available.");
		}

		$this->lang = $lang;
		return $this;
	}



	/**
	 * Set default language.
	 * @throws TranslatorException
	 */
	public function setDefaultLang(string $lang): self
	{
		if (!is_string($lang) || empty($lang)) {
			throw new TranslatorException('Language must be nonempty string.');
		}
		if ($this->defaultLang === $lang) {
			return $this;
		}
		if ($this->availableLanguages && !array_key_exists($lang, $this->availableLanguages)) {
			throw new TranslatorException("Language $lang is not available.");
		}

		$this->defaultLang = $lang;
		return $this;
	}



	/**
	 * Give array with language name associated with plural forms meta such as:
	 * nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));
	 * @throws TranslatorException
	 */
	public function setAvailableLanguages(array $languages): self
	{
		if (!is_array($languages) || empty($languages)) {
			throw new TranslatorException("Available languages must be nonempty array.");
		}

		foreach ($languages as $lang => $pluralForms) {
			if (!is_string($lang)) {
				// just the code - plural rule built in (PluralRules), otherwise $defaultPluralForms
				$lang = $pluralForms;
				$pluralForms = null;
			}
			$this->availableLanguages[$lang] = $pluralForms;
		}

		if ($this->lang && !array_key_exists($this->lang, $this->availableLanguages)) {
			throw new TranslatorException("Set language $this->lang is not available.");
		}
		if (!array_key_exists((string) $this->defaultLang, $this->availableLanguages)) {
			throw new TranslatorException("Default language $this->defaultLang is not available.");
		}

		return $this;
	}


	public function setPresenterLanguageParam(string $paramName): void
	{
		$this->presenterLanguageParam = $paramName;
	}



	/**
	 * Translates string.
	 * Give original string or array of its original variants.
	 * Rest of arguments are handed to sprintf() function.
	 * @throws TranslatorException
	 */
	public function translate(string|\Stringable|array $message, mixed ...$parameters): string|\Stringable //($string, $count = 1)
	{
		$count = isset($parameters[0]) ? $parameters[0] : 0;
		$hasVariants = false;
		if (is_array($message)) {
			$hasVariants = true;
			$stringVariants = array_map('trim', array_values($message));
			$message = trim((string) $message[0]);
			$this->plurals[(string) $this->namespace][$message] = true;
		} else {
			$message = trim((string) $message);
			$plural = 0;
			if (is_array($count)) {
				$args =  $count;

			} elseif (func_num_args() > 2) {
				$args = func_get_args();
				unset($args[0]);
				$args = array_values($args);

			} else {
				$args = array($count);
			}
		}

		if ($hasVariants) {
			if (is_array($count)) {
				$args = $count;
			} elseif (($argc = func_num_args()) > 2) {
				$args = func_get_args();
				unset($args[0]);
				$args = array_values($args);
			} if (isset($args)) {
				unset($count);
				foreach ($args as $arg) {
					if (is_numeric($arg)) {
						$count = (int) $arg;
						break;
					}
				}
				if (!isset($count)) {
					$count = 1;
				}
			}
			else {
				if (is_numeric($count)) {
					$count = (int) $count;
				} else {
					$count = 1;
				}
				$args = array($count);
			}

			$plural = $this->getVariant($count);
		}

		$lang = $this->getCurrentLang();

		if ($lang === $this->defaultLang) {
			if ($hasVariants) {
				if (isset($stringVariants[$plural])) {
					$translated = $stringVariants[$plural];
				} else {
					$translated = end($stringVariants);
				}
			}
			else {
				$translated = $message;
			}
		}

		else {
			$translated = $this->translatorStorage->getTranslation($message, $lang, $plural, $this->namespace);
			if (!is_string($translated) && !is_null($translated)) {
				throw new TranslatorException('ITranslatorStorage::getTranslation() must return string, '.gettype($translated).' returned.');
			}

			if (!$translated) {
				$this->addUntranslated($message, $hasVariants);

				if ($hasVariants) {
					if (isset($stringVariants[$plural])) {
						$translated = $stringVariants[$plural];
					} else {
						$translated = end($stringVariants);
					}
				} else {
					$translated = $message;
				}
			}
		}

		if (false !== strpos($translated, '%')) {
			$tmp = str_replace(array('%label', '%name', '%value'), array('#label', '#name', '#value'), $translated);
			if (preg_match('/%(?:\d+\$)?(?:[-+ 0]|\'.)*\d*(?:\.\d+)?[bcdeEfFgGosuxX%](?![a-zA-Z])/', $tmp)) {
				try {
					$translated = vsprintf($tmp, $args);
				} catch (\ValueError $e) {
					$translated = $tmp;
				}
				$translated = str_replace(array('#label', '#name', '#value'), array('%label', '%name', '%value'), $translated);
			}
		}

		return $translated;
	}



	/**
	 * @throws TranslatorException
	 */
	public function getAllStrings(): array
	{
		$strings = $this->translatorStorage->getAllTranslations($this->getCurrentLang(), $this->namespace);
		if (!is_array($strings)) {
			throw new TranslatorException('ITranslatorStorage::getAllTranslations() must return array, '.gettype($strings).' returned.');
		}

		return $strings + array_fill_keys(array_keys($this->getUntranslated()), false);
	}



	/**
	 * Plural texts of the current language and namespace: used as plural (an array of forms) in this request,
	 * recorded as plural among strings without translation, or translated in more than one form. The panel offers
	 * all plural forms of the language (getVariantsCount()) only for them, one field for the other texts.
	 *
	 * @return array<string, true>
	 */
	public function getPluralStrings(): array
	{
		$plurals = $this->plurals[(string) $this->namespace] ?? array();
		foreach ($this->getUntranslated() as $original => $plural) {
			if ($plural) {
				$plurals[$original] = true;
			}
		}
		foreach ($this->translatorStorage->getAllTranslations($this->getCurrentLang(), $this->namespace) as $original => $variants) {
			if (is_array($variants) && count($variants) > 1) {
				$plurals[$original] = true;
			}
		}

		return $plurals;
	}



	/**
	 * Set translation string(s).
	 */
	public function setTranslation(string $original, string|array|bool $translated): void
	{
		$lang = $this->getCurrentLang();
		if ($lang === $this->defaultLang) {
			return;
		}
		$original = trim($original);
		if ($translated === false) {
			$this->translatorStorage->removeTranslation($original, $lang, $this->namespace);
			$this->removeUntranslated($original);
			return;
		}

		if (!is_array($translated)) {
			$translated = array($translated);
		}
		$translated = array_values($translated);
		$filled = 0;
		foreach ($translated as $variant => $string) {
			// the panel sends null for an empty form between filled ones - the variant index must stay
			if (!is_string($string) || $string === '') {
				continue;
			}
			$this->translatorStorage->setTranslation($original, $string, $lang, $variant, $this->namespace);
			$filled++;
		}

		// a plural text stays among untranslated (with its plural mark) until all plural forms are filled
		$untranslated = $this->getUntranslated();
		if (empty($untranslated[$original]) || $filled >= $this->getVariantsCount()) {
			$this->removeUntranslated($original);
		}
	}



	/**
	 * Strings without translation for the current language and namespace (original => used as plural) - loaded from
	 * IUntranslatedStorage once per request, without it collected in this request only.
	 *
	 * @return array<string, bool>
	 */
	protected function &getUntranslated(): array
	{
		$lang = $this->getCurrentLang();
		$key = $lang . '|' . $this->namespace;
		if (!isset($this->untranslated[$key])) {
			$this->untranslated[$key] = $this->untranslatedStorage?->getUntranslated($lang, $this->namespace) ?? array();
		}

		return $this->untranslated[$key];
	}



	/**
	 * The storage is written only for a string not recorded yet (or recorded, but newly used as plural) - not on every
	 * request that renders it.
	 */
	private function addUntranslated(string $original, bool $plural): void
	{
		$untranslated = &$this->getUntranslated();
		if (array_key_exists($original, $untranslated) && ($untranslated[$original] || !$plural)) {
			return;
		}

		$untranslated[$original] = $plural;
		$this->untranslatedStorage?->addUntranslated($original, $this->getCurrentLang(), $this->namespace, $plural);
	}



	private function removeUntranslated(string $original): void
	{
		$untranslated = &$this->getUntranslated();
		if (!array_key_exists($original, $untranslated)) {
			return;
		}

		unset($untranslated[$original]);
		$this->untranslatedStorage?->removeUntranslated($original, $this->getCurrentLang(), $this->namespace);
	}



	private function evalPluralForms(int $count = 1, ?string $lang = null): array
	{
		$lang = $lang ?: $this->getCurrentLang();
		$pluralForms = $this->availableLanguages[$lang] ?? null;
		if ($pluralForms === null) {
			// no explicit rule: built-in rule of the language (no eval()), otherwise the default meta
			$builtIn = PluralRules::get($lang);
			if ($builtIn !== null) {
				[$nplurals, $rule] = $builtIn;
				return array($nplurals, $rule(abs($count)));
			}
			$pluralForms = self::$defaultPluralForms;
		}
		if (!$pluralForms) {
			throw new TranslatorException("Empty plural-form meta for language $lang.");
		}

		// the meta is evaluated as PHP code - let only the plural-form syntax through (a label used as the value,
		// e.g. setAvailableLanguages(['cs' => 'cs']), used to end with a ParseError from eval())
		$pluralForms = rtrim(trim($pluralForms), ';') . ';';
		if (!preg_match('/^nplurals\s*=\s*\d+\s*;\s*plural\s*=[\sn\d!=<>&|?:%()+\-*\/]+;$/', $pluralForms)) {
			throw new TranslatorException(
				"Invalid plural-form meta '$pluralForms' for language $lang, expected e.g. 'nplurals=2; plural=(n != 1);'."
			  . ' Values of setAvailableLanguages() are plural-form rules, not labels.');
		}

		$eval = preg_replace('/([a-z]+)/', '$$1', "n=$count;$pluralForms");
		eval($eval);

		if (!isset($nplurals)) {
			throw new TranslatorException("Cannot resolve nplurals form count for $lang. Check plural-form meta $pluralForms.");
		}
		if (!isset($plural)) {
			throw new TranslatorException("Cannot resolve plural form for $lang. Check plural-form meta $pluralForms.");
		}
		// a comparison rule (gettext "plural=(n != 1);") evaluates to bool
		$nplurals = (int) $nplurals;
		$plural = (int) $plural;
		if (($plural +1) > $nplurals) {
			throw new TranslatorException(
				"Plural-form parse error for $lang. Plural form cannot exceed ".($nplurals-1)
			  . " regarding to nplural=$nplurals, but $plural returned. Check plural-form meta $pluralForms.");
		}

		return array($nplurals, $plural);
	}
}
