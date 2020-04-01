<?php
require 'flight/Flight.php';
require 'cockpit/Cockpit.php';

Cockpit::useInstruments(['markdown']);

Flight::route('/', function(){
	echo Flight::markdown()->line('**Hello** _world_!');
});

Flight::start();
