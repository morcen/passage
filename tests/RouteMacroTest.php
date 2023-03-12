<?php

use Illuminate\Support\Facades\Route;

it('has macro set', function () {
    $this->asserTTrue(Route::hasMacro('passage'));
});
