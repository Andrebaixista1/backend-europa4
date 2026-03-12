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
