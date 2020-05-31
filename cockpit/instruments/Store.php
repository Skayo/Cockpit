<?php


class Collection extends ArrayObject {
	public function getData () {
		return parent::getArrayCopy();
	}

	public function setData ($data) {
		return parent::exchangeArray($data);
	}

	public function __get ($name) {
		if ($name === 'data')
			return $this->getData();

		return null;
	}

	public function __set ($name, $value) {
		if ($name === 'data')
			return $this->setData($value);

		return null;
	}
}


class Store {
	private $storageDir = './store';

	private $autoSave = true;

	private $prettify = false;

	public $collections = [];

	public function __construct () {
		$storageDir = Flight::get('cockpit.store.path') ?: './store';

		if (!realpath($storageDir) || !is_dir(realpath($storageDir)))
			throw new Exception("Storage dir '$storageDir' isn't a directory or doesn't exist!");

		$this->storageDir = realpath($storageDir);

		$this->autoSave = Flight::get('cockpit.store.auto_save') ?: true;
		$this->prettify = Flight::get('cockpit.store.prettify') ?: false;
		$this->useJson = Flight::get('cockpit.store.use_json') ?: false;
	}

	public function __destruct () {
		if ($this->autoSave)
			$this->save();
	}

	public function __get ($collectionName) {
		if (isset($this->collections[$collectionName]))
			return $this->collections[$collectionName];

		$collectionData = $this->load($collectionName);

		$collection = new Collection($collectionData);

		$this->collections[$collectionName] = $collection;

		return $collection;
	}

	public function in ($collectionName) {
		$this->__get($collectionName);
	}

	private function load ($collectionName) {
		$filePath = rtrim($this->storageDir, '/\\') . "/$collectionName." . ($this->useJson ? 'json' : 'php');

		$data = [];

		if (file_exists($filePath)) {
			if ($this->useJson) {
				$rawJSON = file_get_contents($filePath);

				if ($rawJSON === false)
					throw new Exception("Couldn't read file '$filePath'");

				$data = json_decode($rawJSON, true);

				if (json_last_error() !== JSON_ERROR_NONE)
					throw new Exception("Error while parsing JSON of collection '$collectionName': " . json_last_error_msg());
			} else {
				$data = require $filePath;
			}
		}

		return $data;
	}

	public function save () {
		foreach ($this->collections as $collectionName => $collection) {
			$collectionData = $collection->getData();

			$filePath = rtrim($this->storageDir, '/\\') . "/$collectionName." . ($this->useJson ? 'json' : 'php');

			if (file_exists($filePath) && !is_writable($filePath))
				throw new Exception("'$filePath' is not writable");

			if ($this->useJson) {
				$newData = json_encode($collectionData, $this->prettify ? JSON_PRETTY_PRINT : 0);

				if (json_last_error() !== JSON_ERROR_NONE)
					throw new Exception("Error while JSON-encoding data of collection '$collectionName': " . json_last_error_msg());
			} else {
				$newData = "<?php\nreturn " . var_export($collectionData, true) . ';';
			}

			file_put_contents($filePath, $newData);
		}
	}
}