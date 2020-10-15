<?php

namespace Encima\PostGIS;

class Latitude extends CoordinateBase
{
    public $coordinatesIndex = 1;

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|null  $attribute
     * @param  mixed|null  $resolveCallback
     * @return void
     */
    public function __construct($name, $attribute = null, $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);
        $this->latitudeKey = $attribute;
        $this->withMeta(['step' => 0.000001]);
    }
}
