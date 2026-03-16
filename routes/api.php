<?php

use App\Http\Controllers\Api\PingController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupHealthController;
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
Route::delete('/delete/usuario', [CriacaoController::class, 'excluir_usuario']);
Route::patch('/alter/equipe-dados', [CriacaoController::class, 'alterar_dados_equipe']);
Route::patch('/delete/equipe', [CriacaoController::class, 'excluir_equipe']);
Route::get('/permissoes', [ConsultasController::class, 'permissoes']);
Route::get('/permissoes2', [ConsultasController::class, 'permissoes2']);
Route::patch('/permissoes/alterar', [CriacaoController::class, 'alterar_permissoes']);

// Get para verificar bancos de dados
Route::get('/databases/hostinger', [ConsultasController::class, 'hostinger']);
Route::get('/databases/local', [ConsultasController::class, 'local']);
Route::get('/databases/kinghost', [ConsultasController::class, 'kinghost']);
Route::get('/health-consult', [BackupHealthController::class, 'health']);
Route::post('/health-consult/force-backup', [BackupHealthController::class, 'forceBackup']);

// Consultas Online
// Logins
Route::get('/logins/consultashandmais', [ConsultasController::class, 'handmais_login']);
Route::get('/logins/consultasv8', [ConsultasController::class, 'v8_login']);
Route::get('/logins/consultaspresenca', [ConsultasController::class, 'presenca_login']);
Route::get('/logins/consultasprata', [ConsultasController::class, 'prata_login']);
Route::get('/dashboard/saldos/handmais', [ConsultasController::class, 'dashboard_saldos_handmais']);
Route::get('/dashboard/saldos/v8', [ConsultasController::class, 'dashboard_saldos_v8']);
Route::get('/dashboard/saldos/presenca', [ConsultasController::class, 'dashboard_saldos_presenca']);
Route::get('/dashboard/saldos/in100', [ConsultasController::class, 'dashboard_saldos_in100']);
Route::get('/dashboard/saldos/prata', [ConsultasController::class, 'dashboard_saldos_prata']);
Route::get('/dashboard/consultas/handmais', [ConsultasController::class, 'dashboard_consultas_handmais']);
Route::get('/dashboard/consultas/v8', [ConsultasController::class, 'dashboard_consultas_v8']);
Route::get('/dashboard/consultas/presenca', [ConsultasController::class, 'dashboard_consultas_presenca']);
Route::get('/dashboard/consultas/in100', [ConsultasController::class, 'dashboard_consultas_in100']);
Route::get('/dashboard/consultas/prata', [ConsultasController::class, 'dashboard_consultas_prata']);

// Hand +
Route::post('/online/consultashandmais', [HandMaisController::class, 'handmais_online']);
Route::get('/start/consultashandmais', [HandMaisController::class, 'processar_fila']);
Route::post('/register/consultashandmais', [CriacaoController::class, 'handmais_cadastro']);
Route::patch('/alter/consultashandmais/equipes', [CriacaoController::class, 'alterar_equipes_handmais']);
Route::patch('/delete/consultashandmais', [CriacaoController::class, 'excluir_handmais_cadastro']);
Route::delete('/delete/consultashandmais', [CriacaoController::class, 'excluir_handmais_cadastro']);

// V8 
Route::post('/register/consultasv8', [CriacaoController::class, 'v8_cadastro']);
Route::patch('/alter/consultasv8/equipes', [CriacaoController::class, 'alterar_equipes_v8']);
Route::patch('/delete/consultasv8', [CriacaoController::class, 'excluir_v8_cadastro']);
Route::delete('/delete/consultasv8', [CriacaoController::class, 'excluir_v8_cadastro']);
Route::post('/register/consultaspresenca', [CriacaoController::class, 'presenca_cadastro']);
Route::patch('/alter/consultaspresenca/equipes', [CriacaoController::class, 'alterar_equipes_presenca']);
Route::patch('/delete/consultaspresenca', [CriacaoController::class, 'excluir_presenca_cadastro']);
Route::delete('/delete/consultaspresenca', [CriacaoController::class, 'excluir_presenca_cadastro']);
Route::post('/register/consultasprata', [CriacaoController::class, 'prata_cadastro']);
Route::patch('/alter/consultasprata/equipes', [CriacaoController::class, 'alterar_equipes_prata']);
Route::patch('/delete/consultasprata', [CriacaoController::class, 'excluir_prata_cadastro']);
Route::delete('/delete/consultasprata', [CriacaoController::class, 'excluir_prata_cadastro']);
Route::post('/online/consultasv8', [v8Controller::class, 'V8_online']);
Route::get('/start/consultasv8', [v8Controller::class, 'processar_fila']);
