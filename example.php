<?php

include_once 'php-geocode.php';

Geocode::setup('pgsql:host=localhost dbname=my_db', 'my_username',
	'my_password');

$geocode = new Geocode(48.857487, 2.351074);
var_dump($geocode->nearestOne());

$geocode = new Geocode('Eastleigh');
var_dump($geocode->latlng());

$geocode = new Geocode('Paris', 'FR'); // Hint that it's in France
var_dump($geocode->latlng());
