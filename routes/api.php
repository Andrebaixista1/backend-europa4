<?php

use App\Http\Controllers\Api\PingController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultasController;
use App\Http\Controllers\Api\HandMaisController;
use App\Http\Controllers\Api\v8Controller;
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

// Validação e Gets

Route::post('/login', [AuthController::class, 'login']);
Route::get('/usuarios', [ConsultasController::class, 'usuarios']);
Route::get('/equipes', [ConsultasController::class, 'equipes']);
Route::get('/permissoes', [ConsultasController::class, 'permissoes']);

// Get para verificar bancos de dados
Route::get('/databases/hostinger', [ConsultasController::class, 'hostinger']);
Route::get('/databases/local', [ConsultasController::class, 'local']);
Route::get('/databases/kinghost', [ConsultasController::class, 'kinghost']);

// Consultas Online

// Hand +
Route::post('/online/consultashandmais', [HandMaisController::class, 'handmais_online']);
Route::get('/start/consultashandmais', [HandMaisController::class, 'processar_fila']);

// V8 
Route::post('/online/consultasv8', [v8Controller::class, 'V8_online']);
Route::get('/start/consultasv8', [v8Controller::class, 'processar_fila']);
