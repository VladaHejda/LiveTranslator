<?php
declare(strict_types=1);

namespace LiveTranslator\Storage;


class File implements \LiveTranslator\ITranslatorStorage
{

	protected string $storageDir;

	/** @var resource[] */
	protected $handlers = array();

	private array $newTranslations = array();

	private array $metaData = array();


	/**
	 * @throws \Nette\DirectoryNotFoundException
	 */
	public function __construct(string $storageDir)
	{
		$realStorageDir = realpath($storageDir);

		if (false === $realStorageDir) {
			throw new \Nette\DirectoryNotFoundException("Directory $storageDir was not found.");
		}

		$this->storageDir = $realStorageDir;
	}


	/**
	 * Directory with translation files - Translator keeps strings without translation next to them by default
	 * (UntranslatedFile).
	 */
	public function getStorageDir(): string
	{
		return $this->storageDir;
	}


	public function getTranslation(string $original, string $lang, int $variant = 0, ?string $namespace = null): ?string
	{
		$changed = $this->tryGetChanged($original, $lang, $variant, $namespace);
		if (false !== $changed) {
			return $changed;
		}

		$handler = $this->getFileHandler($lang, $namespace);
		$initPos = ftell($handler);
		for ($i = 0; $i < 2; ++$i) {
			$pos = ftell($handler);
			if ($i == 1 && $pos >= $initPos) {
				break;
			}
			while ($translation = fgets($handler)) {
				$translation = $this->terminateLine($translation);
				if (!empty($prepend)) {
					$translation = $prepend . $translation;
					$prepend = null;
				}
				if (substr($translation, -4) !== "\";}\n") {
					$prepend = $translation;
					continue;
				}

				$translationUnserialized = @unserialize(rtrim($translation, "\n"));
				if($translationUnserialized === false)
				{
					bdump("Error in translation: '$translation'");
				}
				if ($original === $translationUnserialized[0]) {
					while (!isset($translationUnserialized[$variant +1])) {
						--$variant;
						if ($variant < 0) {
							return null;
						}
					}
					return $translationUnserialized[$variant +1];
				}
			}
			rewind($handler);
		}
		return null;
	}


	public function getAllTranslations(string $lang, ?string $namespace = null): array
	{
		$handler = $this->getFileHandler($lang, $namespace);
		rewind($handler);
		$translations = array();

		while ($translation = fgets($handler)) {
			$translation = $this->terminateLine($translation);
			if (!empty($prepend)) {
				$translation = $prepend . $translation;
				$prepend = null;
			}
			if (substr($translation, -4) !== "\";}\n") {
				$prepend = $translation;
				continue;
			}

			$translation = unserialize(rtrim($translation, "\n"));
			$translations[array_shift($translation)] = $translation;
		}
		return $translations;
	}


	/**
	 * @return void
	 */
	public function setTranslation(string $original, string $translated, string $lang, int $variant = 0, ?string $namespace = null)
	{
		$this->saveTranslation($original, $translated, $lang, $namespace, $variant);
	}


	/**
	 * @return void
	 */
	public function removeTranslation(string $original, string $lang, ?string $namespace = null)
	{
		$this->saveTranslation($original, false, $lang, $namespace);
	}


	public function __destruct()
	{
		if ($this->newTranslations) {
			foreach ($this->metaData as $meta => $originals) {
				list($lang, $namespace) = unserialize($meta);
				$filePath = $this->storageDir . DIRECTORY_SEPARATOR . $this->getFilename($lang, $namespace);
				$lines = file_exists($filePath) ? file($filePath) : false;
				$data = $lines === false ? array() : array_map(fn(string $row): string => $this->terminateLine($row), $lines);

				foreach ($data as $i => &$row) {
					if (!empty($prepend)) {
						$row = $prepend . $row;
						$prepend = null;
					}
					if (substr($row, -4) !== "\";}\n") {
						$prepend = $row;
						continue;
					}

					$translation = unserialize(rtrim($row, "\n"));
					$index = array_search($translation[0], $originals);

					if (false !== $index) {
						unset($originals[$index]);
						if (false === $this->newTranslations[$translation[0]]) {
							unset($data[$i]);
						} else {
							$translation = $this->newTranslations[$translation[0]] + $translation;
							ksort($translation);
							$row = serialize($translation) . "\n";
						}
					}
				}

				foreach ($originals as $original) {
					if (false === $this->newTranslations[$original]) {
						continue;
					}
					$new = array($original) + $this->newTranslations[$original];
					ksort($new);
					$data[] = serialize($new) . "\n";
				}

				$handler = $this->getFileHandler($lang, $namespace);
				ftruncate($handler, 0);
				rewind($handler);
				$content = implode('', $data);
				fwrite($handler, $content, strlen($content));
			}
		}

		foreach ($this->handlers as $handler) {
			fclose($handler);
		}
	}


	/**
	 * @return resource
	 */
	protected function getFileHandler(string $lang, ?string $namespace = null)
	{
		$file = $this->getFilename($lang, $namespace);

		if (isset($this->handlers[$file])) {
			return $this->handlers[$file];
		}

		$filePath = $this->storageDir . DIRECTORY_SEPARATOR . $file;

		if (file_exists($filePath)) {
			$handler = fopen($filePath, 'r+');
		} else {
			$handler = fopen($filePath, 'w+');;
		}

		return $this->handlers[$file] = $handler;
	}


	/**
	 * The last line of a hand-edited file may lack the newline - without it the last translation was never read
	 * and a translation saved from the panel was glued to it on the same line.
	 */
	private function terminateLine(string $line): string
	{
		return substr($line, -1) === "\n" ? $line : $line . "\n";
	}


	protected function getFilename(string $lang, ?string $namespace = null): string
	{
		return $lang . ($namespace === null ? '' : ".$namespace");
	}


	private function saveTranslation($original, $new, $lang, $namespace, $variant = null)
	{
		if (false !== $new) {
			if (isset($this->newTranslations[$original])) {
				$this->newTranslations[$original][$variant +1] = $new;
				return;
			}
			$new = array($variant +1 => $new);
		}

		$meta = serialize(array($lang, $namespace));
		if (!isset($this->metaData[$meta])) {
			$this->metaData[$meta] = array();
		}

		$this->metaData[$meta][] = $original;
		$this->newTranslations[$original] = $new;
	}


	/**
	 * Returns string translation when change found,
	 * returns null when translation removed,
	 * returns false when change not found.
	 */
	private function tryGetChanged($original, $lang, $variant, $namespace)
	{
		if (array_key_exists($original, $this->newTranslations)) {
			$meta = serialize(array($lang, $namespace));

			if (!isset($this->metaData[$meta]) || !in_array($original, $this->metaData[$meta])) {
				return false;
			}
			if (false === $this->newTranslations[$original]) {
				return null;
			}

			$seekVariant = $variant +1;
			while (!isset($this->newTranslations[$original][$seekVariant])) {
				--$seekVariant;
				if (!$seekVariant) {
					break;
				}
			}

			if ($seekVariant) {
				return $this->newTranslations[$original][$seekVariant];
			}
		}
		return false;
	}
}
