<?php
declare(strict_types=1);

namespace LiveTranslator;

/**
 * Built-in plural rules by the GNU gettext manual, "Plural forms"
 * (https://www.gnu.org/software/gettext/manual/html_node/Plural-forms.html), as PHP closures - no eval().
 * Used for languages given to Translator::setAvailableLanguages() without an explicit rule; an unknown language
 * falls back to Translator::$defaultPluralForms.
 */
final class PluralRules
{

	/**
	 * Rule for a language code (`cs`, `pt_BR`, `cs-CZ` - a region falls back to the language).
	 *
	 * @return array{int, \Closure(int): int}|null number of forms and the form index for a count, NULL = unknown language
	 */
	public static function get(string $lang): ?array
	{
		$code = strtolower(str_replace('-', '_', trim($lang)));
		$rule = self::forCode($code);
		if ($rule === null && str_contains($code, '_')) {
			$rule = self::forCode(explode('_', $code, 2)[0]);
		}

		return $rule;
	}


	/**
	 * @return array{int, \Closure(int): int}|null
	 */
	private static function forCode(string $code): ?array
	{
		return match ($code) {
			// nplurals=1; plural=0;
			'ja', 'vi', 'ko', 'th' => array(1, static fn(int $n): int => 0),
			// nplurals=2; plural=n != 1;
			'en', 'de', 'nl', 'sv', 'da', 'no', 'nb', 'nn', 'fo', 'es', 'pt', 'it', 'el', 'bg', 'fi', 'et', 'he', 'id', 'eo', 'hu', 'tr'
				=> array(2, static fn(int $n): int => $n !== 1 ? 1 : 0),
			// nplurals=2; plural=n>1;
			'pt_br', 'fr' => array(2, static fn(int $n): int => $n > 1 ? 1 : 0),
			// nplurals=3; plural=n%10==1 && n%100!=11 ? 0 : n != 0 ? 1 : 2;
			'lv' => array(3, static fn(int $n): int => $n % 10 === 1 && $n % 100 !== 11 ? 0 : ($n !== 0 ? 1 : 2)),
			// nplurals=3; plural=n==1 ? 0 : n==2 ? 1 : 2;
			'ga' => array(3, static fn(int $n): int => $n === 1 ? 0 : ($n === 2 ? 1 : 2)),
			// nplurals=3; plural=n==1 ? 0 : (n==0 || (n%100 > 0 && n%100 < 20)) ? 1 : 2;
			'ro' => array(3, static fn(int $n): int => $n === 1 ? 0 : (($n === 0 || ($n % 100 > 0 && $n % 100 < 20)) ? 1 : 2)),
			// nplurals=3; plural=n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 2;
			'lt' => array(3, static fn(int $n): int => $n % 10 === 1 && $n % 100 !== 11 ? 0 : ($n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2)),
			// nplurals=3; plural=n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2;
			'ru', 'uk', 'be', 'sr', 'hr' => array(3, static fn(int $n): int => $n % 10 === 1 && $n % 100 !== 11
				? 0
				: ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2)),
			// nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;
			'cs', 'sk' => array(3, static fn(int $n): int => $n === 1 ? 0 : ($n >= 2 && $n <= 4 ? 1 : 2)),
			// nplurals=3; plural=n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2;
			'pl' => array(3, static fn(int $n): int => $n === 1 ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2)),
			// nplurals=4; plural=n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3;
			'sl' => array(4, static fn(int $n): int => $n % 100 === 1 ? 0 : ($n % 100 === 2 ? 1 : ($n % 100 === 3 || $n % 100 === 4 ? 2 : 3))),
			// nplurals=6; plural=n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5;
			'ar' => array(6, static fn(int $n): int => $n === 0
				? 0
				: ($n === 1 ? 1 : ($n === 2 ? 2 : ($n % 100 >= 3 && $n % 100 <= 10 ? 3 : ($n % 100 >= 11 ? 4 : 5))))),
			default => null,
		};
	}
}
