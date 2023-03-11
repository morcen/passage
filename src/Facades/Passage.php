<?php

namespace Morcen\Passage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Morcen\Passage\Passage
 */
class Passage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Morcen\Passage\Passage::class;
    }
}
