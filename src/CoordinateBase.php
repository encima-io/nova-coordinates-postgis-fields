<?php

namespace Encima\PostGIS;

use DateInterval;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;

abstract class CoordinateBase extends Number
{
    /**
     * The column that holds the position.
     *
     * @var string
     */
    public $position = 'position';

    /**
     * The key that will be used to look for in the request when creating/upating.
     *
     * @var string
     */
    public $longitudeKey = 'longitude';

    /**
     * The key that will be used to look for in the request when creating/upating.
     *
     * @var string
     */
    public $latitudeKey = 'latitude';


    /**
     * The column type. Check on the DB table, not the migration, to be safe!
     *
     * @var string
     */
    public $dataType = 'geography';

    /**
     * The key that will be used to look for in the request when creating/upating.
     *
     * @var null|string
     */
    public $cacheStore = null;

    /**
     * The time-to-live in the cache
     *
     * @var \DateTimeInterface|\DateInterval|int
     */
    public $ttl = 300;

    /**
     * Specify the field that contains the longitude in the request from Place-field.
     *
     * @param  string  $field
     * @return $this
     */
    public function longitude($field)
    {
        $this->longitudeKey = $field;

        return $this;
    }

    /**
     * Specify the field that contains the latitude in the request from Place-field.
     *
     * @param  string  $field
     * @return $this
     */
    public function latitude($field)
    {
        $this->latitudeKey = $field;

        return $this;
    }

    /**
     * Specify the field that contains the PostGIS position.
     *
     * @param  string  $field
     * @return $this
     */
    public function fromPosition($field)
    {
        $this->position = $field;

        return $this;
    }

    /**
     * Specify the field that contains the PostGIS position.
     *
     * @param  string  $field
     * @return $this
     */
    public function dataType($field)
    {
        $this->dataType = $field;

        return $this;
    }

    /**
     * Specify the cache store to be used.
     *
     * @param  string  $field
     * @return $this
     */
    public function cacheStore($field)
    {
        $this->cacheStore = $field;

        return $this;
    }

    /**
     * Specify the ttl for the cache.
     *
     * @param  string|DateTimeInterface|\DateInterval|int $field
     * @return $this
     */
    public function cacheFor($field)
    {
        if (is_string($field)) {
            $this->ttl = DateInterval::createFromDateString($field);

            return $this;
        }
        $this->ttl = $field;

        return $this;
    }

    /**
     * Resolve the field's value.
     *
     * @param  mixed  $resource
     * @return string
     */
    public static function getCacheKey($resource)
    {
        return Str::lower('encima-postgis-fields.'.class_basename($resource).'.'.$resource->getRouteKey());
    }

    /**
     * Resolve the field's value.
     *
     * @param  mixed  $resource
     * @param  string|null  $attribute
     * @return void
     */
    public function resolve($resource, $attribute = null)
    {
        $this->resource = $resource;
        if ($resource->{$this->position} === null) {
            $this->value = null;

            return;
        }

        $this->value = $this->getCoordinates($resource)[$this->coordinatesIndex];
    }

    /**
     * Resolve the field's value.
     *
     * @param  mixed  $resource
     * @return null|array
     */
    public function getCoordinates($resource)
    {
        return Cache::store($this->cacheStore)
            ->remember(
                static::getCacheKey($resource),
                $this->ttl,
                function () use ($resource) {
                    if ($resource->{$this->position} === null) {
                        return null;
                    }
                    // Because I'm paranoid
                    if (!is_a($resource, Model::class)) {
                        return null;
                    }
                    $point = DB::select(DB::raw("SELECT ST_AsText('{$resource->{$this->position}}'::geography) as position"))[0]->position;

                    return explode(' ', Str::between($point, '(', ')'));
                });
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  string  $requestAttribute
     * @param  object  $model
     * @param  string  $attribute
     * @return void
     */
    protected function fillAttributeFromRequest(
        NovaRequest $request,
        $requestAttribute,
        $model,
        $attribute
    ) {
        if ($request->exists($requestAttribute)) {
            Cache::store($this->cacheStore)->forget(static::getCacheKey($model));
            $longitude = filter_var($request[$this->longitudeKey], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $latitude = filter_var($request[$this->latitudeKey], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            if ($this->dataType === 'geography') {
                $statement = $this->getGeographyStatement($longitude, $latitude);
            } elseif ($this->dataType === 'geometry') {
                $statement = $this->getGeometryStatement($longitude, $latitude);
            } else {
                return;
            }
            if ($statement !== null) {
                $model->{$this->position} = DB::raw($statement);
            }
        }
    }

    public function getGeographyStatement($longitude = null, $latitude = null)
    {
        if ($longitude === null && $latitude === null) {
            return null;
        }
        if ($longitude === null) {
            $longitude = 0;
        }
        if ($latitude === null) {
            $latitude = 0;
        }

        return "ST_GeographyFromText('POINT({$longitude} {$latitude})')";
    }

    public function getGeometryStatement($longitude = null, $latitude = null)
    {
        if ($longitude === null && $latitude === null) {
            return null;
        }
        if ($longitude === null) {
            $longitude = 0;
        }
        if ($latitude === null) {
            $latitude = 0;
        }

        return "ST_SetSRID(ST_MakePoint({$longitude}, {$latitude}), 4326)";
    }
}
