<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
// use Symfony\Component\HttpFoundation\Request;
use Illuminate\Http\Request;
use Throwable;

class ConsultasController extends Controller
{
    private function equipeFilterSql(string $column): string
    {
        return "',' + REPLACE(REPLACE(REPLACE(REPLACE([$column], '{',''),'}',''),'[',''),']','') + ',' LIKE ?";
    }

    private function validarEquipeId(Request $request, string $exemplo)
    {
        $equipeId = trim((string) $request->query('equipe_id', ''));
        $loadAll = filter_var($request->query('all', false), FILTER_VALIDATE_BOOL);

        if (! $loadAll && $equipeId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parametro obrigatorio: equipe_id (query string).',
                'exemplo' => $exemplo,
            ], 400);
        }

        if (! $loadAll && ! preg_match('/^\d+$/', $equipeId)) {
            return response()->json([
                'success' => false,
                'message' => 'equipe_id deve ser numerico.',
            ], 400);
        }

        return [$equipeId, $loadAll];
    }

    public function dashboard_saldos_v8(Request $request)
    {
        try {
            $validacao = $this->validarEquipeId($request, '/api/dashboard/saldos/v8?equipe_id=1');
            if ($validacao instanceof \Illuminate\Http\JsonResponse) {
                return $validacao;
            }

            [$equipeId, $loadAll] = $validacao;

            $sql = "
                SELECT TOP (1000) [id]
                    ,[email]
                    ,[senha]
                    ,[total]
                    ,[consultados]
                    ,[limite]
                    ,[created_at]
                    ,[updated_at]
                    ,[equipe_id]
                FROM [consultas_api].[dbo].[saldo_v8]
            ";

            $params = [];

            if (! $loadAll) {
                $sql .= " WHERE " . $this->equipeFilterSql('equipe_id');
                $params[] = "%," . $equipeId . ",%";
            }

            $sql .= " ORDER BY [id] DESC";
            $saldos = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($saldos),
                'data' => $saldos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar saldos V8',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_saldos_handmais(Request $request)
    {
        try {
            $validacao = $this->validarEquipeId($request, '/api/dashboard/saldos/handmais?equipe_id=1');
            if ($validacao instanceof \Illuminate\Http\JsonResponse) {
                return $validacao;
            }

            [$equipeId, $loadAll] = $validacao;

            $sql = "
                SELECT TOP (1000) [id]
                    ,[empresa]
                    ,[token_api]
                    ,[total]
                    ,[consultados]
                    ,[limite]
                    ,[equipe_id]
                    ,[created_at]
                    ,[updated_at]
                FROM [consultas_api].[dbo].[saldo_handmais]
            ";

            $params = [];

            if (! $loadAll) {
                $sql .= " WHERE " . $this->equipeFilterSql('equipe_id');
                $params[] = "%," . $equipeId . ",%";
            }

            $sql .= " ORDER BY [id] DESC";
            $saldos = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($saldos),
                'data' => $saldos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar saldos HandMais',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_consultas_handmais(Request $request)
    {
        try {
            $validacao = $this->validarEquipeId($request, '/api/dashboard/consultas/handmais?equipe_id=1');
            if ($validacao instanceof \Illuminate\Http\JsonResponse) {
                return $validacao;
            }

            [$equipeId, $loadAll] = $validacao;

            $sql = "
                SELECT TOP (1000) [id]
                    ,[nome]
                    ,[cpf]
                    ,[telefone]
                    ,[dataNascimento]
                    ,[status]
                    ,[tipoConsulta]
                    ,[descricao]
                    ,[nome_tabela]
                    ,[valor_margem]
                    ,[id_tabela]
                    ,[token_tabela]
                    ,[id_user]
                    ,[equipe_id]
                    ,[id_consulta_hand]
                    ,[created_at]
                    ,[updated_at]
                FROM [consultas_api].[dbo].[consulta_handmais]
            ";

            $params = [];

            if (! $loadAll) {
                $sql .= " WHERE [equipe_id] = ?";
                $params[] = (int) $equipeId;
            }

            $sql .= " ORDER BY [id] DESC";
            $consultas = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($consultas),
                'data' => $consultas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar consultas HandMais',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_consultas_v8(Request $request)
    {
        try {
            $validacao = $this->validarEquipeId($request, '/api/dashboard/consultas/v8?equipe_id=1');
            if ($validacao instanceof \Illuminate\Http\JsonResponse) {
                return $validacao;
            }

            [$equipeId, $loadAll] = $validacao;

            $sql = "
                SELECT TOP (1000) [cliente_cpf]
                    ,[cliente_sexo]
                    ,[nascimento]
                    ,[cliente_nome]
                    ,[email]
                    ,[telefone]
                    ,[created_at]
                    ,[status]
                    ,[status_consulta_v8]
                    ,[valor_liberado]
                    ,[descricao_v8]
                    ,[id_user]
                    ,[id_equipe]
                    ,[id_roles]
                    ,[id]
                    ,[tipoConsulta]
                FROM [consultas_api].[dbo].[consulta_v8]
            ";

            $params = [];

            if (! $loadAll) {
                $sql .= " WHERE [id_equipe] = ?";
                $params[] = (int) $equipeId;
            }

            $sql .= " ORDER BY [id] DESC";
            $consultas = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($consultas),
                'data' => $consultas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar consultas V8',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_saldos_presenca(Request $request)
    {
        try {
            $equipeId = trim((string) $request->query('equipe_id', ''));
            $loadAll = filter_var($request->query('all', false), FILTER_VALIDATE_BOOL);

            $sql = "
                SELECT TOP (1000) [id]
                    ,[login]
                    ,[senha]
                    ,[total]
                    ,[consultados]
                    ,[limite]
                    ,[equipe_id]
                    ,[created_at]
                    ,[updated_at]
                FROM [consultas_api].[dbo].[saldo_presenca]
            ";

            $params = [];

            if (!$loadAll && $equipeId !== '' && preg_match('/^\d+$/', $equipeId)) {
                $sql .= " WHERE " . $this->equipeFilterSql('equipe_id');
                $params[] = "%," . $equipeId . ",%";
            }

            $sql .= " ORDER BY [id] DESC";
            $saldos = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($saldos),
                'data' => $saldos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar saldos Presenca',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_consultas_presenca(Request $request)
    {
        try {
            $validacao = $this->validarEquipeId($request, '/api/dashboard/consultas/presenca?equipe_id=1');
            if ($validacao instanceof \Illuminate\Http\JsonResponse) {
                return $validacao;
            }

            [$equipeId, $loadAll] = $validacao;

            $sql = "
                SELECT TOP (1000) [id]
                    ,[cpf]
                    ,[nome]
                    ,[telefone]
                    ,[matricula]
                    ,[numeroInscricaoEmpregador]
                    ,[elegivel]
                    ,[valorMargemDisponivel]
                    ,[valorMargemBase]
                    ,[valorTotalDevido]
                    ,[dataAdmissao]
                    ,[dataNascimento]
                    ,[nomeMae]
                    ,[sexo]
                    ,[nomeTipo]
                    ,[prazo]
                    ,[taxaJuros]
                    ,[valorLiberado]
                    ,[valorParcela]
                    ,[taxaSeguro]
                    ,[valorSeguro]
                    ,[tipoConsulta]
                    ,[status]
                    ,[mensagem]
                    ,[id_user]
                    ,[equipe_id]
                    ,[id_consulta_presenca]
                    ,[created_at]
                    ,[updated_at]
                FROM [consultas_api].[dbo].[consulta_presenca]
            ";

            $params = [];

            if (! $loadAll) {
                $sql .= " WHERE [equipe_id] = ?";
                $params[] = (int) $equipeId;
            }

            $sql .= " ORDER BY [created_at] DESC, [id] DESC";
            $consultas = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($consultas),
                'data' => $consultas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar consultas Presenca',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_saldos_in100(Request $request)
    {
        try {
            $validacao = $this->validarEquipeId($request, '/api/dashboard/saldos/in100?equipe_id=1');
            if ($validacao instanceof \Illuminate\Http\JsonResponse) {
                return $validacao;
            }

            [$equipeId, $loadAll] = $validacao;

            $sql = "
                SELECT TOP (1000)
                    [id] AS [saldo_id],
                    [total] AS [total_carregado],
                    [limite] AS [limite_disponivel],
                    [consultados] AS [consultas_realizada],
                    [created_at] AS [data_saldo_carregado],
                    [equipe_id],
                    [equipe_nome],
                    [total],
                    [consultados],
                    [limite],
                    [created_at],
                    [updated_at]
                FROM [consultas_api].[dbo].[saldo_in100]
            ";

            $params = [];
            if (! $loadAll) {
                $sql .= " WHERE " . $this->equipeFilterSql('equipe_id');
                $params[] = "%," . $equipeId . ",%";
            }

            $sql .= " ORDER BY [id] DESC";
            $saldos = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($saldos),
                'data' => $saldos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar saldos IN100',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function recarga_in100(Request $request)
    {
        try {
            $equipeId = trim((string) $request->input('equipeId', $request->input('equipe_id', '')));
            $equipeNome = trim((string) $request->input('equipeNome', $request->input('equipe_nome', '')));
            $quantidadeRaw = $request->input('quantidade', $request->input('total', null));

            if ($equipeId === '' || !preg_match('/^\d+$/', $equipeId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'equipeId deve ser numerico.',
                ], 400);
            }

            if ($equipeNome === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'equipeNome obrigatorio.',
                ], 400);
            }

            $quantidade = is_numeric($quantidadeRaw) ? (int) $quantidadeRaw : 0;
            if ($quantidade <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'quantidade deve ser maior que zero.',
                ], 400);
            }

            $now = now()->format('Y-m-d H:i:s');

            DB::connection('sqlsrv')->insert("
                INSERT INTO [consultas_api].[dbo].[saldo_in100]
                    ([equipe_nome], [total], [consultados], [limite], [equipe_id], [created_at])
                VALUES (?, ?, ?, ?, ?, ?)
            ", [
                $equipeNome,
                $quantidade,
                0,
                $quantidade,
                $equipeId,
                $now,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recarga registrada com sucesso.',
                'data' => [
                    'equipe_id' => $equipeId,
                    'equipe_nome' => $equipeNome,
                    'total' => $quantidade,
                    'consultados' => 0,
                    'limite' => $quantidade,
                    'created_at' => $now,
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar recarga IN100',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_consultas_in100(Request $request)
    {
        try {
            $equipeId = trim((string) $request->query('equipe_id', ''));
            $idUser = (int) $request->query('id_user', 0);
            $role = strtolower(trim((string) $request->query('role', $request->query('hierarquia', ''))));
            $roleId = (int) $request->query('role_id', $request->query('id_role', 0));
            $nivelHierarquia = (int) $request->query('nivel_hierarquia', 0);

            $isMaster = $role === 'master' || $roleId === 1 || $nivelHierarquia >= 100;
            $isOperador = $role === 'operador' || $nivelHierarquia === 10;
            $loadAll = $isMaster;

            if ($isOperador && $idUser <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio para Operador: id_user (query string).',
                    'exemplo' => '/api/dashboard/consultas/in100?id_user=123',
                ], 400);
            }

            if (! $loadAll && ! $isOperador && $equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: equipe_id (query string).',
                    'exemplo' => '/api/dashboard/consultas/in100?equipe_id=1',
                ], 400);
            }

            $sql = "
                SELECT TOP (10000)
                    [id],
                    [numero_beneficio],
                    [numero_documento],
                    [nome],
                    [estado],
                    [pensao],
                    [data_nascimento],
                    [tipo_bloqueio],
                    [data_concessao],
                    [tipo_credito],
                    [limite_cartao_beneficio],
                    [saldo_cartao_beneficio],
                    [situacao_beneficio],
                    [data_final_beneficio],
                    [limite_cartao_consignado],
                    [saldo_cartao_consignado],
                    [saldo_credito_consignado],
                    [saldo_total_maximo],
                    [saldo_total_utilizado],
                    [saldo_total_disponivel],
                    [data_consulta],
                    [data_retorno_consulta],
                    [hora_retorno_consulta],
                    [nome_representante_legal],
                    [banco_desembolso],
                    [agencia_desembolso],
                    [conta_desembolso],
                    [digito_desembolso],
                    [numero_portabilidades],
                    [id_usuario],
                    [data_hora_registro],
                    [nome_arquivo],
                    [resposta_api],
                    [status_api],
                    [equipe_id]
                FROM [consultas_api].[dbo].[consultas_in100]
            ";

            $params = [];
            if (! $loadAll) {
                if ($isOperador) {
                    $sql .= " WHERE [id_usuario] = ?";
                    $params[] = $idUser;
                } else {
                    $sql .= " WHERE " . $this->equipeFilterSql('equipe_id');
                    $params[] = "%," . $equipeId . ",%";
                }
            }

            $sql .= " ORDER BY [id] DESC";
            $consultas = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($consultas),
                'data' => $consultas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar consultas IN100',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_fila_in100(Request $request)
    {
        try {
            $validacao = $this->validarEquipeId($request, '/api/dashboard/fila/in100?equipe_id=1');
            if ($validacao instanceof \Illuminate\Http\JsonResponse) {
                return $validacao;
            }

            [$equipeId, $loadAll] = $validacao;

            $sql = "
                SELECT TOP (1000)
                    [id],
                    [cpf],
                    [id_consulta] AS [numero_beneficio],
                    [id_user],
                    [equipe_id],
                    [created_at],
                    [updated_at]
                FROM [consultas_api].[dbo].[filaconsulta_in100]
            ";

            $params = [];
            if (! $loadAll) {
                $sql .= " WHERE [equipe_id] = ?";
                $params[] = (int) $equipeId;
            }

            $sql .= " ORDER BY [id] DESC";
            $fila = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($fila),
                'data' => $fila,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar fila IN100',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function consulta_online_fila(Request $request)
    {
        try {
            $equipeId = trim((string) $request->query('equipe_id', ''));
            $idUser = (int) $request->query('id_user', 0);
            $hierarquia = strtolower(trim((string) $request->query('hierarquia', $request->query('role', ''))));

            $isMaster = $hierarquia === 'master';
            $isAdmOuSuper = in_array($hierarquia, ['adm', 'super', 'administrador', 'supervisor'], true);
            $isOperador = in_array($hierarquia, ['oper', 'operador'], true);

            if ($isOperador && $idUser <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio para Operador: id_user (query string).',
                    'exemplo' => '/api/consultaonline/fila?id_user=123&hierarquia=oper',
                ], 400);
            }

            if (! $isMaster && ! $isOperador && $equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: equipe_id (query string).',
                    'exemplo' => '/api/consultaonline/fila?equipe_id=1&hierarquia=adm',
                ], 400);
            }

            $sql = "
                SELECT TOP (1000)
                    [id],
                    [cpf],
                    [nb],
                    [nome],
                    [telefone],
                    [dt_nascimento],
                    [consultas_ativas],
                    [status],
                    [id_consulta],
                    [id_user],
                    [equipe_id],
                    [created_at],
                    [updated_at]
                FROM [consultas_api].[dbo].[filaconsultas]
            ";

            $params = [];

            if (! $isMaster) {
                if ($isOperador) {
                    $sql .= " WHERE [id_user] = ?";
                    $params[] = $idUser;
                } elseif ($isAdmOuSuper) {
                    $sql .= " WHERE [equipe_id] = ?";
                    $params[] = (int) $equipeId;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hierarquia invalida. Use master, adm, super ou oper.',
                    ], 400);
                }
            }

            $sql .= " ORDER BY [id] DESC";
            $fila = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($fila),
                'data' => $fila,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar fila da consulta online',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_saldos_prata(Request $request)
    {
        try {
            $validacao = $this->validarEquipeId($request, '/api/dashboard/saldos/prata?equipe_id=1');
            if ($validacao instanceof \Illuminate\Http\JsonResponse) {
                return $validacao;
            }

            [$equipeId, $loadAll] = $validacao;

            $sql = "
                SELECT TOP (1000)
                    [id],
                    [login],
                    [senha],
                    [total],
                    [consultados],
                    [limite],
                    [equipe_id],
                    [token],
                    [account_id],
                    [account_token],
                    [created_at],
                    [updated_at]
                FROM [consultas_api].[dbo].[saldo_prata]
            ";

            $params = [];
            if (! $loadAll) {
                $sql .= " WHERE [equipe_id] = ?";
                $params[] = (int) $equipeId;
            }

            $sql .= " ORDER BY [consultados] DESC, [id] DESC";
            $saldos = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($saldos),
                'data' => $saldos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar saldos Prata',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard_consultas_prata(Request $request)
    {
        try {
            $validacao = $this->validarEquipeId($request, '/api/dashboard/consultas/prata?equipe_id=1');
            if ($validacao instanceof \Illuminate\Http\JsonResponse) {
                return $validacao;
            }

            [$equipeId, $loadAll] = $validacao;

            $sql = "
                SELECT TOP (1000)
                    [id],
                    [cpf],
                    [nome],
                    [sexo],
                    [elegivel],
                    [dt_nascimento],
                    [tipo_bloqueio],
                    [blocked_at],
                    [nome_mae],
                    [expira_consulta],
                    [margem_base],
                    [motivo_inelegibilidade],
                    [qtd_contratos_suspensos],
                    [margem_disponivel],
                    [status_consulta],
                    [margem_total_disponivel],
                    [saldo_total_disp_6_parcelas],
                    [valor_emissao_6_parcelas],
                    [saldo_total_disp_12_parcelas],
                    [valor_emissao_12_parcelas],
                    [saldo_total_disp_24_parcelas],
                    [valor_emissao_24_parcelas],
                    [status_consulta] AS [status_consulta_prata],
                    [created_at],
                    [updated_at],
                    [id_user],
                    [equipe_id],
                    [id_consulta_prata]
                FROM [consultas_api].[dbo].[consulta_prata]
            ";

            $params = [];
            if (! $loadAll) {
                $sql .= " WHERE [equipe_id] = ?";
                $params[] = (int) $equipeId;
            }

            $sql .= " ORDER BY [created_at] DESC, [id] DESC";
            $consultas = DB::connection('sqlsrv')->select($sql, $params);

            return response()->json([
                'success' => true,
                'total' => count($consultas),
                'data' => $consultas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar consultas Prata',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function prata_login(Request $request)
    {
        try {
            $equipeId = trim((string) $request->query('equipe_id', ''));
            $loadAll = filter_var($request->query('all', false), FILTER_VALIDATE_BOOL);

            if (!$loadAll && $equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: equipe_id (query string).',
                    'exemplo' => '/api/logins/consultasprata?equipe_id=1',
                ], 400);
            }

            if (!$loadAll && !preg_match('/^\d+$/', $equipeId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'equipe_id deve ser numerico.',
                ], 400);
            }

            $sql = "
            SELECT TOP (1000) [id]
                ,[login]
                ,[senha]
                ,[total]
                ,[consultados]
                ,[limite]
                ,[equipe_id]
                ,[token]
                ,[account_id]
                ,[account_token]
                ,[created_at]
                ,[updated_at]
            FROM [consultas_api].[dbo].[saldo_prata]
        ";

            $params = [];

            if (!$loadAll) {
                $sql .= " WHERE [equipe_id] = ?";
                $params[] = (int) $equipeId;
            }

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

    public function permissoes2()
    {
        try {
            $sql = "
            DECLARE @sql NVARCHAR(MAX) = N'
            ;WITH role_perm_agg AS (
                SELECT
                    rp.role_id,
                    rp.permission_id,
                    MAX(CAST(rp.allowed AS INT)) AS allowed
                FROM [europa4].[dbo].[role_permissions45] rp
                GROUP BY rp.role_id, rp.permission_id
            ),
            role_permissions_json AS (
                SELECT
                    r.id AS role_id,
                    JSON_QUERY(COALESCE((
                        SELECT
                            p.id,
                            p.nome,
                            p.slug,
                            p.modulo,
                            p.descricao,
                            CAST(ISNULL(rpa.allowed, 0) AS bit) AS allowed
                        FROM [europa4].[dbo].[permissions45] p
                        LEFT JOIN role_perm_agg rpa
                            ON rpa.role_id = r.id
                        AND rpa.permission_id = p.id
                        ORDER BY p.modulo, p.slug
                        FOR JSON PATH
                    ), ''[]'')) AS permissoes_sistema_json,
                    COALESCE((
                        SELECT STUFF((
                            SELECT '','' + p.slug
                            FROM [europa4].[dbo].[permissions45] p
                            LEFT JOIN role_perm_agg rpa
                                ON rpa.role_id = r.id
                            AND rpa.permission_id = p.id
                            WHERE ISNULL(rpa.allowed, 0) = 1
                            ORDER BY p.modulo, p.slug
                            FOR XML PATH(''''), TYPE
                        ).value(''.'', ''NVARCHAR(MAX)''), 1, 1, '''')
                    ), '''') AS permissoes_sistema
                FROM [europa4].[dbo].[roles45] r
            ),
            regras_role_paginas AS (
                SELECT
                    r.id AS role_id,
                    c.pagina_key,
                    c.pagina_nome,
                    c.rota,
                    MAX(CAST(ISNULL(rp.allow_view, 0) AS INT))       AS allow_view,
                    MAX(CAST(ISNULL(rp.allow_consultar, 0) AS INT))  AS allow_consultar,
                    MAX(CAST(ISNULL(rp.allow_criar, 0) AS INT))      AS allow_criar,
                    MAX(CAST(ISNULL(rp.allow_editar, 0) AS INT))     AS allow_editar,
                    MAX(CAST(ISNULL(rp.allow_excluir, 0) AS INT))    AS allow_excluir,
                    MAX(CAST(ISNULL(rp.allow_exportar, 0) AS INT))   AS allow_exportar
                FROM [europa4].[dbo].[roles45] r
                INNER JOIN [europa4].[dbo].[permissoes_regras] pr
                    ON pr.ativo = 1
                AND (
                        pr.role_alvo = r.slug
                        -- Se role_alvo guardar o ID da role, troque por:
                        -- OR TRY_CONVERT(INT, pr.role_alvo) = r.id

                        -- Se role_alvo guardar o NOME da role, troque por:
                        -- OR pr.role_alvo = r.nome
                )
                INNER JOIN [europa4].[dbo].[permissoes_regra_paginas] rp
                    ON rp.regra_id = pr.id
                INNER JOIN [europa4].[dbo].[permissoes_paginas_catalogo] c
                    ON c.pagina_key = rp.pagina_key
                AND c.ativo = 1
                GROUP BY
                    r.id,
                    c.pagina_key,
                    c.pagina_nome,
                    c.rota
            ),
            role_pages_json AS (
                SELECT
                    r.id AS role_id,
                    JSON_QUERY(COALESCE((
                        SELECT
                            c.pagina_key,
                            c.pagina_nome,
                            c.rota,
                            CAST(
                                CASE
                                    WHEN ISNULL(rrp.allow_view, 0) = 1 THEN 1
                                    ELSE 0
                                END
                            AS bit) AS allow_view,
                            CAST(
                                CASE
                                    WHEN c.suporta_consultar = 1 AND ISNULL(rrp.allow_consultar, 0) = 1 THEN 1
                                    ELSE 0
                                END
                            AS bit) AS allow_consultar,
                            CAST(
                                CASE
                                    WHEN c.suporta_criar = 1 AND ISNULL(rrp.allow_criar, 0) = 1 THEN 1
                                    ELSE 0
                                END
                            AS bit) AS allow_criar,
                            CAST(
                                CASE
                                    WHEN c.suporta_editar = 1 AND ISNULL(rrp.allow_editar, 0) = 1 THEN 1
                                    ELSE 0
                                END
                            AS bit) AS allow_editar,
                            CAST(
                                CASE
                                    WHEN c.suporta_excluir = 1 AND ISNULL(rrp.allow_excluir, 0) = 1 THEN 1
                                    ELSE 0
                                END
                            AS bit) AS allow_excluir,
                            CAST(
                                CASE
                                    WHEN c.suporta_exportar = 1 AND ISNULL(rrp.allow_exportar, 0) = 1 THEN 1
                                    ELSE 0
                                END
                            AS bit) AS allow_exportar
                        FROM [europa4].[dbo].[permissoes_paginas_catalogo] c
                        LEFT JOIN regras_role_paginas rrp
                            ON rrp.role_id = r.id
                        AND rrp.pagina_key = c.pagina_key
                        WHERE c.ativo = 1
                        ORDER BY c.pagina_nome
                        FOR JSON PATH
                    ), ''[]'')) AS paginas_permissoes_json
                FROM [europa4].[dbo].[roles45] r
            )
            SELECT COALESCE((
                SELECT
                    r.id,
                    r.nome,
                    r.slug,
                    r.nivel,
                    CAST(ISNULL(r.is_system, 0) AS bit) AS is_system,
                    r.created_at,
                    r.updated_at,
                    ISNULL(rps.permissoes_sistema, '''') AS permissoes_sistema,
                    JSON_QUERY(ISNULL(rps.permissoes_sistema_json, ''[]'')) AS permissoes_sistema_json,
                    JSON_QUERY(ISNULL(rpj.paginas_permissoes_json, ''[]'')) AS paginas_permissoes_json
                FROM [europa4].[dbo].[roles45] r
                LEFT JOIN role_permissions_json rps
                    ON rps.role_id = r.id
                LEFT JOIN role_pages_json rpj
                    ON rpj.role_id = r.id
                ORDER BY r.nivel, r.nome
                FOR JSON PATH
            ), ''[]'') AS payload;
            ';

            EXEC sp_executesql @sql;
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
            $loadAll = filter_var($request->query('all', false), FILTER_VALIDATE_BOOL);

            if (!$loadAll && $equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: equipe_id (query string).',
                    'exemplo' => '/api/logins/consultashandmais?equipe_id=1',
                ], 400);
            }

            if (!$loadAll && !preg_match('/^\d+$/', $equipeId)) {
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

            if (!$loadAll) {
                $sql .= " WHERE ',' + REPLACE(REPLACE(REPLACE(REPLACE([equipe_id], '{',''),'}',''),'[',''),']','') + ',' LIKE ?";
                $params[] = "%," . $equipeId . ",%";
            }

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

    public function in100_login(Request $request)
    {
        try {
            $equipeId = trim((string) $request->query('equipe_id', ''));
            $loadAll = filter_var($request->query('all', false), FILTER_VALIDATE_BOOL);

            if (!$loadAll && $equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: equipe_id (query string).',
                    'exemplo' => '/api/logins/consultasin100?equipe_id=1',
                ], 400);
            }

            if (!$loadAll && !preg_match('/^\d+$/', $equipeId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'equipe_id deve ser numerico.',
                ], 400);
            }

            $sql = "
            SELECT TOP (1000) [id]
                ,[equipe_nome]
                ,[total]
                ,[consultados]
                ,[limite]
                ,[equipe_id]
                ,[created_at]
                ,[updated_at]
            FROM [consultas_api].[dbo].[saldo_in100]
        ";

            $params = [];

            if (!$loadAll) {
                $sql .= " WHERE ',' + REPLACE(REPLACE(REPLACE(REPLACE([equipe_id], '{',''),'}',''),'[',''),']','') + ',' LIKE ?";
                $params[] = "%," . $equipeId . ",%";
            }

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
                'message' => 'Erro ao buscar logins IN100',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function v8_login(Request $request)
    {
        try {
            $equipeId = trim((string) $request->query('equipe_id', ''));
            $loadAll = filter_var($request->query('all', false), FILTER_VALIDATE_BOOL);

            if (!$loadAll && $equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: equipe_id (query string).',
                    'exemplo' => '/api/logins/consultasv8?equipe_id=1',
                ], 400);
            }

            if (!$loadAll && !preg_match('/^\d+$/', $equipeId)) {
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

            if (!$loadAll) {
                $sql .= " WHERE ',' + REPLACE(REPLACE(REPLACE(REPLACE([equipe_id], '{',''),'}',''),'[',''),']','') + ',' LIKE ?";
                $params[] = "%," . $equipeId . ",%";
            }

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

    public function presenca_login(Request $request)
    {
        try {
            $equipeId = trim((string) $request->query('equipe_id', ''));
            $loadAll = filter_var($request->query('all', false), FILTER_VALIDATE_BOOL);

            if (!$loadAll && $equipeId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: equipe_id (query string).',
                    'exemplo' => '/api/logins/consultaspresenca?equipe_id=1',
                ], 400);
            }

            if (!$loadAll && !preg_match('/^\d+$/', $equipeId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'equipe_id deve ser numerico.',
                ], 400);
            }

            $sql = "
            SELECT TOP (1000) [id]
                ,[login]
                ,[senha]
                ,[total]
                ,[consultados]
                ,[limite]
                ,[equipe_id]
                ,[created_at]
                ,[updated_at]
            FROM [consultas_api].[dbo].[saldo_presenca]
        ";

            $params = [];

            if (!$loadAll) {
                $sql .= " WHERE ',' + REPLACE(REPLACE(REPLACE(REPLACE([equipe_id], '{',''),'}',''),'[',''),']','') + ',' LIKE ?";
                $params[] = "%," . $equipeId . ",%";
            }

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

    public function vanguard_list()
    {
        try {
            $rows = DB::connection('sqlsrv')->select("
                SELECT TOP (1000)
                    [id],
                    [codigo],
                    [empresa],
                    [login],
                    [nome],
                    [cargo],
                    [data_cadastro],
                    [renovacao],
                    [status],
                    [vencimento],
                    [grupo]
                FROM [Inbis].[dbo].[usuarios_vanguard]
                ORDER BY [id] DESC
            ");

            return response()->json($rows);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar Vanguard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function vanguard_add(Request $request)
    {
        try {
            $payload = $request->all();
            $codigo = trim((string) ($payload['codigo'] ?? ''));
            $empresa = trim((string) ($payload['empresa'] ?? ''));
            $login = trim((string) ($payload['login'] ?? ''));
            $nome = trim((string) ($payload['nome'] ?? ''));
            $cargo = trim((string) ($payload['cargo'] ?? ''));
            $dataCadastro = trim((string) ($payload['data_cadastro'] ?? ''));
            $renovacao = trim((string) ($payload['renovacao'] ?? $dataCadastro));
            $status = trim((string) ($payload['status'] ?? 'Ativo'));
            $vencimento = trim((string) ($payload['vencimento'] ?? ''));
            $grupo = trim((string) ($payload['grupo'] ?? ''));

            if ($codigo === '' || $empresa === '' || $login === '' || $cargo === '' || $dataCadastro === '' || $grupo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campos obrigatorios: codigo, empresa, login, cargo, data_cadastro, grupo.',
                ], 400);
            }

            DB::connection('sqlsrv')->insert("
                INSERT INTO [Inbis].[dbo].[usuarios_vanguard]
                    ([codigo], [empresa], [login], [nome], [cargo], [data_cadastro], [renovacao], [status], [vencimento], [grupo])
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $codigo,
                $empresa,
                $login,
                $nome,
                $cargo,
                $dataCadastro,
                $renovacao,
                $status ?: 'Ativo',
                $vencimento ?: null,
                $grupo,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario Vanguard adicionado.',
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao adicionar Vanguard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function vanguard_edit(Request $request)
    {
        try {
            $payload = $request->all();
            $id = (int) ($payload['id'] ?? 0);
            $codigo = trim((string) ($payload['codigo'] ?? ''));
            $empresa = trim((string) ($payload['empresa'] ?? ''));
            $login = trim((string) ($payload['login'] ?? ''));
            $nome = trim((string) ($payload['nome'] ?? ''));
            $cargo = trim((string) ($payload['cargo'] ?? ''));
            $dataCadastro = trim((string) ($payload['data_cadastro'] ?? ''));
            $renovacao = trim((string) ($payload['renovacao'] ?? ''));
            $status = trim((string) ($payload['status'] ?? ''));
            $vencimento = trim((string) ($payload['vencimento'] ?? ''));
            $grupo = trim((string) ($payload['grupo'] ?? ''));

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo obrigatorio: id.',
                ], 400);
            }

            if ($codigo === '' || $empresa === '' || $login === '' || $cargo === '' || $dataCadastro === '' || $grupo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campos obrigatorios: codigo, empresa, login, cargo, data_cadastro, grupo.',
                ], 400);
            }

            DB::connection('sqlsrv')->update("
                UPDATE [Inbis].[dbo].[usuarios_vanguard]
                SET
                    [codigo] = ?,
                    [empresa] = ?,
                    [login] = ?,
                    [nome] = ?,
                    [cargo] = ?,
                    [data_cadastro] = ?,
                    [renovacao] = ?,
                    [status] = ?,
                    [vencimento] = ?,
                    [grupo] = ?
                WHERE [id] = ?
            ", [
                $codigo,
                $empresa,
                $login,
                $nome,
                $cargo,
                $dataCadastro,
                $renovacao !== '' ? $renovacao : null,
                $status !== '' ? $status : null,
                $vencimento !== '' ? $vencimento : null,
                $grupo,
                $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario Vanguard atualizado.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao editar Vanguard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function vanguard_renew(Request $request)
    {
        try {
            $id = (int) $request->input('id', 0);
            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: id',
                ], 400);
            }

            $today = now()->format('Y-m-d');
            $vencimento = now()->addMonth()->format('Y-m-d');

            DB::connection('sqlsrv')->update("
                UPDATE [Inbis].[dbo].[usuarios_vanguard]
                SET [renovacao] = ?, [vencimento] = ?, [status] = 'Ativo'
                WHERE [id] = ?
            ", [$today, $vencimento, $id]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario Vanguard renovado.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao renovar Vanguard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function vanguard_inactivate(Request $request)
    {
        try {
            $id = (int) $request->input('id', 0);
            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parametro obrigatorio: id',
                ], 400);
            }

            DB::connection('sqlsrv')->update("
                UPDATE [Inbis].[dbo].[usuarios_vanguard]
                SET [status] = 'Inativo'
                WHERE [id] = ?
            ", [$id]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario Vanguard inativado.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao inativar Vanguard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
