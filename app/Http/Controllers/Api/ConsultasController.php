<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
// use Symfony\Component\HttpFoundation\Request;
use Illuminate\Http\Request;
use Throwable;

class ConsultasController extends Controller
{
    public function usuarios()
    {
        try {
            $result = DB::connection('sqlsrv')->selectOne("
            SELECT COALESCE((
                SELECT id,
                nome,
                login,
                equipe_id,
                role_id,
                ativo,
                last_login_at,
                created_at,
                updated_at
                FROM [europa4].[dbo].[users45]
                ORDER BY [id] DESC
                FOR JSON PATH
            ), '[]') AS payload
        ");

            $payload = is_object($result) ? ($result->payload ?? '[]') : '[]';

            if (! mb_check_encoding($payload, 'UTF-8')) {
                $payload = mb_convert_encoding($payload, 'UTF-8', 'Windows-1252');
            }

            $usuarios = json_decode($payload, true);

            return response()->json([
                'success' => true,
                'total' => count($usuarios ?? []),
                'data' => $usuarios ?? [],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar usuarios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function equipes()
    {
        try {
            $result = DB::connection('sqlsrv')->selectOne("
            SELECT COALESCE((
                SELECT TOP (1000) [id]
                    ,[nome]
                    ,[descricao]
                    ,[supervisor_user_id]
                    ,[ativo]
                    ,[created_at]
                    ,[updated_at]
                FROM [europa4].[dbo].[equipes45]
                ORDER BY [id] DESC
                FOR JSON PATH
            ), '[]') AS payload
        ");

            $payload = is_object($result) ? ($result->payload ?? '[]') : '[]';

            if (! mb_check_encoding($payload, 'UTF-8')) {
                $payload = mb_convert_encoding($payload, 'UTF-8', 'Windows-1252');
            }

            $equipes = json_decode($payload, true);

            return response()->json([
                'success' => true,
                'total' => count($equipes ?? []),
                'data' => $equipes ?? [],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar usuarios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function permissoes()
    {
        try {
            $sql = "
            ;WITH role_perm_agg AS (
                SELECT
                    rp.role_id,
                    rp.permission_id,
                    MAX(CAST(rp.allowed AS INT)) AS allowed
                FROM [europa4].[dbo].[role_permissions45] rp
                GROUP BY rp.role_id, rp.permission_id
            ),
            user_perm_agg AS (
                SELECT
                    up.user_id,
                    up.permission_id,
                    MAX(CAST(up.allowed AS INT)) AS allowed
                FROM [europa4].[dbo].[user_permissions45] up
                GROUP BY up.user_id, up.permission_id
            ),
            effective_permissions AS (
                SELECT
                    u.id AS user_id,
                    p.id AS permission_id,
                    p.nome,
                    p.slug,
                    p.modulo,
                    p.descricao,
                    CASE
                        WHEN up.permission_id IS NOT NULL THEN up.allowed
                        ELSE ISNULL(rp.allowed, 0)
                    END AS allowed
                FROM [europa4].[dbo].[users45] u
                CROSS JOIN [europa4].[dbo].[permissions45] p
                LEFT JOIN role_perm_agg rp
                    ON rp.role_id = u.role_id
                   AND rp.permission_id = p.id
                LEFT JOIN user_perm_agg up
                    ON up.user_id = u.id
                   AND up.permission_id = p.id
            )
            SELECT COALESCE((
                SELECT
                    u.id,
                    u.nome,
                    u.login,
                    u.email,
                    CAST(ISNULL(u.ativo, 1) AS bit) AS ativo,
                    u.created_at,
                    u.updated_at,
                    u.last_login_at,
                    u.equipe_id,
                    e.nome AS equipe_nome,
                    e.supervisor_user_id,
                    sup.nome AS supervisor_nome,
                    u.role_id,
                    r.nome AS role_nome,
                    r.slug AS role_slug,
                    r.nivel AS nivel_hierarquia,
                    COALESCE(
                        STUFF((
                            SELECT ',' + ep.slug
                            FROM effective_permissions ep
                            WHERE ep.user_id = u.id
                              AND ep.allowed = 1
                            ORDER BY ep.modulo, ep.slug
                            FOR XML PATH(''), TYPE
                        ).value('.', 'NVARCHAR(MAX)'), 1, 1, ''),
                        ''
                    ) AS permissoes,
                    JSON_QUERY(COALESCE((
                        SELECT
                            ep.permission_id AS id,
                            ep.nome,
                            ep.slug,
                            ep.modulo,
                            ep.descricao,
                            CAST(CASE WHEN ep.allowed = 1 THEN 1 ELSE 0 END AS bit) AS allowed
                        FROM effective_permissions ep
                        WHERE ep.user_id = u.id
                        ORDER BY ep.modulo, ep.slug
                        FOR JSON PATH
                    ), '[]')) AS permissoes_json
                FROM [europa4].[dbo].[users45] u
                LEFT JOIN [europa4].[dbo].[equipes45] e
                    ON e.id = u.equipe_id
                LEFT JOIN [europa4].[dbo].[users45] sup
                    ON sup.id = e.supervisor_user_id
                LEFT JOIN [europa4].[dbo].[roles45] r
                    ON r.id = u.role_id
                ORDER BY u.nome
                FOR JSON PATH
            ), '[]') AS payload
        ";

            $result = DB::connection('sqlsrv')->selectOne($sql);

            $payload = is_object($result) ? ($result->payload ?? '[]') : '[]';

            if (!mb_check_encoding($payload, 'UTF-8')) {
                $payload = mb_convert_encoding($payload, 'UTF-8', 'Windows-1252');
            }

            $permissoes = json_decode($payload, true);

            return response()->json([
                'success' => true,
                'total' => count($permissoes ?? []),
                'data' => $permissoes ?? [],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar usuários',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function hostinger()
    {
        try {
            $hostingerdb = DB::connection('sqlsrv')->select("
                SELECT
                    name,
                    database_id,
                    create_date,
                    compatibility_level,
                    state_desc
                FROM sys.databases
                ORDER BY name
            ");

            return response()->json([
                'success' => true,
                'total' => count($hostingerdb),
                'data' => $hostingerdb
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar banco de dados da conexão Hostinger',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function local()
    {
        try {
            $localdb = DB::connection('sqlsrv2')->select("
                SELECT
                    name,
                    database_id,
                    create_date,
                    compatibility_level,
                    state_desc
                FROM sys.databases
                ORDER BY name
            ");

            return response()->json([
                'success' => true,
                'total' => count($localdb),
                'data' => $localdb
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar banco de dados da conexão Planejamento Local',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function kinghost()
    {
        try {
            $kinghostdb = DB::connection('sqlsrv3')->select("
                SELECT
                    name,
                    database_id,
                    create_date,
                    compatibility_level,
                    state_desc
                FROM sys.databases
                ORDER BY name
            ");

            return response()->json([
                'success' => true,
                'total' => count($kinghostdb),
                'data' => $kinghostdb
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar banco de dados da conexão Kinghost',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handmais_login(Request $request)
    {
        try {
            $equipeId = trim((string) $request->query('equipe_id', ''));

            if ($equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: equipe_id (query string).',
                    'exemplo' => '/api/logins/consultashandmais?equipe_id=1',
                ], 400);
            }

            if (!preg_match('/^\d+$/', $equipeId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'equipe_id deve ser numerico.',
                ], 400);
            }

            $sql = "
            SELECT
                [id],
                [empresa],
                [token_api],
                [total],
                [consultados],
                [limite],
                [equipe_id],
                [created_at],
                [updated_at]
            FROM [consultas_api].[dbo].[saldo_handmais]
        ";

            $params = [];

            $sql .= " WHERE ',' + REPLACE(REPLACE(REPLACE(REPLACE([equipe_id], '{',''),'}',''),'[',''),']','') + ',' LIKE ?";
            $params[] = "%," . $equipeId . ",%";

            $sql .= " ORDER BY [id] DESC";

            $usuarios = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total'   => count($usuarios),
                'data'    => $usuarios,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar logins',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function v8_login(Request $request)
    {
        try {
            $equipeId = trim((string) $request->query('equipe_id', ''));

            if ($equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: equipe_id (query string).',
                    'exemplo' => '/api/logins/consultasv8?equipe_id=1',
                ], 400);
            }

            if (!preg_match('/^\d+$/', $equipeId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'equipe_id deve ser numerico.',
                ], 400);
            }

            $sql = "
            SELECT TOP (1000) [id]
                ,[email]
                ,[senha]
                ,[total]
                ,[consultados]
                ,[limite]
                ,[equipe_id]
                ,[created_at]
                ,[updated_at]
            FROM [consultas_api].[dbo].[saldo_v8]
        ";

            $params = [];

            $sql .= " WHERE ',' + REPLACE(REPLACE(REPLACE(REPLACE([equipe_id], '{',''),'}',''),'[',''),']','') + ',' LIKE ?";
            $params[] = "%," . $equipeId . ",%";

            $sql .= " ORDER BY [id] DESC";

            $usuarios = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total'   => count($usuarios),
                'data'    => $usuarios,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar logins',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    
}
