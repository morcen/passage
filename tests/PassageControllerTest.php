<?php

use Morcen\Passage\Http\Controllers\PassageController;

it('controller is set', function () {
    $this
        ->get(action([PassageController::class, 'index']))
        ->assertNotFound()
        ->assertSee('Route not found');
});
