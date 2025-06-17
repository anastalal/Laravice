<?php

namespace AnasTalal\Laravice\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \AnasTalal\Laravice\Laravice
 */
class Laravice extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AnasTalal\Laravice\Laravice::class;
    }
}
