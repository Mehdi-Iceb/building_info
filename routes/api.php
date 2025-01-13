<?php

use App\Http\Controllers\BuildingController;
use App\Http\Controllers\BuildingInfoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('buildings/tiles/{z}/{x}/{y}', [BuildingController::class, 'getTile'])
    ->where(['z' => '[0-9]+', 'x' => '[0-9]+', 'y' => '[0-9]+']);

Route::get('/buildings/search', [BuildingController::class, 'searchBuildings']);

Route::get('/buildings/complete', [BuildingController::class, 'getAllBuildingInfo']);