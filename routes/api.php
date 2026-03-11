<?php

use App\Http\Controllers\Api\PingController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultasController;
use App\Http\Controllers\Api\HandMaisController;
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
// Gets
/*Route::get('/get/consultasin100', [ConsultasController::class, 'consultasin100']);
Route::get('/get/consultashandmais', [HandMaisController::class, 'consultashandmais']);
Route::get('/get/consultasv8', [ConsultasController::class, 'consultasv8']);
Route::get('/get/consultaspresenca', [ConsultasController::class, 'consultaspresenca']);
Route::get('/get/consultasprata', [ConsultasController::class, 'consultasprata']);*/


// Post
// Route::post('/post/consultasin100', [ConsultasController::class, 'in100_online']);
// Route::post('/post/consultasv8', [ConsultasController::class, 'v8_online']);
// Route::post('/post/consultaspresenca', [ConsultasController::class, 'presenca_online']);
// Route::post('/post/consultasprata', [ConsultasController::class, 'prata_online']);

// Hand +

Route::post('/online/consultashandmais', [HandMaisController::class, 'handmais_online']);
Route::get('/start/consultashandmais', [HandMaisController::class, 'processar_fila']);
