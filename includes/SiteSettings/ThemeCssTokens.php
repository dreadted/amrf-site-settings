<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * --amrf-* custom properties declared in the active theme's own CSS files,
 * e.g. "--amrf-theme-color: var(--wp--preset--color--primary-500)".
 *
 * @package Antropomorf\SiteSettings
 */
class ThemeCssTokens
{
	private const TRANSIENT = 'amrf_theme_css_tokens';
	private const SKIPPED_DIRS = ['.git', 'node_modules', 'vendor', 'tests', 'test-results'];

	/**
	 * @return array<string, string> Property name => raw value.
	 */
	public static function get(): array
	{
		static $tokens = null;
		if ($tokens !== null) {
			return $tokens;
		}

		$cached = get_transient(self::TRANSIENT);
		if (!is_array($cached) || !self::isFresh($cached)) {
			$cached = self::scan();
			// Nothing found: rescan sooner, since there's no file mtime to notice a new declaration by.
			set_transient(self::TRANSIENT, $cached, $cached['files'] ? DAY_IN_SECONDS : HOUR_IN_SECONDS);
		}

		return $tokens = $cached['tokens'];
	}

	/**
	 * @param array{theme: string, files: array<string, int>, tokens: array<string, string>} $cached
	 */
	private static function isFresh(array $cached): bool
	{
		if (($cached['theme'] ?? '') !== get_stylesheet() || !isset($cached['files'], $cached['tokens'])) {
			return false;
		}

		foreach ($cached['files'] as $path => $mtime) {
			if (@filemtime($path) !== $mtime) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Child theme first, so its declarations win over the parent's.
	 *
	 * @return array{theme: string, files: array<string, int>, tokens: array<string, string>}
	 */
	private static function scan(): array
	{
		$tokens = [];
		$files = [];

		foreach (array_unique([get_stylesheet_directory(), get_template_directory()]) as $dir) {
			foreach (self::cssFiles($dir) as $path) {
				$css = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($path));
				if (!preg_match_all('/(--amrf-[a-z0-9-]+)\s*:\s*([^;}]+)/i', $css, $matches, PREG_SET_ORDER)) {
					continue;
				}

				foreach ($matches as [, $name, $value]) {
					$tokens[$name] ??= trim($value);
				}
				$files[$path] = (int) filemtime($path);
			}
		}

		return ['theme' => get_stylesheet(), 'files' => $files, 'tokens' => $tokens];
	}

	/**
	 * @return string[]
	 */
	private static function cssFiles(string $dir): array
	{
		if (!is_dir($dir)) {
			return [];
		}

		$filter = new \RecursiveCallbackFilterIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			fn (\SplFileInfo $file) => $file->isDir()
				? !in_array($file->getFilename(), self::SKIPPED_DIRS, true)
				: $file->getExtension() === 'css'
		);

		$paths = [];
		foreach (new \RecursiveIteratorIterator($filter) as $file) {
			// A symlinked directory isn't descended into, so it arrives here as a leaf.
			if ($file->isFile()) {
				$paths[] = $file->getPathname();
			}
		}
		sort($paths);

		return $paths;
	}
}
