<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim(mb_strtolower($request->input('login')));
        $password = $request->input('password');

        try {
            $user = $this->fetchUserByLogin($login);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Nao foi possivel consultar o banco de autenticacao.',
                'details' => config('app.debug') ? $exception->getMessage() : null,
            ], 503);
        }

        if (! $user || empty($user['password_hash'])) {
            return response()->json([
                'message' => $user['mensagem'] ?? 'Login ou senha invalidos.',
            ], 401);
        }

        if (! Hash::check($password, $user['password_hash'])) {
            return response()->json([
                'message' => 'Login ou senha invalidos.',
            ], 401);
        }

        if (! $this->canLogin($user)) {
            return response()->json([
                'message' => $user['mensagem'] ?? 'Login nao autorizado.',
                'status_conta' => $user['status_conta'] ?? null,
                'user' => $this->publicUser($user),
            ], 403);
        }

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'user' => $this->publicUser($user),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUserByLogin(string $login): ?array
    {
        $result = DB::connection('sqlsrv')->selectOne(
            $this->buildLoginQuery(),
            [$login],
        );

        if ($result === null) {
            return null;
        }

        return $this->normalizeUser((array) $result);
    }

    private function buildLoginQuery(): string
    {
        return <<<'SQL'
SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;

DECLARE @login NVARCHAR(150) = LOWER(LTRIM(RTRIM(?)));

IF NULLIF(@login, N'') IS NULL
BEGIN
  SELECT
    NULL AS id,
    NULL AS id_user,
    NULL AS nome,
    NULL AS login,
    NULL AS email,
    NULL AS password_hash,
    NULL AS role_id,
    NULL AS role,
    NULL AS role_nome,
    NULL AS role_slug,
    NULL AS nivel_hierarquia,
    NULL AS equipe_id,
    NULL AS equipe_nome,
    CAST(0 AS BIT) AS is_supervisor,
    CAST(0 AS BIT) AS ativo,
    NULL AS data_ultimo_login,
    NULL AS created_at,
    NULL AS updated_at,
    N'INVALID' AS status_conta,
    CAST(0 AS BIT) AS sucesso,
    N'Login nao informado.' AS mensagem,
    N'' AS permissoes,
    N'[]' AS permissoes_json,
    N'[]' AS permissoes_matriz_json;
  RETURN;
END;

IF NOT EXISTS (
  SELECT 1
  FROM [europa4].[dbo].[users45] u
  WHERE LOWER(LTRIM(RTRIM(u.login))) = @login
     OR LOWER(LTRIM(RTRIM(ISNULL(u.email, N'')))) = @login
)
BEGIN
  SELECT
    NULL AS id,
    NULL AS id_user,
    NULL AS nome,
    @login AS login,
    NULL AS email,
    NULL AS password_hash,
    NULL AS role_id,
    NULL AS role,
    NULL AS role_nome,
    NULL AS role_slug,
    NULL AS nivel_hierarquia,
    NULL AS equipe_id,
    NULL AS equipe_nome,
    CAST(0 AS BIT) AS is_supervisor,
    CAST(0 AS BIT) AS ativo,
    NULL AS data_ultimo_login,
    NULL AS created_at,
    NULL AS updated_at,
    N'INVALID' AS status_conta,
    CAST(0 AS BIT) AS sucesso,
    N'Login ou senha invalidos.' AS mensagem,
    N'' AS permissoes,
    N'[]' AS permissoes_json,
    N'[]' AS permissoes_matriz_json;
  RETURN;
END;

;WITH base_user AS (
  SELECT TOP 1
    CAST(u.id AS INT) AS id,
    u.nome,
    u.login,
    u.email,
    u.[password] AS password_hash,
    CAST(u.equipe_id AS INT) AS equipe_id,
    e.nome AS equipe_nome,
    CAST(e.supervisor_user_id AS INT) AS supervisor_user_id,
    CAST(ISNULL(e.ativo, 1) AS BIT) AS equipe_ativa,
    CAST(u.role_id AS INT) AS role_id,
    r.nome AS role_nome,
    r.slug AS role_slug,
    CAST(r.nivel AS INT) AS nivel_hierarquia,
    CAST(u.ativo AS BIT) AS usuario_ativo,
    u.last_login_at AS data_ultimo_login,
    u.created_at,
    u.updated_at
  FROM [europa4].[dbo].[users45] u
  LEFT JOIN [europa4].[dbo].[equipes45] e
    ON e.id = u.equipe_id
  LEFT JOIN [europa4].[dbo].[roles45] r
    ON r.id = u.role_id
  WHERE LOWER(LTRIM(RTRIM(u.login))) = @login
     OR LOWER(LTRIM(RTRIM(ISNULL(u.email, N'')))) = @login
  ORDER BY u.id DESC
),
role_perm_agg AS (
  SELECT
    rp.permission_id,
    MAX(CAST(rp.allowed AS INT)) AS allowed
  FROM [europa4].[dbo].[role_permissions45] rp
  INNER JOIN base_user bu
    ON bu.role_id = rp.role_id
  GROUP BY rp.permission_id
),
user_perm_agg AS (
  SELECT
    up.permission_id,
    MAX(CAST(up.allowed AS INT)) AS allowed
  FROM [europa4].[dbo].[user_permissions45] up
  INNER JOIN base_user bu
    ON bu.id = up.user_id
  GROUP BY up.permission_id
),
effective_permissions AS (
  SELECT
    p.id,
    p.nome,
    p.slug,
    p.modulo,
    p.descricao,
    CASE
      WHEN up.permission_id IS NOT NULL THEN up.allowed
      ELSE ISNULL(rp.allowed, 0)
    END AS allowed
  FROM [europa4].[dbo].[permissions45] p
  LEFT JOIN role_perm_agg rp
    ON rp.permission_id = p.id
  LEFT JOIN user_perm_agg up
    ON up.permission_id = p.id
),
permissions_enabled AS (
  SELECT
    ep.id,
    ep.nome,
    ep.slug,
    ep.modulo,
    ep.descricao
  FROM effective_permissions ep
  WHERE ep.allowed = 1
)
SELECT
  bu.id,
  bu.id AS id_user,
  bu.nome,
  bu.login,
  bu.email,
  bu.password_hash,
  bu.role_id,
  bu.role_nome AS role,
  bu.role_nome,
  bu.role_slug,
  bu.nivel_hierarquia,
  bu.equipe_id,
  bu.equipe_nome,
  CAST(CASE WHEN bu.supervisor_user_id = bu.id THEN 1 ELSE 0 END AS BIT) AS is_supervisor,
  CAST(CASE WHEN bu.usuario_ativo = 1 AND ISNULL(bu.equipe_ativa, 1) = 1 THEN 1 ELSE 0 END AS BIT) AS ativo,
  bu.data_ultimo_login,
  bu.created_at,
  bu.updated_at,
  CASE
    WHEN bu.usuario_ativo = 0 THEN N'INACTIVE'
    WHEN ISNULL(bu.equipe_ativa, 1) = 0 THEN N'TEAM_INACTIVE'
    ELSE N'ACTIVE'
  END AS status_conta,
  CAST(CASE
    WHEN bu.usuario_ativo = 1 AND ISNULL(bu.equipe_ativa, 1) = 1 THEN 1
    ELSE 0
  END AS BIT) AS sucesso,
  CASE
    WHEN bu.usuario_ativo = 0 THEN N'Usuario inativo.'
    WHEN ISNULL(bu.equipe_ativa, 1) = 0 THEN N'Equipe inativa.'
    ELSE N'Login permitido.'
  END AS mensagem,
  COALESCE(
    STUFF((
      SELECT ',' + pe.slug
      FROM permissions_enabled pe
      ORDER BY pe.modulo, pe.slug
      FOR XML PATH(''), TYPE
    ).value('.', 'NVARCHAR(MAX)'), 1, 1, ''),
    N''
  ) AS permissoes,
  COALESCE((
    SELECT
      pe.id,
      pe.nome,
      pe.slug,
      pe.modulo,
      pe.descricao
    FROM permissions_enabled pe
    ORDER BY pe.modulo, pe.slug
    FOR JSON PATH
  ), N'[]') AS permissoes_json,
  COALESCE((
    SELECT
      ep.id,
      ep.nome,
      ep.slug,
      ep.modulo,
      ep.descricao,
      CAST(CASE WHEN ep.allowed = 1 THEN 1 ELSE 0 END AS BIT) AS allowed
    FROM effective_permissions ep
    ORDER BY ep.modulo, ep.slug
    FOR JSON PATH
  ), N'[]') AS permissoes_matriz_json
FROM base_user bu;
SQL;
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    private function normalizeUser(array $user): array
    {
        foreach ([
            'id',
            'id_user',
            'role_id',
            'nivel_hierarquia',
            'equipe_id',
        ] as $field) {
            $user[$field] = $this->normalizeNullableInt($user[$field] ?? null);
        }

        foreach (['is_supervisor', 'ativo', 'sucesso'] as $field) {
            $user[$field] = $this->normalizeBool($user[$field] ?? false);
        }

        $user['permissoes'] = (string) ($user['permissoes'] ?? '');
        $user['permissoes_json'] = $this->decodeJsonField($user['permissoes_json'] ?? '[]');
        $user['permissoes_matriz_json'] = $this->decodeJsonField($user['permissoes_matriz_json'] ?? '[]');

        return $user;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'True'], true);
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonField(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function canLogin(array $user): bool
    {
        if (! ($user['ativo'] ?? false)) {
            return false;
        }

        if (! ($user['sucesso'] ?? false)) {
            return false;
        }

        return ($user['status_conta'] ?? null) === 'ACTIVE';
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    private function publicUser(array $user): array
    {
        unset($user['password_hash']);

        return $user;
    }
}
