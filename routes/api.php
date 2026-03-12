<?php

use App\Http\Controllers\Api\PingController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultasController;
use App\Http\Controllers\Api\CriacaoController;
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

// Validação e Configs

Route::post('/login', [AuthController::class, 'login']);
Route::get('/usuarios', [ConsultasController::class, 'usuarios']);
Route::get('/equipes', [ConsultasController::class, 'equipes']);
Route::post('/register/usuarios', [CriacaoController::class, 'novo_usuarios']);
Route::post('/register/equipes', [CriacaoController::class, 'novo_equipes']);
Route::patch('/alter/passuser', [CriacaoController::class, 'alterar_senha']);
Route::patch('/alter/equipe', [CriacaoController::class, 'alterar_equipe']);
Route::patch('/alter/status-user', [CriacaoController::class, 'alterar_status']);
Route::patch('/alter/usuario', [CriacaoController::class, 'alterar_usuario']);
Route::get('/permissoes', [ConsultasController::class, 'permissoes']);
Route::get('/permissoes2', [ConsultasController::class, 'permissoes2']);
Route::patch('/permissoes/alterar', [CriacaoController::class, 'alterar_permissoes']);

// Get para verificar bancos de dados
Route::get('/databases/hostinger', [ConsultasController::class, 'hostinger']);
Route::get('/databases/local', [ConsultasController::class, 'local']);
Route::get('/databases/kinghost', [ConsultasController::class, 'kinghost']);

// Consultas Online
// Logins
Route::get('/logins/consultashandmais', [ConsultasController::class, 'handmais_login']);
Route::get('/logins/consultasv8', [ConsultasController::class, 'v8_login']);

// Hand +
Route::post('/online/consultashandmais', [HandMaisController::class, 'handmais_online']);
Route::get('/start/consultashandmais', [HandMaisController::class, 'processar_fila']);
Route::post('/register/consultashandmais', [CriacaoController::class, 'handmais_cadastro']);

// V8 
Route::post('/register/consultasv8', [CriacaoController::class, 'v8_cadastro']);
Route::post('/online/consultasv8', [v8Controller::class, 'V8_online']);
Route::get('/start/consultasv8', [v8Controller::class, 'processar_fila']);
