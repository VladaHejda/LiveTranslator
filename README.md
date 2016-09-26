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
via panel in debug bar. Works with the Nette 2.

*LiveTranslator is forked from [NetteTranslator](https://github.com/straiki/NetteTranslator), uses its robust
parts (especially [TranslationPanel](http://forum.nette.org/cs/4399-nette-translation-panel-preklady-primo-v-prohlizeci))
and changes other, mainly the storage where translations are saved.*


Installation
---

- Download from Github: <https://github.com/VladaHejda/LiveTranslator>
- or better use [Composer](http://getcomposer.org/doc/00-intro.md#declaring-dependencies):

```json
{
	"require": {
		"vladahejda/livetranslator": "~1.0"
	},
	"minimum-stability": "RC"
}
```

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
In default `LiveTranslator\Storage\NetteDatabase` uses `Nette\Caching`. For disabling this function use `cacheDisable()` method:  
```
services:
	translatorStorage: 
		class: LiveTranslator\Storage\NetteDatabase(localization_text, localization)
		setup:
			- cacheDisable()
```

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


### 2. add LiveTranslator and Panel service

Into your config file add two more services `LiveTranslator\Translator` and `LiveTranslator\Panel` and define
the default language (it is language which in your web is written basically):
```
nette:
	debugger:
		bar:
			- LiveTranslator\Panel
services:
	translator: LiveTranslator\Translator(en)
	translatorPanel: LiveTranslator\Panel
```

In default `LiveTranslator\Translator` uses `Nette\Caching` for storage untranslated strings. For disabling caching use `cacheDisable()` method. Then will be use SEESIONS:  
```
services:
	translator: 
		class: LiveTranslator\Translator(en)
		setup:
			- cacheDisable()
	...
```


### 3. set up your BasePresenter

Inject LiveTranslator, set current language and give translator to template and forms:
```php
class BasePresenter extends \Nette\Application\UI\Presenter
{
	/** @var string @persistent */
	public $lang = 'en';

	/** @var \LiveTranslator\Translator @inject */
	public $translator;

	// since Nette 2.1 you can omit this method
	public function injectTranslator(\LiveTranslator\Translator $translator)
	{
		$this->translator = $translator;
	}

	public function startup()
	{
		parent::startup();
		$this->translator->setCurrentLang($this->lang);
	}
	
	protected function createTemplate($class = NULL)
	{
		$template = parent::createTemplate($class);
		$template->setTranslator($this->translator);
		return $template;
	}

	// to have translated even forms add this method too
	protected function createComponent($name)
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


### Translate plurals (1 apple → 2 apples)

You can say what plural-form each language uses via `setAvailableLanguages`, this way:
```
	setup:
		- setAvailableLanguages([
			en: "nplurals=2; plural=(n==1) ? 0 : 1;",
			cz: "nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));",
		])
```
(to understand this see [plural forms](https://github.com/translate/l10n-guide/blob/master/docs/l10n/pluralforms.rst#plural-forms))

Then the panel will let you translate the text even in plural. More you need to do is to give plural variants
of the default language to the translator, in array. And the number. Example:
`$translator->translate( array( 'There is %d apple', 'There are %d apples' ), 3 )`
or in latte: `{_ ['There is %d apple', 'There are %d apples'], 3}`.


### Using namespaces

When there is huge amount of texts at your web, it would be good to sort them somehow. Just give the namespace
to the translator dependent for example on [module](http://doc.nette.org/en/presenters#toc-modules).
`$translator->setNamespace('products')`

Panel will separate all texts from another namespaces.


And now, enjoy.


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
