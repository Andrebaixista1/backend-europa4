<?php

use App\Http\Controllers\Api\PingController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultasController;
use Illuminate\Support\Facades\Route;
/*
Route::get('/ping', PingController::class);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::get('/teste', function() {
    return response()->json([
        'message' => 'Api teste'
    ]);
});

Route::post('/login', [AuthController::class, 'login']);
Route::get('/usuarios', [ConsultasController::class, 'usuarios']);
Route::get('/equipes', [ConsultasController::class, 'equipes']);