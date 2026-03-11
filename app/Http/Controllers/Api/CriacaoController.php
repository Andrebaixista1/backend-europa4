<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CriacaoController extends Controller
{
    public function handmais_cadastro(Request $request)
    {
        try {
            $empresa  = $request->input('empresa');
            $tokenApi = $request->input('token_api');
            $equipeIds = $request->input('equipe_id', [1]);

            if (!$empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo empresa é obrigatório.'
                ], 422);
            }

            if (!$tokenApi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo token_api é obrigatório.'
                ], 422);
            }

            if (!is_array($equipeIds)) {
                $equipeIds = [$equipeIds];
            }

            $equipeIds = array_map('intval', $equipeIds);
            $equipeIds[] = 1;
            $equipeIds = array_values(array_unique(array_filter($equipeIds, fn($v) => $v > 0)));

            $ids = [];

            foreach ($equipeIds as $equipeId) {
                $id = DB::connection('sqlsrv')
                    ->table('consultas_api.dbo.saldo_handmais')
                    ->insertGetId([
                        'empresa'     => $empresa,
                        'token_api'   => $tokenApi,
                        'total'       => 500,
                        'consultados' => 0,
                        'limite'      => 500,
                        'equipe_id'   => $equipeId,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);

                $ids[] = $id;
            }

            return response()->json([
                'success' => true,
                'message' => 'Empresa cadastrada com sucesso.',
                'ids' => $ids,
                'equipe_ids' => $equipeIds
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao cadastrar empresa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function v8_cadastro(Request $request)
    {
        try {
            $email     = $request->input('email');
            $senha     = $request->input('senha');
            $equipeIds = $request->input('equipe_id', [1]);

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo email é obrigatório.'
                ], 422);
            }

            if (!$senha) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo senha é obrigatório.'
                ], 422);
            }

            if (!is_array($equipeIds)) {
                $equipeIds = [$equipeIds];
            }

            $equipeIds = array_map('intval', $equipeIds);
            $equipeIds[] = 1;
            $equipeIds = array_values(array_unique(array_filter($equipeIds, fn($v) => $v > 0)));

            $ids = [];

            foreach ($equipeIds as $equipeId) {
                $id = DB::connection('sqlsrv')
                    ->table('consultas_api.dbo.saldo_v8')
                    ->insertGetId([
                        'email'       => $email,
                        'senha'       => $senha,
                        'total'       => 500,
                        'consultados' => 0,
                        'limite'      => 500,
                        'equipe_id'   => $equipeId,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);

                $ids[] = $id;
            }

            return response()->json([
                'success' => true,
                'message' => 'Cadastro realizado com sucesso.',
                'ids' => $ids,
                'equipe_ids' => $equipeIds
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao cadastrar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}