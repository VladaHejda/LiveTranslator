[![Latest Stable Version](https://poser.pugx.org/vladahejda/livetranslator/v/stable.png)](https://packagist.org/packages/vladahejda/livetranslator)
[![Total Downloads](https://poser.pugx.org/vladahejda/livetranslator/downloads.png)](https://packagist.org/packages/vladahejda/livetranslator)
[![Montly Downloads](https://poser.pugx.org/vladahejda/livetranslator/d/monthly.png)](https://packagist.org/packages/vladahejda/livetranslator)
[![Daily Downloads](https://poser.pugx.org/vladahejda/livetranslator/d/daily.png)](https://packagist.org/packages/vladahejda/livetranslator)

[![Build Status](https://secure.travis-ci.org/VladaHejda/LiveTranslator.png?branch=master)](https://travis-ci.org/VladaHejda/LiveTranslator)


[DEMO](http://livetranslator.hejdav.cz/)

LiveTranslator
===

LiveTranslator is tool for [Nette Framework](http://nette.org/en/).

LiveTranslator enables simple and user friendly localization of your web, by letting you to translate your texts
via panel in debug bar. Requires PHP 8.3+ and Nette 3.2+.

*LiveTranslator is forked from [NetteTranslator](https://github.com/straiki/NetteTranslator), uses its robust
parts (especially [TranslationPanel](http://forum.nette.org/cs/4399-nette-translation-panel-preklady-primo-v-prohlizeci))
and changes other, mainly the storage where translations are saved.*


Installation
---

Use [Composer](http://getcomposer.org/doc/00-intro.md#declaring-dependencies) with the fork repository
(`vladahejda/livetranslator` on Packagist is the original package from 2013 without PHP 8 / Nette 3 support):

```json
{
	"repositories": [
		{"type": "vcs", "url": "https://github.com/Fire-man-x/LiveTranslator"}
	],
	"require": {
		"vladahejda/livetranslator": "^3.0"
	}
}
```

Upgrading from 2.x? See [Upgrading from 2.x](#upgrading-from-2x) - the translator no longer uses the session.

Then load classes via autoloader ([composer autoloading](http://getcomposer.org/doc/01-basic-usage.md#autoloading)
or Nette RobotLoader).


Usage
---

To launch the translator follow these steps:


### 1. prepare storage

- **I am using Nette Database**

Execute SQL script in `LiveTranslator/Storage/NetteDatabase.createTable.sql` (or its namespaced version,
see [using namespaces](#using-namespaces)) at your database.

Open your configuration file and add service:
```
services:
	translatorStorage: LiveTranslator\Storage\NetteDatabase(localization_text, localization)
```

*You can rename tables in SQL script (use the same names in config file).*

- **I am using [Dibi](http://dibiphp.com/)**

Dibi storage is in progress. You can write your own storage. See below.

- **I have no database**

You can store translations into plaintext file. Just add following service into your config
and choose persistent and write-accessible (existing) directory:
```
services:
	translatorStorage: LiveTranslator\Storage\File(%appDir%/../data/localization)
```

- **I want to save translations elsewhere**

Look at the interface `LiveTranslator\ITranslatorStorage` and implement it to write your own storage.

Then add storage into your configuration file as a service.

#### Strings without translation

The translator itself does not use the session. Strings requested without translation (offered by the panel) are kept
by `LiveTranslator\IUntranslatedStorage`:

- `LiveTranslator\Storage\UntranslatedFile` (default with `Storage\File`) - a JSON object `{"original": plural}`
  (e.g. `{"Save": false, "%d day": true}`) in `<lang>.<namespace>.untranslated` next to the translations
  (e.g. `cs.admin.untranslated`). The file is written only when a new string appears (under an exclusive lock -
  concurrent requests) and removed when the list gets empty. With another translation storage register it with
  a writable directory:
  ```
  services:
      - LiveTranslator\Storage\UntranslatedFile(%appDir%/../data/localization)
  ```
- `LiveTranslator\Storage\UntranslatedSession` - in the session of the current user (the behavior before 3.0):
  ```
  services:
      - LiveTranslator\Storage\UntranslatedSession
  ```
  The session is started by the first recorded string, possibly during template rendering - make sure it can still be
  started then (render the template into a string first, or start the session earlier).
- `LiveTranslator\Storage\UntranslatedNetteDatabase` - in a database table (MySQL/MariaDB). Create it by
  `LiveTranslator/Storage/NetteDatabase.createTable.untranslated.sql` and register:
  ```
  services:
      - LiveTranslator\Storage\UntranslatedNetteDatabase(localization_untranslated)
  ```

The registered service is autowired into the translator (register just one of them). Without any, the strings are kept
for the current request only. The list is per language; a string disappears from it when it is translated (a plural
text when all its forms are filled) or erased in the panel. Your own storage implements `LiveTranslator\IUntranslatedStorage`.

Since 3.0 the translator constructor has no `Nette\Http\Session` parameter (autowired configuration needs no change).

#### Plural forms

A text given as an array of forms is plural - `{_['%d day', '%d days'], $count}` in Latte,
`$translator->translate(['%d day', '%d days'], $count)` in PHP. The panel offers all plural forms of the language only
for plural texts (and texts already translated in more forms), one field for the others.

Plural rules of common languages are built in (`LiveTranslator\PluralRules`, by the GNU gettext manual), so language
codes are enough: `setAvailableLanguages(['cs', 'en'])`. An explicit rule overrides the built-in one:
`setAvailableLanguages(['cs' => 'nplurals=3; plural=(n==1) ? 0 : ((n>=2 && n<=4) ? 1 : 2);'])` - it is evaluated
as PHP, so nested ternary operators must be parenthesized. A language unknown to the table uses
`Translator::$defaultPluralForms` (one form).


### 2. add LiveTranslator and Panel service

Into your config file add two more services `LiveTranslator\Translator` and `LiveTranslator\Panel\Panel` and define
the default language (it is language which in your web is written basically):
```
tracy:
	bar:
		- LiveTranslator\Panel\Panel
services:
	translator: LiveTranslator\Translator(en)
	translatorPanel: LiveTranslator\Panel\Panel
```


### 3. set up your BasePresenter

Inject LiveTranslator, set current language and give translator to template and forms:
```php
class BasePresenter extends \Nette\Application\UI\Presenter
{
	/** @persistent */
	public string $lang = 'en';

	/** @inject */
	public \LiveTranslator\Translator $translator;

	public function startup()
	{
		parent::startup();
		$this->translator->setCurrentLang($this->lang);
	}

	protected function createTemplate(?string $class = null): \Nette\Application\UI\Template
	{
		$template = parent::createTemplate($class);
		$template->setTranslator($this->translator);
		return $template;
	}

	// to have translated even forms add this method too
	protected function createComponent(string $name): ?\Nette\ComponentModel\IComponent
	{
		$component = parent::createComponent($name);
		if ($component instanceof \Nette\Forms\Form) {
			$component->setTranslator($this->translator);
		}
		return $component;
	}
}
```


### 4. mark texts for translation

In presenters call `$this->translator->translate('text to translate')`, in latte use underscore macro
`{_ 'text to translate'}`

Done. In development mode look at your debugbar. You can see panel "translations". Browse your web and texts will
occurs in panel. Switch languages and translate!


Advanced
---

If you want to use your translator fully, there is some more stuff you would do.


### Say translator which languages you use

Call function `$translator->setAvailableLanguages()` and give the array of languages that your web is available in.
Then set the name of presenter language persistent parameter (`$translator->setPresenterLanguageParam()`).

Better way of this is setting it in config:
```
	translator:
		class: LiveTranslator\Translator(en)
		setup:
			- setAvailableLanguages([en, de, fr])
			- setPresenterLanguageParam(lang)
```

Now your panel will display links to switch the languages!


### Use skills of `sprintf`

If you give more arguments to translate method, it will be handed to php function
[sprintf](http://php.net/manual/en/function.sprintf.php).

That means that `$translator->translate('Call me %s.', 'Johan')` results in "Call me Johan", whereas
"Johan" will not be translated.

It can be used in latte too.

`sprintf` is only invoked when the translated text actually contains a real format specifier
(`%s`, `%d`, `%1$s`, ...). A plain `%` sign in your text (e.g. `"Tax is 21%"`) is left as literal text and never
breaks the translation, even without any arguments.


### Translate plurals (1 apple → 2 apples)

Give the plural variants of the default language to the translator in an array, and the number:
`$translator->translate(['There is %d apple', 'There are %d apples'], 3)`
or in latte: `{_ ['There is %d apple', 'There are %d apples'], 3}`.

Such a text is plural: the panel offers all plural forms of the current language for it (three in Czech, two
in English, ...), one field for common texts. The plural rule of the language is built in for common languages
(`LiveTranslator\PluralRules`), so `setAvailableLanguages([en, cs])` is enough. For a language outside the table,
or to change the rule, give it explicitly:
```
	setup:
		- setAvailableLanguages([
			en: "nplurals=2; plural=(n==1) ? 0 : 1;",
			cz: "nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));",
			xx: "nplurals=2; plural=(n==1) ? 0 : 1;",
		])
```
(to understand this see [plural forms](https://www.gnu.org/software/gettext/manual/html_node/Plural-forms.html)).
The explicit rule is evaluated as PHP - only the plural-form syntax is accepted (anything else throws
`TranslatorException`) and nested ternary operators must be parenthesized.


### Using namespaces

When there is huge amount of texts at your web, it would be good to sort them somehow. Just give the namespace
to the translator dependent for example on [module](http://doc.nette.org/en/presenters#toc-modules).
`$translator->setNamespace('products')`

Panel will separate all texts from another namespaces.


And now, enjoy.


Changes in 3.0
---

**Session**
- The translator does not use the session - `Translator::__construct(string $defaultLang, ITranslatorStorage $translatorStorage,
  Application $application, ?IUntranslatedStorage $untranslatedStorage = null)`. It used to start the session in its
  constructor, so every request (bots included) got a session just because the translator service was created.

**Strings without translation** (offered by the panel) - new interface `LiveTranslator\IUntranslatedStorage`:
- `Storage\UntranslatedFile` - JSON object in `<lang>.<namespace>.untranslated` next to the translations, the default
  with `Storage\File` (`File::getStorageDir()`); a JSON list of originals (3.0 pre-release format) is still read.
- `Storage\UntranslatedSession` - in the session (the behavior before 3.0), the session starts with the first recorded string.
- `Storage\UntranslatedNetteDatabase` - table from `Storage/NetteDatabase.createTable.untranslated.sql` (MySQL/MariaDB),
  uniqueness by language + namespace + SHA-1 of the text, texts of any length.
- The storage is written only when a new string appears, not on every request that renders it.

**Plural forms**
- Plural texts: a text given as an array of forms is plural. The panel offers all plural forms of the language only
  for plural texts (and texts translated in more forms), one field ("Translation") for the others -
  `Translator::getPluralStrings()`. The plural mark is kept with the untranslated strings; a plural text stays
  untranslated until all its forms are filled.
- Built-in plural rules of common languages (`LiveTranslator\PluralRules`, by the GNU gettext manual, PHP closures
  without `eval()`): ja/vi/ko/th, en/de/nl/sv/da/no/nb/nn/fo/es/pt/it/el/bg/fi/et/he/id/eo/hu/tr, fr/pt_BR, lv, ga,
  ro, lt, ru/uk/be/sr/hr, cs/sk, pl, sl, ar. A code with a region (`cs-CZ`, `pt_BR`) falls back to the language.
  `setAvailableLanguages()` needs just language codes; an explicit rule overrides the built-in one.
- An explicit rule is validated before `eval()`: a value that is not a plural-form rule (e.g. a label,
  `setAvailableLanguages(['cs' => 'cs'])`) throws `TranslatorException` instead of `ParseError: unexpected end of file`,
  a missing trailing semicolon is added and no other code gets to `eval()`.
- A comparison rule (`plural=(n != 1)`) evaluates to bool - the result is cast to int (`getVariant(): int` threw TypeError).

**Fixes**
- `Storage\File`: a file whose last line lacks the newline (hand-edited) had its last translation unreadable, and
  a translation saved from the panel was glued to it on the same line (`unserialize(): Extra data`).
- Panel: the `ordinal` filter threw TypeError (`substr()` with int under `strict_types`) for languages with more than
  one plural form.
- `Translator::setTranslation()` skips an empty form (the panel sends `null` for an empty field between filled ones)
  and keeps the form indexes.


Upgrading from 2.x
---

- **Constructor** - no `Nette\Http\Session` parameter. Autowired configuration (`LiveTranslator\Translator(en)`) needs
  no change; direct `new Translator('en', $storage, $session, $application)` must drop `$session`.
- **Removed** protected `Translator::getSessionSection()` and `getNewStrings()` (see `getUntranslated()`).
- **Untranslated strings** are no longer in the session. With `Storage\File` they go to `*.untranslated` files next to
  the translations automatically (make the directory writable, consider ignoring `*.untranslated` in VCS). With another
  translation storage register one of `UntranslatedFile`, `UntranslatedSession` (the old behavior) or
  `UntranslatedNetteDatabase` (create its table first).
- The untranslated list is **per language** (the session list was shared by all languages).
- **Plural forms**: languages given just by codes used one form; now they get the built-in rule (e.g. Czech three
  forms) - the panel shows more fields, but only for plural texts. Explicit rules keep working if they are valid
  PHP (parenthesized nested ternaries).
- The session is no longer started by the translator. If a component starts the session (or writes to it) only while
  a template is rendered - a form with CSRF protection created in `{control}`, `UntranslatedSession` - make sure
  the session can still be started then: render the template into a string before sending it, or start the session
  earlier. (Before 3.0 the translator's own `$session->start()` hid this.)


Running tests
---

Install dependencies including dev requirements and run the [Nette Tester](https://tester.nette.org/) suite:
```
composer install
vendor/bin/tester -s tests/
```

(`tests/run-tests.sh` is kept for historical reasons only — it points to a path Nette Tester no longer uses,
so call `vendor/bin/tester` directly as shown above.)

The test of `Storage\UntranslatedNetteDatabase` runs only against a MySQL/MariaDB database given by environment
variables (it uses a temporary table), otherwise it is skipped:
```
LIVETRANSLATOR_TEST_DSN="mysql:host=127.0.0.1;dbname=test" LIVETRANSLATOR_TEST_USER=root LIVETRANSLATOR_TEST_PASSWORD=... vendor/bin/tester -s tests/
```


Authors
---

*(alphabetic order)*

- Josef Kufner (jk@frozen-doe.net)
- Miroslav Paulík (https://github.com/castamir)
- Roman Sklenář (http://romansklenar.cz)
- Miroslav Smetana
- Jan Smitka
- Patrik Votoček (patrik@votocek.cz)
- Tomáš Votruba (tomas.vot@gmail.com)
- Václav Vrbka (gmvasek@php-info.cz)
- Vladislav Hejda


Under *New BSD License*
