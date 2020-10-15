# Laravel Nova PostGIS Coordinates Field

[![Latest Version on Packagist](https://img.shields.io/packagist/v/encima-io/nova-coordinates-postgis-fields.svg)](https://packagist.org/packages/encima-io/nova-coordinates-postgis-fields)
[![License](https://img.shields.io/packagist/l/encima-io/nova-coordinates-postgis-fields.svg)](https://packagist.org/packages/encima-io/nova-coordinates-postgis-fields)
[![Total Downloads](https://img.shields.io/packagist/dt/encima-io/nova-coordinates-postgis-fields.svg)](https://packagist.org/packages/encima-io/nova-coordinates-postgis-fields)

Encima is a web-development team based in Oslo, Norway. You'll find more information about us [on our website](https://e2consult.no).

This package is made to allow you to easily view and update your PostGIS "geography" and "geometry" fields with Laravel Nova. To use this package you should of course already be using PostgreSQL with the PostGIS extension. 

## Installation
NB! This package is designed to be used for a POINT, a single location. If you are looking for something to update lines or multiple positions, then this package is not for you.

You can install the package via composer:
```bash
composer require encima-io/nova-coordinates-postgis-fields
```

## Usage
### Migrations
You should have a column on the models table that is of the "geometry" type. This will create a "geography" type column on your table in the PostgreSQL database. This package also supports the "geometry" type.

```php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

public function up()
{
    Schema::create('locations', function (Blueprint $table) {
        // ...
        $table->string('street_address')->nullable();
        $table->geometry('position')->nullable(); // Creates a geography type column
        // ...
    });
}
```

### Resource
On the Nova resource you should use the `Encima\PostGIS\Latitude` and `Encima\PostGIS\Longitude` fields in addition to the standard `Laravel\Nova\Fields\Place` field. 

The values passed to the `latitude()` and `longitude()` methods on the `Place` field must be the same values as you pass to the second parameter in the `Latitude` and `Longitude` fields, see the example below. This is the ensures that the coordinates fetched by the Algolia API will get sent to the `Latitude` and `Longitude` fields. 

```php
use Encima\PostGIS\Latitude;
use Encima\PostGIS\Longitude;
use Laravel\Nova\Fields\Place;

Place::make('Street address', 'street_address')
    ->latitude('latitude')
    ->longitude('longitude'),
Latitude::make('Latitude', 'latitude'),
Longitude::make('Longitude', 'longitude'),
```

### Custom key-names on fields
If you however use the `latitude` and `longitude` keys for something else on you model, like an accessor or a column in your database, then you should change the keys to something thats available. When you use custom keys you need to let the fields know about their siblings field-key like this.
```php
// latitude with the alias : 'lat_property'
// longitude with the alias : 'lon_propery'
Place::make('Street address', 'street_address')
    ->latitude('lat_property')
    ->longitude('lon_propery'),

// We inform the Latitude-field about its sibling key, longitude, through
// the longitude method. The key should match the key used in the Place field
Latitude::make('Latitude', 'lat_property')
    ->longitude('lon_propery'),

// We do the same, but now for the latitude method
Longitude::make('Longitude', 'lon_propery')
    ->latitude('lat_property'),
```

### Column name
By default this package assumes your geography/geometry field is named `position`. If you are using another name for he column then you can specify this through the `fromPosition(string $column)` method. Here is an example, using the column name `location`: 

```php
Place::make('Street address', 'street_address')
    ->latitude('latitude')
    ->longitude('longitude'),
Latitude::make('Latitude', 'latitude')
    ->fromPosition('location'),
Longitude::make('Longitude', 'longitude')
    ->fromPosition('location'),
```

### Geometry 
If your position column is of the geometry type, then you need to specify this through the `dataType(string $name)` method.
```php
Latitude::make('Latitude', 'latitude')
    ->dataType('geometry'),
Longitude::make('Longitude', 'longitude')
    ->dataType('geometry'),
```
## Cache
This package uses Laravels Cache facade, `\Illuminate\Support\Facades\Cache`, to reduce the amount of queries to the database. Since the coordinates needs a little trip to the database to be converted to the right format, we cache the result by default for 5 minutes, or until they are updated from the edit page. 

For caching it uses your default cache store. You can change the cache store by with the `cacheStore(string $store)` method. You can also change the lifetime of the cache by using the `cacheFor($ttl)` method.

```php
Place::make('Street address', 'street_address')
    ->latitude('latitude')
    ->longitude('longitude'),
Latitude::make('Latitude', 'latitude')
    ->cacheStore('file')
    ->cacheFor('300 seconds'),
Longitude::make('Longitude', 'longitude')
    ->cacheStore('array')
    ->cacheFor(now()->addDay()),
```

## Example
Full example for a resource:
```php
<?php

namespace App\Nova;

use Encima\PostGIS\Latitude;
use Encima\PostGIS\Longitude;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Place;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Panel;


class UserAddress extends Resource
{

    // ...

    public function fields(Request $request)
    {
        return [
            // Other fields
            // ...
            new Panel('Address Information', [
                Place::make('Street address', 'street_address')
                    ->postalCode('postal_code')
                    ->city('postal_town')
                    ->latitude('latitude')
                    ->longitude('longitude'),
                Text::make('Postal code', 'postal_code')
                    ->sortable(),
                Text::make('Postal town', 'postal_town')
                    ->sortable(),
                Latitude::make('Latitude', 'latitude')
                    ->longitude('longitude')
                    ->fromPosition('position')
                    ->cacheFor('15 minutes'),
                Longitude::make('Longitude', 'longitude')
                    ->latitude('latitude')
                    ->fromPosition('position')
                    ->cacheFor('15 minutes'),
            ])
        ];
    }
}
```
## Disclaimer
There is some use of the `DB::raw()` method in this package. We have tried to sanitize the parameters, but you never know. Use this package at your own risk!

## License
The MIT License (MIT). Please see License File for more information.

