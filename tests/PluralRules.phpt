<?php

use LiveTranslator\PluralRules;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/**
 * @return int[] form index for each count
 */
function forms(string $lang, array $counts): array
{
	[, $rule] = PluralRules::get($lang);
	return array_map($rule, $counts);
}


/***************LANGUAGE CODES***************/

Assert::null(PluralRules::get('xx'));
Assert::null(PluralRules::get(''));
Assert::null(PluralRules::get('cz')); // country code, the language is "cs"

Assert::same(3, PluralRules::get('cs')[0]);
Assert::same(3, PluralRules::get('CS')[0]);
Assert::same(3, PluralRules::get(' cs ')[0]);
// a region falls back to the language
Assert::same(3, PluralRules::get('cs_CZ')[0]);
Assert::same(3, PluralRules::get('cs-CZ')[0]);
Assert::null(PluralRules::get('xx_CZ'));

// pt_BR has its own rule, other regions fall back to pt
Assert::same(array(0, 0, 1), forms('pt_BR', array(0, 1, 2)));
Assert::same(array(0, 0, 1), forms('pt-br', array(0, 1, 2)));
Assert::same(array(1, 0, 1), forms('pt_PT', array(0, 1, 2)));
Assert::same(array(1, 0, 1), forms('pt', array(0, 1, 2)));


/***************RULES (GNU gettext manual)***************/

// one form
Assert::same(1, PluralRules::get('ja')[0]);
Assert::same(array(0, 0, 0, 0), forms('ja', array(0, 1, 2, 100)));

// two forms, singular for one only
Assert::same(2, PluralRules::get('en')[0]);
Assert::same(array(1, 0, 1, 1, 1), forms('en', array(0, 1, 2, 5, 21)));
Assert::same(array(1, 0, 1), forms('de', array(0, 1, 2)));

// two forms, singular for zero and one
Assert::same(2, PluralRules::get('fr')[0]);
Assert::same(array(0, 0, 1, 1), forms('fr', array(0, 1, 2, 5)));

// Czech, Slovak
Assert::same(3, PluralRules::get('cs')[0]);
Assert::same(array(2, 0, 1, 1, 1, 2, 2, 2), forms('cs', array(0, 1, 2, 3, 4, 5, 21, 22)));
Assert::same(array(2, 0, 1, 2), forms('sk', array(0, 1, 4, 5)));

// Polish
Assert::same(array(2, 0, 1, 1, 2, 2, 2, 1, 2), forms('pl', array(0, 1, 2, 4, 5, 12, 21, 22, 112)));

// Russian, Ukrainian, Belarusian, Serbian, Croatian
Assert::same(array(2, 0, 1, 1, 2, 2, 2, 0, 1, 2), forms('ru', array(0, 1, 2, 4, 5, 11, 12, 21, 22, 111)));
Assert::same(forms('ru', range(0, 120)), forms('uk', range(0, 120)));
Assert::same(forms('ru', range(0, 120)), forms('hr', range(0, 120)));

// Latvian - special case for zero
Assert::same(array(2, 0, 1, 1, 0, 1), forms('lv', array(0, 1, 2, 11, 21, 111)));

// Irish - special cases for one and two
Assert::same(array(2, 0, 1, 2), forms('ga', array(0, 1, 2, 3)));

// Romanian
Assert::same(array(1, 0, 1, 1, 2, 2, 1), forms('ro', array(0, 1, 2, 19, 20, 100, 101)));

// Lithuanian
Assert::same(array(2, 0, 1, 2, 2, 0, 1), forms('lt', array(0, 1, 2, 10, 11, 21, 22)));

// Slovenian
Assert::same(4, PluralRules::get('sl')[0]);
Assert::same(array(3, 0, 1, 2, 2, 3, 0, 1), forms('sl', array(0, 1, 2, 3, 4, 5, 101, 102)));

// Arabic
Assert::same(6, PluralRules::get('ar')[0]);
Assert::same(array(0, 1, 2, 3, 3, 4, 4, 5, 5, 3), forms('ar', array(0, 1, 2, 3, 10, 11, 99, 100, 102, 103)));


/***************RULE NEVER EXCEEDS NPLURALS***************/

foreach (array('ja', 'en', 'fr', 'pt_BR', 'lv', 'ga', 'ro', 'lt', 'ru', 'cs', 'pl', 'sl', 'ar') as $lang) {
	[$nplurals, $rule] = PluralRules::get($lang);
	foreach (range(0, 1000) as $n) {
		$form = $rule($n);
		Assert::true($form >= 0 && $form < $nplurals, "$lang: form $form for $n");
	}
}
