<?php

namespace LiveTranslator;

use Nette;

/**
 * Translator implementation.
 *
 * @author Vladislav Hejda
 *
 * @property string $namespace
 * @property string $currentLang
 * @property string $defaultLang
 * @property array $availableLanguages
 * @property string $presenterLanguageParam
 * @property-read bool $currentLangDefault
 */
class Translator extends Nette\Object implements Nette\Localization\ITranslator
{

	/** @var string plural-form meta */
	public static $defaultPluralForms = 'nplurals=1; plural=0;';

	/* @var string */
	private $namespace;

	/** @var string */
	private $defaultLang;

	/** @var string */
	private $lang;

	/** @var array */
	private $availableLanguages = array();

	/** @var string */
	private $presenterLanguageParam = array();

	/** @var ITranslatorStorage */
	private $translatorStorage;

	/** @var Nette\Http\SessionSection */
	private $session;

	/** @var Nette\Application\Application */
	private $application;

	/** @var \Nette\Caching\Cache */
	private $cache;


	/**
	 * @param string  $defaultLang
	 * @param ITranslatorStorage $translatorStorage
	 * @param Nette\Http\Session $session
	 * @param Nette\Application\Application $application
	 * @param \Nette\Caching\IStorage $cacheStorage
	 */
	public function __construct($defaultLang, ITranslatorStorage $translatorStorage, Nette\Http\Session $session, Nette\Application\Application $application, Nette\Caching\IStorage $cacheStorage)
	{
		$this->setDefaultLang($defaultLang);
		$this->translatorStorage = $translatorStorage;
		$session->start();
		$this->session = $session;
		$this->application = $application;

		$this->cache = new Nette\Caching\Cache($cacheStorage, 'VladaHejda.LiveTranslator');
	}


	public function cacheDisable($disable = TRUE)
	{
		if ($disable) {
			$this->cache = NULL;
		}
	}


	/**
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->namespace;
	}


	/**
	 * @return string
	 */
	public function getCurrentLang()
	{
		if ($this->lang) {
			return $this->lang;
		}
		if ($this->presenterLanguageParam) {
			$presenter = $this->application->presenter;
			if (isset($presenter->{$this->presenterLanguageParam})) {
				$this->setCurrentLang($presenter->{$this->presenterLanguageParam});
				return $this->lang;
			}
		}
		return $this->lang = $this->defaultLang;
	}


	/**
	 * @return string
	 */
	public function getDefaultLang()
	{
		return $this->defaultLang;
	}


	/**
	 * @return bool
	 */
	public function isCurrentLangDefault()
	{
		return $this->getCurrentLang() === $this->defaultLang;
	}


	/**
	 * @return array
	 */
	public function getAvailableLanguages()
	{
		return $this->availableLanguages ? array_keys($this->availableLanguages) : NULL;
	}


	/**
	 * @param string $lang
	 * @return array (nplurals, plural)
	 */
	public function getVariantsCount($lang = NULL)
	{
		list($nplurals) = $this->evalPluralForms(1, $lang);
		return $nplurals;
	}


	/**
	 * @param int $count
	 * @param string $lang
	 * @return int
	 */
	public function getVariant($count, $lang = NULL)
	{
		list(, $plural) = $this->evalPluralForms($count, $lang);
		return $plural;
	}


	/**
	 * @return string
	 */
	public function getPresenterLanguageParam()
	{
		return $this->presenterLanguageParam;
	}


	/**
	 * @param string $switchLang
	 * @return string
	 */
	public function getPresenterLink($switchLang)
	{
		if (!$this->presenterLanguageParam) {
			return NULL;
		}
		return $this->application->presenter->link('this', array($this->presenterLanguageParam => $switchLang));
	}


	/**
	 * @param string $namespace
	 * @return self
	 * @throws TranslatorException on invalid namespace
	 */
	public function setNamespace($namespace)
	{
		if (!is_string($namespace) || empty($namespace)) {
			throw new TranslatorException('Namespace must be nonempty string.');
		}

		$this->namespace = $namespace;
		return $this;
	}



