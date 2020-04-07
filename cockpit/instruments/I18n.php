<?php

/*!
 * Modified Copy of i18next-php
 * https://github.com/Mika-/i18next-php
 *
 * Copyright (C), Mika-
 * Released under the BEER-WARE license
 */

class I18n {
	private static $path = null;

	private static $language = null;

	private static $fallbackLanguage = 'en';

	private static $recursionLimit = 0;

	private static $translation = [];

	private static $missingKeys = [];

	public function __construct () {
		self::$fallbackLanguage = Flight::get('cockpit.i18n.fallback_lang') ?: 'en';
		self::$path = Flight::get('cockpit.i18n.path');
		self::$recursionLimit = Flight::get('cockpit.i18n.recursion_limit') ?: 10;

		self::$language = self::$fallbackLanguage;

		self::loadTranslation();

		Flight::map('t', 'I18n::getTranslation');
	}

	public function setLang ($language, $fallback = null) {
		self::$language = $language;

		if (!is_null($fallback))
			self::$fallbackLanguage = $fallback;
	}

	public function getMissingKeys () {
		return self::$missingKeys;
	}

	public function keyExists ($key) {
		return self::getKey($key) !== false;
	}

	public static function getTranslation ($key, $variables = [], $recursionCount = 0) {
		$return = self::getKey($key, $variables);

		// Log missing translation
		if (!$return && array_key_exists('lng', $variables))
			array_push(self::$missingKeys, ['language' => $variables['lng'], 'key' => $key]);
		else if (!$return)
			array_push(self::$missingKeys, ['language' => self::$language, 'key' => $key]);

		// fallback language check
		if (!$return && !isset($variables['lng']) && !empty(self::$fallbackLanguage))
			$return = self::getKey($key, array_merge($variables, ['lng' => self::$fallbackLanguage]));

		if (!$return && array_key_exists('defaultValue', $variables))
			$return = $variables['defaultValue'];

		if ($return && isset($variables['postProcess']) && $variables['postProcess'] === 'sprintf' && isset($variables['sprintf'])) {
			if (is_array($variables['sprintf']))
				$return = vsprintf($return, $variables['sprintf']);
			else
				$return = sprintf($return, $variables['sprintf']);
		}

		// Handle nested translations (by francis.crossen@888holdings.com)
		if ($return && strpos($return, '$t(') !== false && $recursionCount < self::$recursionLimit) {
			$recursionCount++;
			$pattern = '/\$t\((.*)\)/U';
			$found = preg_match_all($pattern, $return, $matches);

			if ($found) {
				$replacements = $matches[0];
				$keys = $matches[1];
				$replacementCount = count($replacements);
				for ($index = 0; $index < $replacementCount; $index++) {
					$return = str_replace($replacements[$index], self::getTranslation($keys[$index], $variables, $recursionCount), $return);
				}
			}
		}

		if (isset($variables['returnObjectTrees']) && $variables['returnObjectTrees'] === true)
			$return = explode("\n", $return);

		if (!$return)
			$return = $key;

		foreach ($variables as $variable => $value) {
			if (is_string($value) || is_numeric($value)) {
				$return = str_replace('__' . $variable . '__', $value, $return);
				$return = str_replace('{{' . $variable . '}}', $value, $return);
			}
		}

		return $return;
	}

	private static function loadTranslation () {
		$path = preg_replace('/__(.+?)__/', '*', self::$path, 2, $hasNs);

		if (!preg_match('/\.json$/', $path)) {
			$path = $path . 'translation.json';

			self::$path = self::$path . 'translation.json';
		}

		$dir = glob($path);

		if (count($dir) === 0)
			throw new Exception('Translation file not found!');

		foreach ($dir as $file) {
			$translation = file_get_contents($file);

			$translation = json_decode($translation, true);

			if ($translation === null)
				throw new Exception("Invalid json in file '$file'!");

			if ($hasNs) {
				$regexp = preg_replace('/__(.+?)__/', '(?<$1>.+)?', preg_quote(self::$path, '/'));

				preg_match('/^' . $regexp . '$/', $file, $ns);

				if (!array_key_exists('lng', $ns))
					$ns['lng'] = self::$language;

				if (array_key_exists('ns', $ns)) {
					if (array_key_exists($ns['lng'], self::$translation) && array_key_exists($ns['ns'], self::$translation[$ns['lng']]))
						self::$translation[$ns['lng']][$ns['ns']] = array_merge(self::$translation[$ns['lng']][$ns['ns']], [$ns['ns'] => $translation]);
					else if (array_key_exists($ns['lng'], self::$translation))
						self::$translation[$ns['lng']] = array_merge(self::$translation[$ns['lng']], [$ns['ns'] => $translation]);
					else
						self::$translation[$ns['lng']] = [$ns['ns'] => $translation];
				} else {
					if (array_key_exists($ns['lng'], self::$translation))
						self::$translation[$ns['lng']] = array_merge(self::$translation[$ns['lng']], $translation);
					else
						self::$translation[$ns['lng']] = $translation;
				}
			} else {
				if (array_key_exists(self::$language, $translation))
					self::$translation = $translation;
				else
					self::$translation = array_merge(self::$translation, $translation);
			}
		}
	}

	private static function getKey ($key, $variables = []) {
		$return = false;

		if (array_key_exists('lng', $variables) && array_key_exists($variables['lng'], self::$translation))
			$translation = self::$translation[$variables['lng']];
		else if (array_key_exists(self::$language, self::$translation))
			$translation = self::$translation[self::$language];
		else
			$translation = [];

		// path traversal - last array will be response
		$paths_arr = explode('.', $key);

		while ($path = array_shift($paths_arr)) {
			if (array_key_exists($path, $translation) && is_array($translation[$path]) && count($paths_arr) > 0) {
				$translation = $translation[$path];
			} else if (array_key_exists($path, $translation)) {
				// Request has context
				if (array_key_exists('context', $variables)) {
					if (array_key_exists($path . '_' . $variables['context'], $translation))
						$path = $path . '_' . $variables['context'];
				}

				// Request is plural form
				if (array_key_exists('count', $variables)) {
					if ($variables['count'] != 1 && array_key_exists($path . '_plural_' . $variables['count'], $translation))
						$path = $path . '_plural_' . $variables['count'];
					else if ($variables['count'] != 1 && array_key_exists($path . '_plural', $translation))
						$path = $path . '_plural';
				}

				$return = $translation[$path];

				break;
			} else {
				return false;
			}
		}

		if (is_array($return) && array_keys($return) === range(0, count($return) - 1))
			$return = implode("\n", $return);
		else if (is_array($return))
			return false;

		return $return;
	}

}