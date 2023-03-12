<?php

use Illuminate\Support\Facades\Route;
use Morcen\Passage\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(function () {
        Route::passage();
    })
    ->in(__DIR__);
