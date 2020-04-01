<?php


class Flash {

	public $messages = [];

	private $sessionKey = '';

	public function __construct () {
		if (session_status() == PHP_SESSION_NONE)
			session_start();

		$this->sessionKey = Flight::get('cockpit.flash.session_key') ?: 'flight_flash';

		if (isset($_SESSION[$this->sessionKey]) && is_array($_SESSION[$this->sessionKey]))
			$this->messages = $_SESSION[$this->sessionKey];

		$_SESSION[$this->sessionKey] = [];
	}

	public function __call ($key, $args) {
		$message = $args[0];
		$this->add($key, $message);
	}

	public function add ($key, $message) {
		if (!isset($_SESSION[$this->sessionKey][$key]))
			$_SESSION[$this->sessionKey][$key] = [];

		$_SESSION[$this->sessionKey][$key][] = $message;
	}

	public function addNow ($key, $message) {
		if (!isset($this->messages[$key]))
			$this->messages[$key] = [];

		$this->messages[$key][] = $message;
	}

	public function get ($key = null) {
		if (is_null($key))
			return $this->messages;

		return (isset($this->messages[$key])) ? $this->messages[$key] : [];
	}

	public function getFirst ($key, $default = null) {
		$message = $this->get($key);

		if (is_array($message) && count($message) > 0)
			return $message[0];

		return $default;
	}

	public function has ($key) {
		return isset($this->messages[$key]);
	}

	/**
	 * @param array $keys (array) Clear all of the messages with a key in the array
	 *                    (empty array|null) Clear all messages
	 *                    (string)  Only clear messages in with that one given key
	 */
	public function clear ($keys = []) {
		if ((is_array($keys) && empty($keys)) || is_null($keys) || !$keys) {
			$_SESSION[$this->sessionKey] = [];
			$this->messages = [];
		} else if (!is_array($keys)) {
			$keys = [$keys];
		}

		foreach ($keys as $key) {
			unset($_SESSION[$this->sessionKey][$key]);
			unset($this->messages[$key]);
		}
	}
}