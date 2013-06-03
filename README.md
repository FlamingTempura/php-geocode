php-geocode
===========

php-geocode is a simple offline PHP geocoding library. It is useful when geocoding web services (e.g., Google Geocoding) are too limiting. These web services often impose a limit on the number of requests and request rates, making it too limiting for batch processes.

php-geocode performs geocoding on your server, taking away that limitations.


Requirements
------------

* PHP 5.3 (although may work on earlier if ZipArchive is installed)
* PostgreSQL
* PostGIS

Installation
------------

Install PostgreSQL and PostGIS -- http://postgis.net/install/

Create a database and add the PostGIS extension
```
sudo -u postgres psql postgres
> CREATE DATABASE my_db OWNER my_user;
sudo -u my_user psql my_db
> CREATE EXTENSION postgis;
```

Download and configure php-geocode.
```
git clone git@github.com:FlamingTempura/php-geocode.git
```

Edit the database and data source settings in import.php. Alternative datasets can be found at http://download.geonames.org/export/dump/ (default is cities1000.zip).
```
nano import.php
```

Run import.php.

```
php import.php
```

Usage
-----

Once the data has been imported, you only need php-geocode.php. Make sure you do not have import.php in your web root.

### Setup

You must setup php-geocode to run with your database:

```php
include_once 'php-geocode.php';
Geocode::setup('pgsql:host=localhost dbname=my_db', 'my_username', 'my_password');
```

### Geocoding

php-geocode can be used to retrieve the latitude and longitude of cities and towns.

```php
$geocode = new Geocode('Eastleigh');
var_dump($geocode->latlng());
// array(5) {
//   ["id"] => int(345150)
//   ["name"] => string(9) "Eastleigh"
//   ["countrycode"] => string(2) "GB"
//   ["longitude"] => float(-1.35)
//   ["latitude"] => float(50.96667)
// }
```

Town and city names may be used in different countries. You may specify an ISO-3166 2-letter country code to identify where to search for the place.

```php
$geocode = new Geocode('Paris', 'FR'); // Hint that it's in France
var_dump($geocode->latlng());
// array(5) {
//   ["id"] => int(352968)
//   ["name"] => string(5) "Paris"
//   ["countrycode"] => string(2) "FR"
//   ["longitude"] => float(2.3488)
//   ["latitude"] => float(48.85341)
// }

```

### Reverse Geocoding

php-geocode may be used to identify the closest towns and cities to a latitude and longitude.

```php
$geocode = new Geocode(48.857487, 2.351074);
var_dump($geocode->nearestOne()); // You may specify a radius to search in (in meters)
// array(5) {
//   ["id"] => int(352968)
//   ["name"] => string(5) "Paris"
//   ["countrycode"] => string(2) "FR"
//   ["longitude"] => float(2.3488)
//   ["latitude"] => float(48.85341)
// }


var_dump($geocode->nearest(4, 2000)); // Find closest 4 places within 2km

// array(4) {
//   [0] => array(5) {
//     ["id"] => int(352968)
//     ["name"] => string(5) "Paris"
//     ["countrycode"] => string(2) "FR"
//     ["longitude"] => float(2.3488)
//     ["latitude"] => float(48.85341)
//   }
//   ...
// }


```
