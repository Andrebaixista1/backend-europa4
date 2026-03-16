<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class CriacaoController extends Controller
{
    public function novo_usuarios(Request $request)
    {
        try {
            $nome = trim((string) $request->input('nome', ''));
            $login = Str::lower(trim((string) $request->input('login', '')));
            $senha = (string) $request->input('senha', '');
            $email = trim((string) $request->input('email', ''));
            $email = $email !== '' ? Str::lower($email) : null;
            $roleId = $request->input('role_id');
            $roleName = trim((string) $request->input('role', ''));
            $equipeId = $request->input('equipe_id');
            $equipeId = ($equipeId === null || $equipeId === '') ? null : (int) $equipeId;
            $ativo = filter_var(
                $request->input('ativo', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            $ativo = $ativo !== false;

            if ($nome === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo nome é obrigatório.'
                ], 422);
            }

            if ($login === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo login é obrigatório.'
                ], 422);
            }

            if ($senha === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo senha é obrigatório.'
                ], 422);
            }

            if (mb_strlen($senha) < 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'A senha deve ter pelo menos 4 caracteres.'
                ], 422);
            }

            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo email inválido.'
                ], 422);
            }

            $loginExists = DB::connection('sqlsrv')
                ->table('users45')
                ->whereRaw('LOWER(login) = ?', [$login])
                ->exists();

            if ($loginExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Já existe um usuário com esse login.'
                ], 422);
            }

            if ($equipeId !== null) {
                $equipeExists = DB::connection('sqlsrv')
                    ->table('equipes45')
                    ->where('id', $equipeId)
                    ->exists();

                if (!$equipeExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Equipe informada não foi encontrada.'
                    ], 422);
                }
            }

            $roleQuery = DB::connection('sqlsrv')->table('roles45');
            if ($roleId !== null && $roleId !== '') {
                $role = $roleQuery->where('id', (int) $roleId)->first();
            } elseif ($roleName !== '') {
                $normalizedRole = Str::lower(Str::ascii($roleName));
                $role = $roleQuery
                    ->whereRaw('LOWER(slug) = ?', [$normalizedRole])
                    ->orWhereRaw('LOWER(nome) = ?', [$normalizedRole])
                    ->first();
            } else {
                $role = $roleQuery->where('slug', 'operador')->first();
            }

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perfil informado não foi encontrado.'
                ], 422);
            }

            DB::connection('sqlsrv')->beginTransaction();

            $id = DB::connection('sqlsrv')
                ->table('users45')
                ->insertGetId([
                    'nome' => $nome,
                    'login' => $login,
                    'email' => $email,
                    'password' => Hash::make($senha),
                    'equipe_id' => $equipeId,
                    'role_id' => $role->id,
                    'ativo' => $ativo ? 1 : 0,
                    'last_login_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'remember_token' => Str::random(60),
                    'email_verified_at' => null,
                ]);

            $usuario = DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', $id)
                ->first();

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Usuário criado com sucesso.',
                'data' => [
                    'id' => $usuario->id ?? $id,
                    'nome' => $usuario->nome ?? $nome,
                    'login' => $usuario->login ?? $login,
                    'email' => $usuario->email ?? $email,
                    'equipe_id' => $usuario->equipe_id ?? $equipeId,
                    'role_id' => $usuario->role_id ?? $role->id,
                    'role' => $role->nome ?? null,
                    'ativo' => (bool) ($usuario->ativo ?? $ativo),
                    'created_at' => $usuario->created_at ?? null,
                    'updated_at' => $usuario->updated_at ?? null,
                ]
            ], 201);
        } catch (Throwable $e) {
            if (DB::connection('sqlsrv')->transactionLevel() > 0) {
                DB::connection('sqlsrv')->rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar usuário.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function novo_equipes(Request $request)
    {
        try {
            $nome = trim((string) $request->input('nome', ''));
            $descricao = trim((string) $request->input('descricao', $request->input('departamento', '')));
            $descricao = $descricao !== '' ? $descricao : null;
            $supervisorId = $request->input(
                'supervisor_user_id',
                $request->input('supervisor_id', $request->input('id_usuario'))
            );
            $supervisorId = ($supervisorId === null || $supervisorId === '') ? null : (int) $supervisorId;
            $ativo = filter_var(
                $request->input('ativo', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            $ativo = $ativo !== false;

            if ($nome === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo nome é obrigatório.'
                ], 422);
            }

            if ($supervisorId !== null) {
                $supervisorExists = DB::connection('sqlsrv')
                    ->table('users45')
                    ->where('id', $supervisorId)
                    ->exists();

                if (!$supervisorExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Supervisor informado não foi encontrado.'
                    ], 422);
                }
            }

            DB::connection('sqlsrv')->beginTransaction();

            $id = DB::connection('sqlsrv')
                ->table('equipes45')
                ->insertGetId([
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'supervisor_user_id' => $supervisorId,
                    'ativo' => $ativo ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $equipe = DB::connection('sqlsrv')
                ->table('equipes45')
                ->where('id', $id)
                ->first();

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Equipe criada com sucesso.',
                'data' => [
                    'id' => $equipe->id ?? $id,
                    'nome' => $equipe->nome ?? $nome,
                    'descricao' => $equipe->descricao ?? $descricao,
                    'supervisor_user_id' => $equipe->supervisor_user_id ?? $supervisorId,
                    'ativo' => (bool) ($equipe->ativo ?? $ativo),
                    'created_at' => $equipe->created_at ?? null,
                    'updated_at' => $equipe->updated_at ?? null,
                ]
            ], 201);
        } catch (Throwable $e) {
            if (DB::connection('sqlsrv')->transactionLevel() > 0) {
                DB::connection('sqlsrv')->rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar equipe.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function alterar_senha(Request $request)
    {
        try {
            $userId = $request->input('id');
            $senhaAtual = (string) $request->input('senha_atual', '');
            $senhaNova = (string) $request->input('senha_nova', $request->input('senha', ''));
            $confirmacao = (string) $request->input('confirmacao', '');

            if ($userId === null || $userId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo id é obrigatório.'
                ], 422);
            }

            if ($senhaAtual === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo senha_atual é obrigatório.'
                ], 422);
            }

            if ($senhaNova === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo senha_nova é obrigatório.'
                ], 422);
            }

            if (mb_strlen($senhaNova) < 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'A nova senha deve ter pelo menos 4 caracteres.'
                ], 422);
            }

            if ($confirmacao === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo confirmacao é obrigatório.'
                ], 422);
            }

            if ($senhaNova !== $confirmacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'A confirmação da senha não confere.'
                ], 422);
            }

            $usuario = DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', (int) $userId)
                ->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado.'
                ], 404);
            }

            if (!Hash::check($senhaAtual, (string) ($usuario->password ?? ''))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Senha atual inválida.'
                ], 422);
            }

            DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', (int) $userId)
                ->update([
                    'password' => Hash::make($senhaNova),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Senha atualizada com sucesso.',
                'data' => [
                    'id' => (int) $usuario->id,
                    'login' => $usuario->login ?? null,
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar senha.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function alterar_equipe(Request $request)
    {
        try {
            $userId = $request->input('id_usuario', $request->input('id'));
            $equipeId = $request->input('equipe_id');
            $equipeId = ($equipeId === null || $equipeId === '') ? null : (int) $equipeId;

            if ($userId === null || $userId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo id_usuario é obrigatório.'
                ], 422);
            }

            $usuario = DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', (int) $userId)
                ->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado.'
                ], 404);
            }

            $equipe = null;
            if ($equipeId !== null) {
                $equipe = DB::connection('sqlsrv')
                    ->table('equipes45')
                    ->where('id', $equipeId)
                    ->first();

                if (!$equipe) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Equipe informada não foi encontrada.'
                    ], 422);
                }
            }

            DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', (int) $userId)
                ->update([
                    'equipe_id' => $equipeId,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => $equipeId === null ? 'Usuário desvinculado da equipe com sucesso.' : 'Equipe do usuário alterada com sucesso.',
                'data' => [
                    'id' => (int) $usuario->id,
                    'login' => $usuario->login ?? null,
                    'equipe_id' => $equipeId,
                    'equipe_nome' => $equipe->nome ?? null,
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar equipe.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function alterar_status(Request $request)
    {
        try {
            $userId = $request->input('id_usuario', $request->input('id'));
            $ativo = $request->input('ativo', $request->input('status'));

            if ($userId === null || $userId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo id é obrigatório.'
                ], 422);
            }

            if ($ativo === null || $ativo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo ativo é obrigatório.'
                ], 422);
            }

            if (is_string($ativo)) {
                $token = Str::lower(trim($ativo));
                if (in_array($token, ['ativo', '1', 'true', 'sim', 'yes', 'on'], true)) {
                    $ativo = true;
                } elseif (in_array($token, ['inativo', '0', 'false', 'nao', 'não', 'no', 'off'], true)) {
                    $ativo = false;
                }
            }

            $ativo = filter_var($ativo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($ativo === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Valor de ativo inválido.'
                ], 422);
            }

            $usuario = DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', (int) $userId)
                ->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado.'
                ], 404);
            }

            DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', (int) $userId)
                ->update([
                    'ativo' => $ativo ? 1 : 0,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => $ativo ? 'Usuário ativado com sucesso.' : 'Usuário desativado com sucesso.',
                'data' => [
                    'id' => (int) $usuario->id,
                    'login' => $usuario->login ?? null,
                    'ativo' => $ativo,
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar status.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function alterar_usuario(Request $request)
    {
        try {
            $userId = $request->input('id_usuario', $request->input('id'));
            $nome = trim((string) $request->input('nome', ''));
            $login = Str::lower(trim((string) $request->input('login', '')));
            $roleId = $request->input('role_id');
            $roleName = trim((string) $request->input('role', $request->input('tipo', '')));
            $roleNivel = $request->input('hierarquia', $request->input('nivel'));
            $ativoInput = $request->input('ativo', $request->input('status'));

            if ($userId === null || $userId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo id e obrigatorio.'
                ], 422);
            }

            if ($nome === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo nome e obrigatorio.'
                ], 422);
            }

            if ($login === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo login e obrigatorio.'
                ], 422);
            }

            $usuario = DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', (int) $userId)
                ->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario nao encontrado.'
                ], 404);
            }

            $loginExists = DB::connection('sqlsrv')
                ->table('users45')
                ->whereRaw('LOWER(login) = ?', [$login])
                ->where('id', '<>', (int) $userId)
                ->exists();

            if ($loginExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ja existe um usuario com esse login.'
                ], 422);
            }

            $role = $this->resolverRole(
                $roleId,
                $roleName,
                $roleNivel
            );

            if (!$role) {
                $role = DB::connection('sqlsrv')
                    ->table('roles45')
                    ->where('id', (int) ($usuario->role_id ?? 0))
                    ->first();
            }

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perfil informado nao foi encontrado.'
                ], 422);
            }

            $ativo = $ativoInput;
            if ($ativo === null || $ativo === '') {
                $ativo = (bool) ($usuario->ativo ?? false);
            } else {
                $ativo = $this->normalizarAtivo($ativoInput);
            }

            if ($ativo === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Valor de ativo invalido.'
                ], 422);
            }

            DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', (int) $userId)
                ->update([
                    'nome' => $nome,
                    'login' => $login,
                    'role_id' => (int) $role->id,
                    'ativo' => $ativo ? 1 : 0,
                    'updated_at' => now(),
                ]);

            $usuarioAtualizado = DB::connection('sqlsrv')
                ->table('users45')
                ->where('id', (int) $userId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Usuario atualizado com sucesso.',
                'data' => [
                    'id' => (int) ($usuarioAtualizado->id ?? $userId),
                    'nome' => $usuarioAtualizado->nome ?? $nome,
                    'login' => $usuarioAtualizado->login ?? $login,
                    'role_id' => (int) ($usuarioAtualizado->role_id ?? $role->id),
                    'role' => $role->nome ?? null,
                    'role_nome' => $role->nome ?? null,
                    'role_slug' => $role->slug ?? null,
                    'hierarquia' => isset($role->nivel) ? (int) $role->nivel : null,
                    'ativo' => (bool) ($usuarioAtualizado->ativo ?? $ativo),
                    'status' => (($usuarioAtualizado->ativo ?? $ativo) ? 'Ativo' : 'Inativo'),
                    'updated_at' => $usuarioAtualizado->updated_at ?? null,
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function alterar_dados_equipe(Request $request)
    {
        try {
            $equipeId = $request->input('equipe_id', $request->input('id'));
            $nome = trim((string) $request->input('nome', ''));
            $descricaoInput = $request->input('descricao', $request->input('departamento'));
            $descricao = trim((string) ($descricaoInput ?? ''));
            $descricao = $descricao === '' ? null : $descricao;
            $supervisorId = $request->input(
                'supervisor_user_id',
                $request->input('supervisor_id', $request->input('id_usuario'))
            );
            $supervisorId = ($supervisorId === null || $supervisorId === '') ? null : (int) $supervisorId;
            $ativoInput = $request->input('ativo');

            if ($equipeId === null || $equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo equipe_id e obrigatorio.'
                ], 422);
            }

            $equipe = DB::connection('sqlsrv')
                ->table('equipes45')
                ->where('id', (int) $equipeId)
                ->first();

            if (!$equipe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipe nao encontrada.'
                ], 404);
            }

            if ($nome === '') {
                $nome = trim((string) ($equipe->nome ?? ''));
            }

            if ($nome === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo nome e obrigatorio.'
                ], 422);
            }

            if ($supervisorId !== null) {
                $supervisorExiste = DB::connection('sqlsrv')
                    ->table('users45')
                    ->where('id', $supervisorId)
                    ->exists();

                if (!$supervisorExiste) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Supervisor informado nao foi encontrado.'
                    ], 422);
                }
            }

            $ativo = $ativoInput;
            if ($ativo === null || $ativo === '') {
                $ativo = (bool) ($equipe->ativo ?? false);
            } else {
                $ativo = $this->normalizarAtivo($ativoInput);
            }

            if ($ativo === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Valor de ativo invalido.'
                ], 422);
            }

            DB::connection('sqlsrv')
                ->table('equipes45')
                ->where('id', (int) $equipeId)
                ->update([
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'supervisor_user_id' => $supervisorId,
                    'ativo' => $ativo ? 1 : 0,
                    'updated_at' => now(),
                ]);

            $equipeAtualizada = DB::connection('sqlsrv')
                ->table('equipes45')
                ->where('id', (int) $equipeId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Equipe atualizada com sucesso.',
                'data' => [
                    'id' => (int) ($equipeAtualizada->id ?? $equipeId),
                    'nome' => $equipeAtualizada->nome ?? $nome,
                    'descricao' => $equipeAtualizada->descricao ?? $descricao,
                    'supervisor_user_id' => $equipeAtualizada->supervisor_user_id ?? $supervisorId,
                    'ativo' => (bool) ($equipeAtualizada->ativo ?? $ativo),
                    'updated_at' => $equipeAtualizada->updated_at ?? null,
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar equipe.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function excluir_equipe(Request $request)
    {
        $connection = DB::connection('sqlsrv');

        try {
            $equipeId = $request->input('equipe_id', $request->input('id'));
            $acaoMembros = Str::lower(trim(Str::ascii((string) $request->input(
                'acao_membros',
                $request->input('membros_acao', $request->input('acao', ''))
            ))));

            if ($equipeId === null || $equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo equipe_id e obrigatorio.'
                ], 422);
            }

            $equipe = $connection
                ->table('equipes45')
                ->where('id', (int) $equipeId)
                ->first();

            if (!$equipe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipe nao encontrada.'
                ], 404);
            }

            $membros = $connection
                ->table('users45')
                ->select('id')
                ->where('equipe_id', (int) $equipeId)
                ->get();

            $memberIds = $membros->pluck('id')->map(fn ($id) => (int) $id)->all();
            $memberCount = count($memberIds);

            if ($memberCount > 0 && !in_array($acaoMembros, ['desvincular', 'excluir'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Escolha o tratamento dos membros antes de excluir a equipe.',
                    'data' => [
                        'equipe_id' => (int) $equipeId,
                        'membros_afetados' => $memberCount,
                        'acoes_permitidas' => ['desvincular', 'excluir'],
                    ]
                ], 422);
            }

            $connection->beginTransaction();

            if ($memberCount > 0 && $acaoMembros === 'desvincular') {
                $connection
                    ->table('users45')
                    ->where('equipe_id', (int) $equipeId)
                    ->update([
                        'equipe_id' => null,
                        'updated_at' => now(),
                    ]);
            }

            if ($memberCount > 0 && $acaoMembros === 'excluir') {
                $connection
                    ->table('equipes45')
                    ->whereIn('supervisor_user_id', $memberIds)
                    ->update([
                        'supervisor_user_id' => null,
                        'updated_at' => now(),
                    ]);

                $connection
                    ->table('user_permissions45')
                    ->whereIn('user_id', $memberIds)
                    ->delete();

                $connection
                    ->table('users45')
                    ->whereIn('id', $memberIds)
                    ->delete();
            }

            $connection
                ->table('equipes45')
                ->where('id', (int) $equipeId)
                ->delete();

            $connection->commit();

            return response()->json([
                'success' => true,
                'message' => 'Equipe excluida com sucesso.',
                'data' => [
                    'id' => (int) $equipeId,
                    'nome' => $equipe->nome ?? null,
                    'acao_membros' => $memberCount > 0 ? $acaoMembros : null,
                    'membros_afetados' => $memberCount,
                ]
            ]);
        } catch (Throwable $e) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir equipe.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function resolverRole($roleId = null, string $roleName = '', $roleNivel = null): mixed
    {
        if ($roleId !== null && $roleId !== '') {
            return DB::connection('sqlsrv')
                ->table('roles45')
                ->where('id', (int) $roleId)
                ->first();
        }

        if ($roleNivel !== null && $roleNivel !== '') {
            return DB::connection('sqlsrv')
                ->table('roles45')
                ->where('nivel', (int) $roleNivel)
                ->first();
        }

        if ($roleName !== '') {
            $normalizedRole = Str::lower(trim(Str::ascii($roleName)));

            return DB::connection('sqlsrv')
                ->table('roles45')
                ->whereRaw('LOWER(slug) = ?', [$normalizedRole])
                ->orWhereRaw('LOWER(nome) = ?', [$normalizedRole])
                ->first();
        }

        return null;
    }

    private function normalizarAtivo($ativo): ?bool
    {
        if (is_string($ativo)) {
            $token = Str::lower(trim(Str::ascii($ativo)));

            if (in_array($token, ['ativo', '1', 'true', 'sim', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($token, ['inativo', '0', 'false', 'nao', 'no', 'off'], true)) {
                return false;
            }
        }

        return filter_var($ativo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function excluir_usuario(Request $request)
    {
        $connection = DB::connection('sqlsrv');

        try {
            $userId = $request->input('id_usuario', $request->input('user_id', $request->input('id')));

            if ($userId === null || $userId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo id e obrigatorio.'
                ], 422);
            }

            $user = $connection
                ->table('users45')
                ->where('id', (int) $userId)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario nao encontrado.'
                ], 404);
            }

            // Evita remover o ultimo master do sistema.
            $isMaster = ((int) ($user->role_id ?? 0)) === 1;
            if ($isMaster) {
                $mastersAtivos = $connection
                    ->table('users45')
                    ->where('role_id', 1)
                    ->where('id', '<>', (int) $userId)
                    ->count();

                if ($mastersAtivos === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nao e possivel remover o ultimo usuario Master.'
                    ], 422);
                }
            }

            $connection->beginTransaction();

            // Limpa supervisao de equipes vinculadas
            $connection
                ->table('equipes45')
                ->where('supervisor_user_id', (int) $userId)
                ->update([
                    'supervisor_user_id' => null,
                    'updated_at' => now(),
                ]);

            // Remove permissoes individuais
            $connection
                ->table('user_permissions45')
                ->where('user_id', (int) $userId)
                ->delete();

            // Remove usuario
            $connection
                ->table('users45')
                ->where('id', (int) $userId)
                ->delete();

            $connection->commit();

            return response()->json([
                'success' => true,
                'message' => 'Usuario excluido com sucesso.',
                'data' => [
                    'id' => (int) $userId,
                    'login' => $user->login ?? null,
                    'role_id' => (int) ($user->role_id ?? 0),
                ]
            ]);
        } catch (Throwable $e) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handmais_cadastro(Request $request)
    {
        try {
            $empresa  = $request->input('empresa');
            $tokenApi = $request->input('token_api');
            $equipeIds = $this->normalizeApiEquipeIds($request->input('equipe_id', [1]));

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

            $id = DB::connection('sqlsrv')
                ->table('consultas_api.dbo.saldo_handmais')
                ->insertGetId([
                    'empresa'     => $empresa,
                    'token_api'   => $tokenApi,
                    'total'       => 500,
                    'consultados' => 0,
                    'limite'      => 500,
                    'equipe_id'   => $this->serializeApiEquipeIds($equipeIds),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Empresa cadastrada com sucesso.',
                'id' => $id,
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
            $equipeIds = $this->normalizeApiEquipeIds($request->input('equipe_id', [1]));

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

            $id = DB::connection('sqlsrv')
                ->table('consultas_api.dbo.saldo_v8')
                ->insertGetId([
                    'email'       => $email,
                    'senha'       => $senha,
                    'total'       => 500,
                    'consultados' => 0,
                    'limite'      => 500,
                    'equipe_id'   => $this->serializeApiEquipeIds($equipeIds),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Cadastro realizado com sucesso.',
                'id' => $id,
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

    public function presenca_cadastro(Request $request)
    {
        try {
            $login     = trim((string) $request->input('login', ''));
            $senha     = (string) $request->input('senha');
            $equipeIds = $this->normalizeApiEquipeIds($request->input('equipe_id', [1]));

            if ($login === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo login e obrigatorio.'
                ], 422);
            }

            if (!$senha) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo senha e obrigatorio.'
                ], 422);
            }

            $id = DB::connection('sqlsrv')
                ->table('consultas_api.dbo.saldo_presenca')
                ->insertGetId([
                    'login'       => $login,
                    'senha'       => $senha,
                    'total'       => 1000,
                    'consultados' => 0,
                    'limite'      => 1000,
                    'equipe_id'   => $this->serializeApiEquipeIds($equipeIds),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Cadastro Presenca realizado com sucesso.',
                'id' => $id,
                'equipe_ids' => $equipeIds
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao cadastrar Presenca.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function prata_cadastro(Request $request)
    {
        try {
            $login     = trim((string) $request->input('login', ''));
            $senha     = (string) $request->input('senha');
            $token     = trim((string) $request->input('token', ''));
            $accountId = trim((string) $request->input('account_id', ''));
            $accountToken = trim((string) $request->input('account_token', ''));
            $equipeIds = $this->normalizeApiEquipeIds($request->input('equipe_id', [1]));

            if ($login === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo login e obrigatorio.'
                ], 422);
            }

            if (!$senha) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo senha e obrigatorio.'
                ], 422);
            }

            $id = DB::connection('sqlsrv')
                ->table('consultas_api.dbo.saldo_prata')
                ->insertGetId([
                    'login'       => $login,
                    'senha'       => $senha,
                    'total'       => 1000,
                    'consultados' => 0,
                    'limite'      => 1000,
                    'equipe_id'   => $this->serializeApiEquipeIds($equipeIds),
                    'token'       => $token !== '' ? $token : null,
                    'account_id'  => $accountId !== '' ? $accountId : null,
                    'account_token' => $accountToken !== '' ? $accountToken : null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Cadastro Prata realizado com sucesso.',
                'id' => $id,
                'equipe_ids' => $equipeIds
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao cadastrar Prata.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function alterar_equipes_handmais(Request $request)
    {
        return $this->alterarEquipesApiCadastro(
            $request,
            'consultas_api.dbo.saldo_handmais',
            'Empresa Hand+ atualizada com sucesso.'
        );
    }

    public function alterar_equipes_v8(Request $request)
    {
        return $this->alterarEquipesApiCadastro(
            $request,
            'consultas_api.dbo.saldo_v8',
            'Login V8 atualizado com sucesso.'
        );
    }

    public function alterar_equipes_presenca(Request $request)
    {
        return $this->alterarEquipesApiCadastro(
            $request,
            'consultas_api.dbo.saldo_presenca',
            'Login Presenca atualizado com sucesso.'
        );
    }

    public function alterar_equipes_prata(Request $request)
    {
        return $this->alterarEquipesApiCadastro(
            $request,
            'consultas_api.dbo.saldo_prata',
            'Login Prata atualizado com sucesso.'
        );
    }

    public function excluir_handmais_cadastro(Request $request)
    {
        return $this->excluirApiCadastro(
            $request,
            'consultas_api.dbo.saldo_handmais',
            'Cadastro Hand+ excluido com sucesso.'
        );
    }

    public function excluir_v8_cadastro(Request $request)
    {
        return $this->excluirApiCadastro(
            $request,
            'consultas_api.dbo.saldo_v8',
            'Login V8 excluido com sucesso.'
        );
    }

    public function excluir_presenca_cadastro(Request $request)
    {
        return $this->excluirApiCadastro(
            $request,
            'consultas_api.dbo.saldo_presenca',
            'Login Presenca excluido com sucesso.'
        );
    }

    public function excluir_prata_cadastro(Request $request)
    {
        return $this->excluirApiCadastro(
            $request,
            'consultas_api.dbo.saldo_prata',
            'Login Prata excluido com sucesso.'
        );
    }

    private function alterarEquipesApiCadastro(Request $request, string $table, string $successMessage)
    {
        try {
            $id = (int) $request->input('id');
            $equipeIds = $this->normalizeApiEquipeIds($request->input('equipe_id', []));

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo id e obrigatorio.',
                ], 422);
            }

            $updated = DB::connection('sqlsrv')
                ->table($table)
                ->where('id', $id)
                ->update([
                    'equipe_id' => $this->serializeApiEquipeIds($equipeIds),
                    'updated_at' => now(),
                ]);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cadastro nao encontrado.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'id' => $id,
                'equipe_ids' => $equipeIds,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar equipes do cadastro.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function excluirApiCadastro(Request $request, string $table, string $successMessage)
    {
        try {
            $id = (int) $request->input('id', $request->input('cadastro_id'));

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo id e obrigatorio.',
                ], 422);
            }

            $cadastro = DB::connection('sqlsrv')
                ->table($table)
                ->where('id', $id)
                ->first();

            if (!$cadastro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cadastro nao encontrado.',
                ], 404);
            }

            DB::connection('sqlsrv')
                ->table($table)
                ->where('id', $id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'id' => $id,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir cadastro.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param mixed $rawEquipeIds
     * @return array<int, int>
     */
    private function normalizeApiEquipeIds($rawEquipeIds): array
    {
        if (!is_array($rawEquipeIds)) {
            $rawEquipeIds = [$rawEquipeIds];
        }

        $flattened = [];

        foreach ($rawEquipeIds as $value) {
            if (is_array($value)) {
                $flattened = array_merge($flattened, $value);
                continue;
            }

            $token = trim((string) $value);
            if ($token === '') {
                continue;
            }

            if (str_contains($token, ',')) {
                $flattened = array_merge($flattened, array_map('trim', explode(',', $token)));
                continue;
            }

            $flattened[] = $token;
        }

        $flattened[] = 1;

        $ids = array_map(
            static fn ($item) => (int) preg_replace('/\D+/', '', (string) $item),
            $flattened
        );

        $ids = array_values(array_unique(array_filter($ids, static fn ($item) => $item > 0)));
        sort($ids);

        return $ids;
    }

    /**
     * @param array<int, int> $equipeIds
     */
    private function serializeApiEquipeIds(array $equipeIds): string
    {
        return '{' . implode(',', $equipeIds) . '}';
    }

    public function alterar_permissoes(Request $request)
    {
            try {
                $payloadArray = [
                    'role_id' => $request->input('role_id'),
                    'permissoes_sistema_json' => $request->input('permissoes_sistema_json', []),
                    'paginas_permissoes_json' => $request->input('paginas_permissoes_json', []),
                ];

                $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if (!$payloadArray['role_id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'role_id é obrigatório.'
                    ], 422);
                }

                DB::connection('sqlsrv')->beginTransaction();

                $sql = "
                SET NOCOUNT ON;
                SET XACT_ABORT ON;

                DECLARE @payload NVARCHAR(MAX) = ?;
                DECLARE @role_id INT = TRY_CONVERT(INT, JSON_VALUE(@payload, '$.role_id'));
                DECLARE @role_slug NVARCHAR(255);
                DECLARE @role_nome NVARCHAR(255);
                DECLARE @regra_id INT;
                DECLARE @agora DATETIME2(0) = SYSDATETIME();
                DECLARE @src_paginas TABLE (
                    [pagina_key] NVARCHAR(200) NOT NULL,
                    [allow_view] bit NOT NULL,
                    [allow_consultar] bit NOT NULL,
                    [allow_criar] bit NOT NULL,
                    [allow_editar] bit NOT NULL,
                    [allow_excluir] bit NOT NULL,
                    [allow_exportar] bit NOT NULL
                );

                IF @role_id IS NULL
                    THROW 50001, 'role_id inválido ou não informado.', 1;

                SELECT
                    @role_slug = r.slug,
                    @role_nome = r.nome
                FROM [europa4].[dbo].[roles45] r
                WHERE r.id = @role_id;

                IF @role_slug IS NULL
                    THROW 50002, 'Role não encontrada.', 1;

                SELECT TOP 1
                    @regra_id = pr.id
                FROM [europa4].[dbo].[permissoes_regras] pr
                WHERE pr.ativo = 1
                  AND pr.role_alvo = @role_slug
                ORDER BY pr.prioridade DESC, pr.id DESC;

                IF @regra_id IS NULL
                BEGIN
                    INSERT INTO [europa4].[dbo].[permissoes_regras]
                    (
                        [nome_regra],
                        [escopo_tipo],
                        [role_alvo],
                        [equipe_id_alvo],
                        [usuario_id_alvo],
                        [prioridade],
                        [ativo],
                        [observacao],
                        [created_at],
                        [updated_at],
                        [created_by_user_id]
                    )
                    VALUES
                    (
                        CONCAT('HIERARQUIA_', @role_slug),
                        'hierarquia',
                        @role_slug,
                        NULL,
                        NULL,
                        100,
                        1,
                        CONCAT('Regra automática da role ', @role_nome),
                        @agora,
                        @agora,
                        NULL
                    );

                    SET @regra_id = SCOPE_IDENTITY();
                END

                ;WITH src_role_permissions AS (
                    SELECT
                        TRY_CONVERT(INT, j.[id]) AS permission_id,
                        CAST(ISNULL(j.[allowed], 0) AS bit) AS allowed
                    FROM OPENJSON(@payload, '$.permissoes_sistema_json')
                    WITH (
                        [id] INT '$.id',
                        [allowed] bit '$.allowed'
                    ) j
                    WHERE TRY_CONVERT(INT, j.[id]) IS NOT NULL
                )
                MERGE [europa4].[dbo].[role_permissions45] AS tgt
                USING src_role_permissions AS src
                    ON tgt.role_id = @role_id
                   AND tgt.permission_id = src.permission_id
                WHEN MATCHED THEN
                    UPDATE SET
                        tgt.allowed = src.allowed,
                        tgt.updated_at = @agora
                WHEN NOT MATCHED BY TARGET THEN
                    INSERT
                    (
                        [role_id],
                        [permission_id],
                        [allowed],
                        [scope],
                        [created_at],
                        [updated_at]
                    )
                    VALUES
                    (
                        @role_id,
                        src.permission_id,
                        src.allowed,
                        NULL,
                        @agora,
                        @agora
                    );

                INSERT INTO @src_paginas
                (
                    [pagina_key],
                    [allow_view],
                    [allow_consultar],
                    [allow_criar],
                    [allow_editar],
                    [allow_excluir],
                    [allow_exportar]
                )
                SELECT
                    j.[pagina_key],
                    CAST(ISNULL(j.[allow_view], 0) AS bit) AS allow_view,
                    CAST(ISNULL(j.[allow_consultar], 0) AS bit) AS allow_consultar,
                    CAST(ISNULL(j.[allow_criar], 0) AS bit) AS allow_criar,
                    CAST(ISNULL(j.[allow_editar], 0) AS bit) AS allow_editar,
                    CAST(ISNULL(j.[allow_excluir], 0) AS bit) AS allow_excluir,
                    CAST(ISNULL(j.[allow_exportar], 0) AS bit) AS allow_exportar
                FROM OPENJSON(@payload, '$.paginas_permissoes_json')
                WITH (
                    [pagina_key] NVARCHAR(200) '$.pagina_key',
                    [allow_view] bit '$.allow_view',
                    [allow_consultar] bit '$.allow_consultar',
                    [allow_criar] bit '$.allow_criar',
                    [allow_editar] bit '$.allow_editar',
                    [allow_excluir] bit '$.allow_excluir',
                    [allow_exportar] bit '$.allow_exportar'
                ) j
                WHERE j.[pagina_key] IS NOT NULL;

                MERGE [europa4].[dbo].[permissoes_regra_paginas] AS tgt
                USING @src_paginas AS src
                    ON tgt.regra_id = @regra_id
                   AND tgt.pagina_key = src.pagina_key
                WHEN MATCHED THEN
                    UPDATE SET
                        tgt.allow_view       = src.allow_view,
                        tgt.allow_consultar  = src.allow_consultar,
                        tgt.allow_criar      = src.allow_criar,
                        tgt.allow_editar     = src.allow_editar,
                        tgt.allow_excluir    = src.allow_excluir,
                        tgt.allow_exportar   = src.allow_exportar,
                        tgt.updated_at       = @agora
                WHEN NOT MATCHED BY TARGET THEN
                    INSERT
                    (
                        [regra_id],
                        [pagina_key],
                        [allow_view],
                        [allow_consultar],
                        [allow_criar],
                        [allow_editar],
                        [allow_excluir],
                        [allow_exportar],
                        [created_at],
                        [updated_at]
                    )
                    VALUES
                    (
                        @regra_id,
                        src.pagina_key,
                        src.allow_view,
                        src.allow_consultar,
                        src.allow_criar,
                        src.allow_editar,
                        src.allow_excluir,
                        src.allow_exportar,
                        @agora,
                        @agora
                    );

                SELECT
                    @role_id AS role_id,
                    @regra_id AS regra_id,
                    @role_slug AS role_slug;
            ";

                $result = DB::connection('sqlsrv')->selectOne($sql, [$payload]);

                $legacyPermissionModulesByPage = [
                    'dashboard' => 'dashboard',
                    'usuarios' => 'users',
                    'equipes' => 'equipes',
                    'permissoes' => 'config',
                    'consultas_clientes' => 'consulta_cliente',
                    'consultas_presenca' => 'consulta_presenca',
                    'consultas_v8' => 'consulta_v8',
                ];

                $disabledModules = [];
                foreach (($payloadArray['paginas_permissoes_json'] ?? []) as $pagePermission) {
                    $pageKey = trim((string) ($pagePermission['pagina_key'] ?? ''));
                    if ($pageKey === '' || !array_key_exists($pageKey, $legacyPermissionModulesByPage)) {
                        continue;
                    }

                    $allowView = filter_var(
                        $pagePermission['allow_view'] ?? false,
                        FILTER_VALIDATE_BOOLEAN,
                        FILTER_NULL_ON_FAILURE
                    );

                    if ($allowView === true) {
                        continue;
                    }

                    $disabledModules[$legacyPermissionModulesByPage[$pageKey]] = true;
                }

                if (!empty($disabledModules)) {
                    $modulePlaceholders = implode(', ', array_fill(0, count($disabledModules), '?'));
                    $bindings = array_merge([(int) $payloadArray['role_id']], array_keys($disabledModules));

                    DB::connection('sqlsrv')->update(
                        "
                        UPDATE rp
                        SET
                            rp.allowed = 0,
                            rp.updated_at = SYSDATETIME()
                        FROM [europa4].[dbo].[role_permissions45] rp
                        INNER JOIN [europa4].[dbo].[permissions45] p
                            ON p.id = rp.permission_id
                        WHERE rp.role_id = ?
                          AND p.modulo IN ($modulePlaceholders)
                          AND ISNULL(rp.allowed, 0) <> 0
                        ",
                        $bindings
                    );
                }

                DB::connection('sqlsrv')->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Permissões alteradas com sucesso.',
                    'data' => [
                        'role_id' => $result->role_id ?? null,
                        'regra_id' => $result->regra_id ?? null,
                        'role_slug' => $result->role_slug ?? null,
                    ]
                ]);
            } catch (Throwable $e) {
                DB::connection('sqlsrv')->rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao alterar permissões',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }
    }
