<?php


class Utils {
	public static function init () {
		Flight::map('layout', 'Utils::layout');
		Flight::map('section', 'Utils::section');
		Flight::map('console', 'Utils::console');
	}

	public static function layout ($file, $checkVar = 'content') {
		Flight::before('stop', function () use ($file, $checkVar) {
			if (Flight::view()->has($checkVar))
				Flight::render($file);
		});
	}

	private static $currSectionName;

	public static function section ($name = null) {
		if (is_null($name) && !is_null(self::$currSectionName)) {
			Flight::view()->set(self::$currSectionName, ob_get_clean());
			self::$currSectionName = null;
		} else if (!is_null($name)) {
			self::$currSectionName = $name;
			ob_start();
		}
	}

	public static function console () {
		return new class {
			public function __call ($type, $data) {
				foreach ($data as $_data) {
					switch (gettype($_data)) {
						case 'array':
						case 'object':
							$_data = json_encode($_data, JSON_PRETTY_PRINT);
							break;

						case 'string':
							$_data = '`' . $_data . '`';
							break;

						case 'NULL':
							$_data = null;
							break;

						case 'boolean':
							$_data = ($_data) ? 'true' : 'false';
							break;

						case 'resource':
						case 'resource (closed)':
							$_data = 'Resource [' . get_resource_type($_data) ?: 'unknown' . ']';
							break;
					}

					echo "<script>console.$type($_data);</script>";
				}
			}
		};
	}
}