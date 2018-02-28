<?php

namespace Gentor\SmartUcf\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class SmartUcf
 *
 * @package Gentor\SmartUcf\Facades
 */
class SmartUcf extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'smart-ucf';
    }
}