	/**
	 * Set current language.
	 * @param string $lang
	 * @return self
	 * @throws TranslatorException
	 */
	public function setCurrentLang($lang)
	{
		if (!is_string($lang) || empty($lang)) {
			throw new TranslatorException('Language must be nonempty string.');
		}
		if ($this->lang === $lang) {
			return $this;
		}
		if ($this->availableLanguages && !isset($this->availableLanguages[$lang])) {
			throw new TranslatorException("Language $lang is not available.");
		}

		$this->lang = $lang;
		return $this;
	}



	/**
	 * Set default language.
	 * @param string $lang
	 * @return self
	 * @throws TranslatorException
	 */
	public function setDefaultLang($lang)
	{
		if (!is_string($lang) || empty($lang)) {
			throw new TranslatorException('Language must be nonempty string.');
		}
		if ($this->defaultLang === $lang) {
			return $this;
		}
		if ($this->availableLanguages && !isset($this->availableLanguages[$lang])) {
			throw new TranslatorException("Language $lang is not available.");
		}

		$this->defaultLang = $lang;
		return $this;
	}



	/**
	 * Give array with language name associated with plural forms meta such as:
	 * nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));
	 * @param array
	 * @return self
	 * @throws TranslatorException
	 */
	public function setAvailableLanguages(array $languages)
	{
		if (!is_array($languages) || empty($languages)) {
			throw new TranslatorException("Available languages must be nonempty array.");
		}

		foreach ($languages as $lang => $pluralForms) {
			if (!is_string($lang)) {
				$lang = $pluralForms;
				$pluralForms = self::$defaultPluralForms;
			}
			$this->availableLanguages[$lang] = $pluralForms;
		}

		if ($this->lang && !isset($this->availableLanguages[$this->lang])) {
			throw new TranslatorException("Set language $this->lang is not available.");
		}
		if (!isset($this->availableLanguages[$this->defaultLang])) {
			throw new TranslatorException("Default language $this->defaultLang is not available.");
		}

		return $this;
	}


	/**
	 * @param string $paramName
	 */
	public function setPresenterLanguageParam($paramName)
	{
		$this->presenterLanguageParam = $paramName;
	}



	/**
	 * Translates string.
	 * Give original string or array of its original variants.
	 * Rest of arguments are handed to sprintf() function.
	 * @param string|array $string
	 * @param int $count
	 * @return string
	 * @throws TranslatorException
	 */
	public function translate($string, $count = NULL)
	{
		$hasVariants = FALSE;
		if (is_array($string)) {
			$hasVariants = TRUE;
			$stringVariants = array_map('trim', array_values($string));
			$string = trim((string) $string[0]);
		} else {
			$string = trim((string) $string);
			$plural = 0;
			if (is_array($count)) {
				$args =  $count;

			} elseif (func_num_args() > 2) {
				$args = func_get_args();
				unset($args[0]);
				$args = array_values($args);

			} elseif ($count === NULL) {
				$args = NULL;

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
				$translated = $string;
			}
		}

		else {
			$translated = $this->translatorStorage->getTranslation($string, $lang, $plural, $this->namespace);
			if (!is_string($translated) && !is_null($translated)) {
				throw new TranslatorException('ITranslatorStorage::getTranslation() must return string, '.gettype($translated).' returned.');
			}

			if (!$translated) {
				$this->setNewStringUntranslated($string);

				if ($hasVariants) {
					if (isset($stringVariants[$plural])) {
						$translated = $stringVariants[$plural];
					} else {
						$translated = end($stringVariants);
					}
				} else {
					$translated = $string;
				}
			}
		}

		if ($args !== NULL AND FALSE !== strpos($translated, '%')) {
			$tmp = str_replace(array('%label', '%name', '%value'), array('#label', '#name', '#value'), $translated);
			if (FALSE !== strpos($tmp, '%')) {
				$translated = vsprintf($tmp, $args);
				$translated = str_replace(array('#label', '#name', '#value'), array('%label', '%name', '%value'), $translated);
			}
		}

		return $translated;
	}



