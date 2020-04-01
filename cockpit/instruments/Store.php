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

	public $fileHandlers = [];

	public function __construct () {
		$this->storageDir = Flight::get('cockpit.store.path') ?: './store';

		if (!is_dir($this->storageDir))
			throw new Exception("Storage dir '$this->storageDir' isn't a directory!");

		$this->autoSave = Flight::get('cockpit.store.auto_save') ?: true;
		$this->prettify = Flight::get('cockpit.store.prettify') ?: false;
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

	public function get ($collectionName) {
		$this->__get($collectionName);
	}

	private function load ($collectionName) {
		$filePath = rtrim($this->storageDir, '/') . "/$collectionName.json";

		$this->fileHandlers[$collectionName] = fopen($filePath, 'cb+');

		if (!$this->fileHandlers[$collectionName])
			throw new Exception("Couldn't open file '$filePath'");

		$fileSize = filesize($filePath);
		$rawJSON = '[]';

		if ($fileSize > 0) {
			$rawJSON = fread($this->fileHandlers[$collectionName], $fileSize);

			if (!$rawJSON)
				throw new Exception("Couldn't read file '$filePath'");
		}

		$data = json_decode($rawJSON, true);

		if (json_last_error() !== JSON_ERROR_NONE)
			throw new Exception("Error while parsing JSON of collection '$collectionName': " . json_last_error_msg());

		return $data;
	}

	public function save () {
		foreach ($this->collections as $collectionName => $collection) {
			$collectionData = $collection->getData();

			$filePath = rtrim($this->storageDir, '/') . "/$collectionName.json";

			$rawJSON = json_encode($collectionData, $this->prettify ? JSON_PRETTY_PRINT : 0);

			if (json_last_error() !== JSON_ERROR_NONE)
				throw new Exception("Error while JSON-encoding data of collection '$collectionName': " . json_last_error_msg());

			if (!is_writable($filePath) && file_exists($filePath))
				throw new Exception("File '$filePath' not writable");

			if (!ftruncate($this->fileHandlers[$collectionName], 1))
				throw new Exception("Couldn't truncate file '$filePath'");

			if (!rewind($this->fileHandlers[$collectionName]))
				throw new Exception("Couldn't rewind file '$filePath'");

			if (!fwrite($this->fileHandlers[$collectionName], $rawJSON))
				throw new Exception("Couldn't write file '$filePath'");

			fclose($this->fileHandlers[$collectionName]);
		}
	}
}