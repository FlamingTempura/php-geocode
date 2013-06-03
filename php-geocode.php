<?php

class Geocode {
	public $args;
	public function __construct () {
		$this->args = func_get_args();
	}

	// Geocoding - get latlng of city or town
	public function latlng () {
		if (count($this->args) > 1) {
			self::$qLatLngCountry->execute(array(
				':name' => $this->args[0],
				':countrycode' => $this->args[1]
			));
			return self::exportGeoname(self::$qLatLngCountry->fetch(PDO::FETCH_ASSOC));
		} else {
			self::$qLatLng->execute(array(
				':name' => $this->args[0]
			));
			return self::exportGeoname(self::$qLatLng->fetch(PDO::FETCH_ASSOC));
		}
	}

	// Reverse geocoding - returns closest geonames within radius
	public function nearest ($limit = 1, $within = 10000) {
		self::$qNearest->execute(array(
			':lng' => $this->args[1],
			':lat' => $this->args[0],
			':limit' => $limit,
			':within' => $within
		));
		return array_map(function ($row) {
			return self::exportGeoname($row);
		}, self::$qNearest->fetchAll(PDO::FETCH_ASSOC));
	}

	public function nearestOne ($within = 10000) {
		$nearest = $this->nearest(1, $within);
		return count($nearest) ? $nearest[0] : false;
	}

	private static $pdo, $qNearest, $qLatLng, $qLatLngCountry;

	private static function exportGeoname ($geoname) {
		$latlng = json_decode($geoname['coord'])->coordinates;
		$geoname['longitude'] = $latlng[0];
		$geoname['latitude'] = $latlng[1];
		unset($geoname['coord']);
		return $geoname;
	}

	public static function setup ($dsn, $username = null, $password = null) {
		self::$pdo = new PDO($dsn, $username, $password);
		self::$qNearest = self::$pdo->prepare(
			'	SELECT id, name, countrycode,
					ST_AsGeoJSON(coordinate) AS coord,
					ST_Distance(coordinate, location) AS distance
				FROM geoname,
					(SELECT ST_MakePoint(:lng, :lat)::geography AS location) AS location
				WHERE ST_DWithin(coordinate, location, :within)
				ORDER BY distance
				LIMIT :limit');
		self::$qLatLng = self::$pdo->prepare(
			'	SELECT id, name, countrycode, ST_AsGeoJSON(coordinate) AS coord
				FROM geoname WHERE name = :name LIMIT 1');
		self::$qLatLngCountry = self::$pdo->prepare(
			'	SELECT id, name, countrycode, ST_AsGeoJSON(coordinate) AS coord
				FROM geoname WHERE name LIKE :name AND countrycode = :countrycode LIMIT 1');
	}

}
