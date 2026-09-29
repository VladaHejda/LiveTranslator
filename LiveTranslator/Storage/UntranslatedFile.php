<?php
declare(strict_types=1);

namespace LiveTranslator\Storage;

use LiveTranslator\IUntranslatedStorage;

/**
 * Strings without translation in files `<lang>.<namespace>.untranslated` (JSON list), usually in the same directory
 * as the translations of Storage\File (e.g. `cs.admin` -> `cs.admin.untranslated`). The default for the translator
 * with Storage\File.
 *
 * A file is written only when the list changes (read-modify-write under an exclusive lock - concurrent requests)
 * and removed when the list gets empty.
 */
class UntranslatedFile implements IUntranslatedStorage
{
	private const Suffix = '.untranslated';

	private string $storageDir;


	/**
	 * @throws \Nette\DirectoryNotFoundException
	 */
	public function __construct(string $storageDir)
	{
		$realStorageDir = realpath($storageDir);
		if ($realStorageDir === false) {
			throw new \Nette\DirectoryNotFoundException("Directory $storageDir was not found.");
		}

		$this->storageDir = $realStorageDir;
	}


	/**
	 * @return string[]
	 */
	public function getUntranslated(string $lang, ?string $namespace = null): array
	{
		$filePath = $this->getFilePath($lang, $namespace);
		if (!is_file($filePath)) {
			return array();
		}

		$handler = fopen($filePath, 'r');
		if ($handler === false) {
			return array();
		}
		flock($handler, LOCK_SH);
		$content = stream_get_contents($handler);
		flock($handler, LOCK_UN);
		fclose($handler);

		return $this->decode(is_string($content) ? $content : '');
	}


	public function addUntranslated(string $original, string $lang, ?string $namespace = null): void
	{
		$this->modify($lang, $namespace, function (array $originals) use ($original): array {
			if (!in_array($original, $originals, true)) {
				$originals[] = $original;
			}
			return $originals;
		});
	}


	public function removeUntranslated(string $original, string $lang, ?string $namespace = null): void
	{
		if (!is_file($this->getFilePath($lang, $namespace))) {
			return;
		}

		$this->modify($lang, $namespace, function (array $originals) use ($original): array {
			return array_values(array_filter($originals, function (string $item) use ($original): bool {
				return $item !== $original;
			}));
		});
	}


	protected function getFilePath(string $lang, ?string $namespace = null): string
	{
		return $this->storageDir . DIRECTORY_SEPARATOR . $lang . ($namespace === null ? '' : ".$namespace") . self::Suffix;
	}


	/**
	 * @param callable(string[]): string[] $modify
	 */
	private function modify(string $lang, ?string $namespace, callable $modify): void
	{
		$filePath = $this->getFilePath($lang, $namespace);
		$handler = fopen($filePath, 'c+');
		if ($handler === false) {
			throw new \RuntimeException("Unable to open file '$filePath'.");
		}

		flock($handler, LOCK_EX);
		$content = stream_get_contents($handler);
		$originals = $this->decode(is_string($content) ? $content : '');
		$modified = $modify($originals);

		if ($modified !== $originals) {
			ftruncate($handler, 0);
			rewind($handler);
			if ($modified) {
				fwrite($handler, json_encode($modified, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
			}
			fflush($handler);
		}

		flock($handler, LOCK_UN);
		fclose($handler);

		if (!$modified) {
			@unlink($filePath); // nothing to keep - also removes a file just created by 'c+'
		}
	}


	/**
	 * @return string[]
	 */
	private function decode(string $content): array
	{
		$originals = $content === '' ? null : json_decode($content, true);

		return is_array($originals) ? array_values(array_filter($originals, 'is_string')) : array();
	}
}
