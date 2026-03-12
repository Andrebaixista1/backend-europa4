<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Throwable;

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

                ;WITH pagina_role_permissions_map AS (
                    SELECT
                        v.[pagina_key],
                        v.[modulo]
                    FROM (VALUES
                        (N'dashboard', N'dashboard'),
                        (N'usuarios', N'users'),
                        (N'equipes', N'equipes'),
                        (N'permissoes', N'config'),
                        (N'consultas_clientes', N'consulta_cliente'),
                        (N'consultas_presenca', N'consulta_presenca'),
                        (N'consultas_v8', N'consulta_v8')
                    ) v ([pagina_key], [modulo])
                )
                UPDATE rp
                SET
                    rp.allowed = 0,
                    rp.updated_at = @agora
                FROM [europa4].[dbo].[role_permissions45] rp
                INNER JOIN [europa4].[dbo].[permissions45] p
                    ON p.id = rp.permission_id
                INNER JOIN pagina_role_permissions_map map
                    ON map.modulo = p.modulo
                INNER JOIN @src_paginas src
                    ON src.pagina_key = map.pagina_key
                WHERE rp.role_id = @role_id
                  AND src.allow_view = 0
                  AND ISNULL(rp.allowed, 0) <> 0;

                SELECT
                    @role_id AS role_id,
                    @regra_id AS regra_id,
                    @role_slug AS role_slug;
            ";

                $result = DB::connection('sqlsrv')->selectOne($sql, [$payload]);

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
