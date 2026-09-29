<?php
declare(strict_types=1);

namespace LiveTranslator\Storage;

use LiveTranslator\IUntranslatedStorage;

/**
 * Strings without translation in a database table (MySQL/MariaDB), see NetteDatabase.createTable.untranslated.sql.
 * Opt-in instead of the default UntranslatedFile, e.g. together with Storage\NetteDatabase:
 *
 *     services:
 *         - LiveTranslator\Storage\UntranslatedNetteDatabase(localization_untranslated)
 *
 * Uniqueness is guarded by the table (lang + ns + SHA-1 of the text, ON DUPLICATE KEY UPDATE), so concurrent requests do not
 * create duplicates. No namespace is stored as an empty string (a unique index allows repeated NULLs).
 */
class UntranslatedNetteDatabase implements IUntranslatedStorage
{

	private \Nette\Database\Explorer $db;

	private string $table;


	/**
	 * @param string $tableName name of table with texts without translation
	 */
	public function __construct(string $tableName, \Nette\Database\Explorer $db)
	{
		$this->db = $db;
		$this->table = trim($tableName, '`'); // delimited by ?name in the queries
	}


	/**
	 * @return array<string, bool>
	 */
	public function getUntranslated(string $lang, ?string $namespace = null): array
	{
		$rows = $this->db->fetchAll(
			'SELECT `text`, `plural` FROM ?name WHERE `lang` = ? AND `ns` = ? ORDER BY `id`',
			$this->table,
			$lang,
			(string) $namespace,
		);

		$originals = array();
		foreach ($rows as $row) {
			if (is_string($row->text)) {
				$originals[$row->text] = (bool) $row->plural;
			}
		}

		return $originals;
	}


	public function addUntranslated(string $original, string $lang, ?string $namespace = null, bool $plural = false): void
	{
		// an existing row is only marked as plural, never unmarked
		$this->db->query(
			'INSERT INTO ?name (`lang`, `ns`, `hash`, `text`, `plural`) VALUES (?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE `plural` = GREATEST(`plural`, ?)',
			$this->table,
			$lang,
			(string) $namespace,
			sha1($original),
			$original,
			(int) $plural,
			(int) $plural,
		);
	}


	public function removeUntranslated(string $original, string $lang, ?string $namespace = null): void
	{
		$this->db->query(
			'DELETE FROM ?name WHERE `lang` = ? AND `ns` = ? AND `hash` = ?',
			$this->table,
			$lang,
			(string) $namespace,
			sha1($original),
		);
	}

}