	/**
	 * @return array
	 * @throws TranslatorException
	 */
	public function getAllStrings()
	{
		$strings = $this->translatorStorage->getAllTranslations($this->getCurrentLang(), $this->namespace);
		if (!is_array($strings)) {
			throw new TranslatorException('ITranslatorStorage::getAllTranslations() must return array, '.gettype($strings).' returned.');
		}

		$newStrings = $this->getNewStrings();
		return $strings + (is_array($newStrings) ? $newStrings : array());
	}



	/**
	 * Set translation string(s).
	 * @param string $original
	 * @param string|array|bool $translated array of variants or default variant or FALSE to remove translation.
	 */
	public function setTranslation($original, $translated)
	{
		$lang = $this->getCurrentLang();
		if ($lang === $this->defaultLang) {
			return;
		}
		$original = trim($original);
		if ($translated === FALSE) {
			$this->translatorStorage->removeTranslation($original, $lang, $this->namespace);
			$this->setNewStringUntranslated($original);
			return;
		}

		if (!is_array($translated)) {
			$translated = array($translated);
		}
		$translated = array_values($translated);
		foreach ($translated as $variant => $string) {
			$this->translatorStorage->setTranslation($original, $string, $lang, $variant, $this->namespace);
		}
		$this->setNewStringTranslated($original);
	}



	protected function getSessionSection()
	{
		$ns = $this->namespace ?: 'default';
		return $this->session->getSection("LT-$ns");
	}



	protected function saveNewStrings(array $newStrings)
	{
		if (isset($this->cache)) {
			$ns = $this->namespace ?: 'default';
			$this->cache->save("newStrings-$ns", $newStrings);

		} else {
			$section = $this->getSessionSection();
			$section->strings = $newStrings;
		}
	}



	protected function setNewStringTranslated($original)
	{
		$newStrings = $this->getNewStrings();
		unset($newStrings[$original]);
		$this->saveNewStrings($newStrings);
	}



	protected function setNewStringUntranslated($original)
	{
		$newStrings = $this->getNewStrings();
		$newStrings[$original] = FALSE;
		$this->saveNewStrings($newStrings);
	}



	protected function getNewStrings()
	{
		if (isset($this->cache)) {
			$ns = $this->namespace ?: 'default';
			$strings = (array) $this->cache->load("newStrings-$ns");

		} else {
			// todo mohlo by to mít jednu section a ns by byly jednotlivý property zde
			$section = $this->getSessionSection();
			if (!isset($section->strings)) {
				$section->strings = array();
			}
			$strings = $section->strings;
		}

		return $strings;
	}



	private function evalPluralForms($count = 1, $lang = NULL)
	{
		$lang = $lang ?: $this->getCurrentLang();
		$pluralForms = isset($this->availableLanguages[$lang]) ? $this->availableLanguages[$lang] : self::$defaultPluralForms;
		if (!$pluralForms) {
			throw new TranslatorException("Empty plural-form meta for language $lang.");
		}

		$eval = preg_replace('/([a-z]+)/', '$$1', "n=$count;$pluralForms");
		eval($eval);

		if (!isset($nplurals)) {
			throw new TranslatorException("Cannot resolve nplurals form count for $lang. Check plural-form meta $pluralForms.");
		}
		if (!isset($plural)) {
			throw new TranslatorException("Cannot resolve plural form for $lang. Check plural-form meta $pluralForms.");
		}
		if (($plural +1) > $nplurals) {
			throw new TranslatorException(
				"Plural-form parse error for $lang. Plural form cannot exceed ".($nplurals-1)
			  . " regarding to nplural=$nplurals, but $plural returned. Check plural-form meta $pluralForms.");
		}

		return array($nplurals, $plural);
	}
}


class TranslatorException extends \Exception {}
