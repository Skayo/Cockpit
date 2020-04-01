<?php

class Cockpit {

	private static $availableInstruments = [
		'auth'     => 'Auth',
		'flash'    => 'Flash',
		'csrf'     => 'Csrf',
		'i18n'     => 'I18n',
		'store'    => 'Store',
		'db'       => 'DB',
		'markdown' => 'Markdown',
	];

	public static $configFile = 'config.ini';

	private static $initialized = false;

	public static function init () {
		// Add instruments path
		Flight::path('cockpit/instruments');

		// Load config
		$config = parse_ini_file(self::$configFile, true, INI_SCANNER_TYPED);

		foreach ($config as $section => $sectionContent) {
			if (empty($sectionContent))
				continue;

			if ($section == 'cockpit.db') {
				Flight::set($section, $sectionContent); // So I don't have to modify the DB class more than needed
				continue;
			}

			foreach ($sectionContent as $key => $value) {
				Flight::set("$section.$key", $value);
			}
		}

		// Initialisation complete
		self::$initialized = true;
	}

	public static function useInstruments ($instruments = []) {
		if (!self::$initialized)
			self::init();

		if (!is_array($instruments)) {
			$instruments = [$instruments];
		}

		foreach ($instruments as $instrument) {
			if (!isset(self::$availableInstruments[$instrument]))
				throw new Exception("No cockpit instrument named '$instrument' found!");

			Flight::register($instrument, self::$availableInstruments[$instrument]);
		}
	}

}