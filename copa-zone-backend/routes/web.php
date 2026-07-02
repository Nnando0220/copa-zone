<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'data' => [
            'name' => 'CopaZone API',
            'version' => '1.0.0',
        ],
        'meta' => [],
        'message' => 'API online.',
    ]);
});
