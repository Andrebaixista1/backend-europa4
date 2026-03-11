<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DateTimeImmutable;
use Throwable;

class v8Controller extends Controller
{
    private const V8_AUTH_URL = 'https://auth.v8sistema.com/oauth/token';
    private const V8_CONSULT_URL = 'https://bff.v8sistema.com/private-consignment/consult';
    private const V8_CLIENT_ID = 'DHWogdaYmEI8n5bwwxPDzulMlSK7dwIn';
    private const V8_AUDIENCE = 'https://bff.v8sistema.com';
    private const V8_SCOPE = 'online_access';
    private const V8_PROVIDER = 'QI';
    private const V8_REGION_CODE_FALLBACK = 'S080';
    private const WAITING_STATUSES = [
        'WAITING_CONSENT',
        'WAITING_CONSULT',
        'WAITING_CREDIT_ANALYSIS',
    ];

    public function V8_online(Request $request)
    {
        try {
            $faltando = [];

            if (!$request->filled('cpf')) $faltando[] = 'cpf';
            if (!$request->filled('nome')) $faltando[] = 'nome';
            if (!$request->filled('data_nascimento')) $faltando[] = 'data_nascimento';
            if (!$request->filled('id_consulta')) $faltando[] = 'id_consulta';
            if (!$request->filled('id_user')) $faltando[] = 'id_user';
            if (!$request->filled('equipe_id')) $faltando[] = 'equipe_id';

            if (!empty($faltando)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campos obrigatorios faltando.',
                    'faltando' => $faltando,
                ], 400);
            }

            $cpf = $this->normalizarCpf((string) $request->cpf);
            $nome = trim((string) $request->nome);
            $idConsulta = (int) $request->id_consulta;
            $idUser = (int) $request->id_user;
            $equipeId = (int) $request->equipe_id;
            $dataNascimento = $this->normalizarDataParaSql((string) $request->data_nascimento);
            $telefone = $this->normalizarTelefone((string) ($request->telefone ?? ''));

            if ($idUser <= 0 || $equipeId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'id_user e equipe_id devem ser numeros inteiros maiores que zero.',
                ], 400);
            }

            if ($dataNascimento === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'data_nascimento deve estar no formato yyyy-mm-dd ou dd/mm/yyyy.',
                ], 400);
            }

            if ($telefone === '') {
                $telefone = $this->gerarTelefoneAleatorio();
            }

            $idFila = DB::table('consultas_api.dbo.filaconsulta_v8')->insertGetId([
                'cpf' => $cpf,
                'nome' => $nome,
                'telefone' => $telefone,
                'status' => 'fila',
                'id_consulta' => $idConsulta,
                'id_user' => $idUser,
                'equipe_id' => $equipeId,
                'dataNascimento' => $dataNascimento,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente adicionado na fila V8.',
                'data' => [
                    'id_fila' => $idFila,
                    'cpf' => $cpf,
                    'nome' => $nome,
                    'telefone' => $telefone,
                    'dataNascimento' => $dataNascimento,
                    'id_consulta' => $idConsulta,
                    'id_user' => $idUser,
                    'equipe_id' => $equipeId,
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('Erro em V8_online', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao adicionar cliente na fila V8.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function processar_fila()
    {
        $processados = [];
        $erros = [];
        $idsBloqueados = [];
        $idsAguardando = [];

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        try {
            while (true) {
                $idsPendentes = $this->listarIdsConsultaPendentes();

                if ($idsPendentes === []) {
                    break;
                }

                $processouNestaRodada = false;

                foreach ($idsPendentes as $idConsulta) {
                    if (isset($idsBloqueados[$idConsulta]) || isset($idsAguardando[$idConsulta])) {
                        continue;
                    }

                    $lockAdquirido = $this->adquirirLockFilaPorConsulta($idConsulta);

                    if (!$lockAdquirido) {
                        continue;
                    }

                    try {
                        $fila = $this->buscarProximaFilaPorConsulta($idConsulta);

                        if (!$fila) {
                            continue;
                        }

                        $processouNestaRodada = true;

                        try {
                    $saldo = $this->buscarSaldo((int) $fila->id_consulta);

                    if (!$saldo) {
                        $this->salvarConsultaV8($fila, [
                            'status' => 'ERRO_SALDO',
                            'status_consulta_v8' => 'SALDO_NOT_FOUND',
                            'descricao_v8' => 'Saldo V8 nao encontrado para o id_consulta informado.',
                        ]);

                        $this->removerFila((int) $fila->id);
                        $erros[] = ['cpf' => $fila->cpf ?? null, 'erro' => 'Saldo nao encontrado'];
                        sleep(2);
                        continue;
                    }

                    if (!$this->equipePodeUsarSaldo($saldo->equipe_id ?? null, $fila->equipe_id ?? null)) {
                        $this->salvarConsultaV8($fila, [
                            'status' => 'SEM_PERMISSAO_EQUIPE',
                            'status_consulta_v8' => 'TEAM_NOT_ALLOWED',
                            'descricao_v8' => 'Equipe sem permissao para usar este login/token V8.',
                        ]);

                        $this->removerFila((int) $fila->id);
                        $erros[] = [
                            'cpf' => $fila->cpf ?? null,
                            'erro' => 'Equipe sem permissao para o id_consulta ' . (int) $fila->id_consulta,
                        ];
                        sleep(1);
                        continue;
                    }

                    $controleLimite = $this->validarLimitePorHora($saldo);

                    if (!$controleLimite['pode_consultar']) {
                        $idsBloqueados[(int) $fila->id_consulta] = $controleLimite['libera_em'];
                        $erros[] = [
                            'cpf' => $fila->cpf ?? null,
                            'erro' => 'Limite de consultas por hora atingido para id_consulta ' . (int) $fila->id_consulta
                                . '. Libera em: ' . ($controleLimite['libera_em'] ?? 'N/A'),
                        ];
                        continue;
                    }

                    if ($controleLimite['resetado']) {
                        $saldo = $this->buscarSaldo((int) $fila->id_consulta);
                        if (!$saldo) {
                            $this->salvarConsultaV8($fila, [
                                'status' => 'ERRO_SALDO',
                                'status_consulta_v8' => 'SALDO_NOT_FOUND',
                                'descricao_v8' => 'Saldo V8 nao encontrado apos reset de 1h.',
                            ]);

                            $this->removerFila((int) $fila->id);
                            $erros[] = [
                                'cpf' => $fila->cpf ?? null,
                                'erro' => 'Saldo nao encontrado apos reset de 1h.',
                            ];
                            continue;
                        }
                    }

                    $tokenPayload = $this->autenticarV8((string) ($saldo->email ?? ''), (string) ($saldo->senha ?? ''));

                    if (!$tokenPayload['success']) {
                        $this->salvarConsultaV8($fila, [
                            'status' => 'ERRO_AUTH',
                            'status_consulta_v8' => 'AUTH_ERROR',
                            'descricao_v8' => $tokenPayload['message'],
                        ]);

                        $this->removerFila((int) $fila->id);
                        $erros[] = [
                            'cpf' => $fila->cpf ?? null,
                            'erro' => $tokenPayload['message'] ?? 'Falha de autenticacao V8',
                        ];
                        sleep(2);
                        continue;
                    }

                    $accessToken = (string) $tokenPayload['access_token'];
                    $statusFila = strtolower(trim((string) ($fila->status ?? 'fila')));

                    if ($statusFila === 'fila') {
                        $criarConsulta = $this->criarConsultaV8($accessToken, $fila);

                        if (!$criarConsulta['success']) {
                        $this->salvarConsultaV8($fila, [
                            'status' => 'ERRO_CONSULTA',
                            'status_consulta_v8' => 'CREATE_ERROR',
                            'descricao_v8' => $criarConsulta['message'],
                        ]);

                        $this->removerFila((int) $fila->id);
                        $erros[] = [
                            'cpf' => $fila->cpf ?? null,
                            'erro' => $criarConsulta['message'] ?? 'Falha ao criar consulta',
                        ];
                        sleep(2);
                        continue;
                    }

                        $autorizar = $this->autorizarConsultaV8($accessToken, (string) $criarConsulta['id']);

                        if (!$autorizar['success']) {
                            $this->salvarConsultaV8($fila, [
                                'status' => 'ERRO_AUTORIZACAO',
                                'status_consulta_v8' => 'AUTHORIZE_ERROR',
                                'descricao_v8' => $autorizar['message'],
                            ]);

                        $this->consumirSaldo((int) $fila->id_consulta);
                        $this->removerFila((int) $fila->id);
                        $erros[] = [
                            'cpf' => $fila->cpf ?? null,
                            'erro' => $autorizar['message'] ?? 'Falha ao autorizar consulta',
                        ];
                        sleep(2);
                        continue;
                    }

                        // Conta uma consulta quando a V8 recebeu create + authorize.
                        $this->consumirSaldo((int) $fila->id_consulta);
                    }

                    $resultado = $this->consultarResultadoComTentativas($accessToken, (string) $fila->cpf);

                    if ($resultado['waiting']) {
                        $this->marcarFilaAguardando((int) $fila->id);
                        $idsAguardando[(int) $fila->id_consulta] = true;
                        $erros[] = [
                            'cpf' => $fila->cpf ?? null,
                            'erro' => 'Consulta ainda em processamento na V8 (aguardando).',
                        ];
                        continue;
                    }

                    if (!$resultado['success']) {
                        $this->salvarConsultaV8($fila, [
                            'status' => 'SEM_RESULTADO',
                            'status_consulta_v8' => $resultado['status_consulta_v8'] ?? 'NOT_FOUND',
                            'descricao_v8' => $resultado['message'] ?? 'Consulta sem retorno na V8.',
                        ]);

                        $this->removerFila((int) $fila->id);
                        $erros[] = [
                            'cpf' => $fila->cpf ?? null,
                            'erro' => $resultado['message'] ?? 'Sem retorno na consulta.',
                        ];
                        sleep(2);
                        continue;
                    }

                    $this->salvarConsultaV8($fila, [
                        'status' => 'Concluido',
                        'status_consulta_v8' => $resultado['status_consulta_v8'] ?? 'SUCCESS',
                        'valor_liberado' => $resultado['valor_liberado'] ?? null,
                        'descricao_v8' => $resultado['descricao_v8'] ?? 'OK',
                    ]);

                    $this->removerFila((int) $fila->id);
                    $processados[] = $fila->cpf ?? null;
                    sleep(2);
                        } catch (Throwable $e) {
                            Log::error('Erro ao processar item da fila V8', [
                                'fila_id' => $fila->id ?? null,
                                'cpf' => $fila->cpf ?? null,
                                'message' => $e->getMessage(),
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                            ]);

                            $erros[] = [
                                'cpf' => $fila->cpf ?? null,
                                'erro' => $e->getMessage(),
                            ];

                            $this->removerFila((int) ($fila->id ?? 0));
                            sleep(2);
                        }
                    } finally {
                        try {
                            $this->liberarLockFilaPorConsulta($idConsulta);
                        } catch (Throwable $e) {
                            Log::warning('Falha ao liberar lock da fila V8 por id_consulta', [
                                'id_consulta' => $idConsulta,
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                if (!$processouNestaRodada) {
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Fila V8 processada.',
                'total_processados' => count($processados),
                'cpfs_processados' => $processados,
                'erros' => $erros,
                'ids_bloqueados' => $this->formatarIdsBloqueados($idsBloqueados),
                'ids_aguardando' => array_values(array_keys($idsAguardando)),
            ], 200);
        } catch (Throwable $e) {
            Log::error('Erro geral em processar_fila V8', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar fila V8.',
                'error' => $e->getMessage(),
                'processados_ate_agora' => $processados,
                'erros' => $erros,
                'ids_bloqueados' => $this->formatarIdsBloqueados($idsBloqueados),
                'ids_aguardando' => array_values(array_keys($idsAguardando)),
            ], 500);
        }
    }

    private function listarIdsConsultaPendentes(): array
    {
        $rows = DB::select("
            SELECT DISTINCT CAST(id_consulta AS INT) AS id_consulta
            FROM consultas_api.dbo.filaconsulta_v8
            WHERE id_consulta IS NOT NULL
            ORDER BY CAST(id_consulta AS INT) ASC
        ");

        $ids = [];

        foreach ($rows as $row) {
            $idConsulta = $this->extrairInteiroPositivo($row->id_consulta ?? null);

            if ($idConsulta !== null) {
                $ids[] = $idConsulta;
            }
        }

        return $ids;
    }

    private function buscarProximaFilaPorConsulta(int $idConsulta): ?object
    {
        return DB::selectOne("
            SELECT TOP 1 *
            FROM consultas_api.dbo.filaconsulta_v8
            WHERE id_consulta = ?
            ORDER BY
                CASE
                    WHEN LOWER(ISNULL(status, 'fila')) = 'fila' THEN 0
                    WHEN LOWER(ISNULL(status, 'fila')) = 'aguardando' THEN 1
                    ELSE 2
                END,
                ISNULL(updated_at, created_at) ASC,
                id ASC
        ", [$idConsulta]);
    }

    private function buscarSaldo(int $idConsulta): ?object
    {
        return DB::selectOne("
            SELECT TOP 1 *
            FROM consultas_api.dbo.saldo_v8
            WHERE id = ?
        ", [$idConsulta]);
    }

    private function autenticarV8(string $username, string $password): array
    {
        if (trim($username) === '' || trim($password) === '') {
            return [
                'success' => false,
                'message' => 'Email/senha da V8 nao configurados no saldo_v8.',
            ];
        }

        $response = $this->v8Http()
            ->asForm()
            ->timeout(60)
            ->post(self::V8_AUTH_URL, [
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password,
                'audience' => self::V8_AUDIENCE,
                'scope' => self::V8_SCOPE,
                'client_id' => self::V8_CLIENT_ID,
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Falha ao obter token V8: HTTP ' . $response->status() . ' - ' . trim($response->body()),
            ];
        }

        $json = $response->json();

        if (!is_array($json) || !isset($json['access_token'])) {
            return [
                'success' => false,
                'message' => 'Resposta de autenticacao V8 sem access_token.',
            ];
        }

        return [
            'success' => true,
            'access_token' => (string) $json['access_token'],
        ];
    }

    private function criarConsultaV8(string $accessToken, object $fila): array
    {
        $telefone = $this->quebrarTelefone((string) ($fila->telefone ?? ''));
        $birthDate = $this->normalizarDataParaSql((string) ($fila->dataNascimento ?? ''));

        if ($birthDate === null) {
            $birthDate = (new DateTimeImmutable('1980-01-01'))->format('Y-m-d');
        }

        $response = $this->v8Http()
            ->timeout(60)
            ->withToken($accessToken)
            ->acceptJson()
            ->post(self::V8_CONSULT_URL, [
                'borrowerDocumentNumber' => $this->normalizarCpf((string) $fila->cpf),
                'gender' => 'male',
                'birthDate' => $birthDate,
                'signerName' => trim((string) ($fila->nome ?? 'SEM NOME')),
                'signerEmail' => 'NAOTENHO@GMAIL.COM',
                'signerPhone' => [
                    'phoneNumber' => $telefone['phoneNumber'],
                    'countryCode' => '55',
                    'areaCode' => $telefone['areaCode'],
                ],
                'provider' => self::V8_PROVIDER,
                'region_code' => $this->obterRegionCodeV8(),
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Falha ao criar consulta V8: HTTP ' . $response->status() . ' - ' . trim($response->body()),
            ];
        }

        $json = $response->json();
        $id = is_array($json) ? (string) ($json['id'] ?? '') : '';

        if ($id === '') {
            return [
                'success' => false,
                'message' => 'Resposta da V8 sem id da consulta.',
            ];
        }

        return [
            'success' => true,
            'id' => $id,
        ];
    }

    private function autorizarConsultaV8(string $accessToken, string $consultaId): array
    {
        $url = self::V8_CONSULT_URL . '/' . rawurlencode($consultaId) . '/authorize';

        $response = $this->v8Http()
            ->timeout(60)
            ->withToken($accessToken)
            ->acceptJson()
            ->post($url);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Falha ao autorizar consulta V8: HTTP ' . $response->status() . ' - ' . trim($response->body()),
            ];
        }

        return [
            'success' => true,
            'message' => 'Autorizada',
        ];
    }

    private function consultarResultadoComTentativas(string $accessToken, string $cpf): array
    {
        $ultimoRetorno = [
            'success' => false,
            'waiting' => false,
            'status_consulta_v8' => 'NOT_FOUND',
            'message' => 'Consulta nao encontrada na V8.',
        ];

        for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
            $resultado = $this->consultarResultadoV8($accessToken, $cpf);

            if (!$resultado['success']) {
                $ultimoRetorno = $resultado;
                if ($tentativa < 3) {
                    sleep(5);
                    continue;
                }
                return $ultimoRetorno;
            }

            $status = strtoupper((string) ($resultado['status_consulta_v8'] ?? ''));

            if (in_array($status, self::WAITING_STATUSES, true)) {
                $ultimoRetorno = [
                    'success' => false,
                    'waiting' => true,
                    'status_consulta_v8' => $status,
                    'message' => 'Consulta em processamento na V8.',
                ];

                if ($tentativa < 3) {
                    sleep(5);
                    continue;
                }

                return $ultimoRetorno;
            }

            return [
                'success' => true,
                'waiting' => false,
                'status_consulta_v8' => $status !== '' ? $status : 'SUCCESS',
                'valor_liberado' => $resultado['valor_liberado'] ?? null,
                'descricao_v8' => $resultado['descricao_v8'] ?? 'OK',
            ];
        }

        return $ultimoRetorno;
    }

    private function consultarResultadoV8(string $accessToken, string $cpf): array
    {
        $agora = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startDate = $agora->modify('-1 day')->setTime(0, 0, 0)->format('Y-m-d\TH:i:s\Z');
        $endDate = $agora->setTime(23, 59, 59)->format('Y-m-d\TH:i:s\Z');

        $response = $this->v8Http()
            ->timeout(60)
            ->withToken($accessToken)
            ->acceptJson()
            ->get(self::V8_CONSULT_URL, [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'limit' => 50,
                'page' => 1,
                'search' => $this->normalizarCpf($cpf),
                'provider' => self::V8_PROVIDER,
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'waiting' => false,
                'status_consulta_v8' => 'HTTP_' . $response->status(),
                'message' => 'Falha ao consultar resultado V8: HTTP ' . $response->status() . ' - ' . trim($response->body()),
            ];
        }

        $json = $response->json();

        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            return [
                'success' => false,
                'waiting' => false,
                'status_consulta_v8' => 'INVALID_RESPONSE',
                'message' => 'Resposta da V8 sem lista de dados.',
            ];
        }

        $cpfNormalizado = $this->normalizarCpf($cpf);
        $registro = null;

        foreach ($json['data'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $doc = $this->normalizarCpf((string) ($item['documentNumber'] ?? ''));

            if ($doc === $cpfNormalizado) {
                $registro = $item;
                break;
            }
        }

        if (!is_array($registro)) {
            return [
                'success' => false,
                'waiting' => false,
                'status_consulta_v8' => 'NOT_FOUND',
                'message' => 'Consulta nao localizada para o CPF informado.',
            ];
        }

        return [
            'success' => true,
            'waiting' => false,
            'status_consulta_v8' => strtoupper((string) ($registro['status'] ?? 'UNKNOWN')),
            'valor_liberado' => $registro['availableMarginValue'] ?? null,
            'descricao_v8' => $registro['description'] ?? null,
        ];
    }

    private function salvarConsultaV8(object $fila, array $dados): void
    {
        DB::table('consultas_api.dbo.consulta_v8')->insert([
            'cliente_cpf' => $this->normalizarCpf((string) ($fila->cpf ?? '')),
            'cliente_sexo' => null,
            'nascimento' => $this->normalizarDataParaSql((string) ($fila->dataNascimento ?? '')),
            'cliente_nome' => $fila->nome ?? null,
            'email' => 'NAOTENHO@GMAIL.COM',
            'telefone' => $this->normalizarTelefone((string) ($fila->telefone ?? '')),
            'created_at' => now(),
            'status' => $dados['status'] ?? 'SEM_RESULTADO',
            'status_consulta_v8' => $dados['status_consulta_v8'] ?? null,
            'valor_liberado' => $dados['valor_liberado'] ?? null,
            'descricao_v8' => $dados['descricao_v8'] ?? null,
            'id_user' => $this->extrairInteiroPositivo($fila->id_user ?? null),
            'id_equipe' => $this->extrairInteiroPositivo($fila->equipe_id ?? null),
            'id_roles' => null,
            'tipoConsulta' => 'CLT',
        ]);
    }

    private function marcarFilaAguardando(int $filaId): void
    {
        DB::update("
            UPDATE consultas_api.dbo.filaconsulta_v8
            SET status = 'aguardando',
                updated_at = GETDATE()
            WHERE id = ?
        ", [$filaId]);
    }

    private function removerFila(int $filaId): void
    {
        if ($filaId <= 0) {
            return;
        }

        DB::delete("
            DELETE FROM consultas_api.dbo.filaconsulta_v8
            WHERE id = ?
        ", [$filaId]);
    }

    private function consumirSaldo(int $saldoId): void
    {
        DB::update("
            UPDATE consultas_api.dbo.saldo_v8
            SET consultados = ISNULL(consultados, 0) + 1,
                limite = ISNULL(limite, 0) - 1,
                updated_at = GETDATE()
            WHERE id = ?
        ", [$saldoId]);
    }

    /**
     * @return array{pode_consultar: bool, resetado: bool, libera_em: ?string}
     */
    private function validarLimitePorHora(object $saldo): array
    {
        $limiteAtual = (int) ($saldo->limite ?? 0);

        if ($limiteAtual > 0) {
            return [
                'pode_consultar' => true,
                'resetado' => false,
                'libera_em' => null,
            ];
        }

        $atualizadoEm = $this->criarDataHora($saldo->updated_at ?? null);

        if (!$atualizadoEm) {
            $this->resetarLimiteHora((int) $saldo->id);

            return [
                'pode_consultar' => true,
                'resetado' => true,
                'libera_em' => null,
            ];
        }

        $liberaEm = $atualizadoEm->modify('+1 hour');
        $agora = new DateTimeImmutable('now', $liberaEm->getTimezone());

        if ($agora >= $liberaEm) {
            $this->resetarLimiteHora((int) $saldo->id);

            return [
                'pode_consultar' => true,
                'resetado' => true,
                'libera_em' => $liberaEm->format('d/m/Y H:i:s'),
            ];
        }

        return [
            'pode_consultar' => false,
            'resetado' => false,
            'libera_em' => $liberaEm->format('d/m/Y H:i:s'),
        ];
    }

    private function resetarLimiteHora(int $saldoId): void
    {
        DB::update("
            UPDATE consultas_api.dbo.saldo_v8
            SET consultados = 0,
                limite = CASE
                    WHEN ISNULL(total, 0) > 0 THEN ISNULL(total, 0)
                    ELSE 250
                END,
                updated_at = GETDATE()
            WHERE id = ?
        ", [$saldoId]);
    }

    private function normalizarCpf(string $cpf): string
    {
        $cpfNumerico = preg_replace('/\D/', '', $cpf);

        if (!is_string($cpfNumerico) || $cpfNumerico === '') {
            return '';
        }

        if (strlen($cpfNumerico) > 11) {
            $cpfNumerico = substr($cpfNumerico, -11);
        }

        return str_pad($cpfNumerico, 11, '0', STR_PAD_LEFT);
    }

    private function normalizarTelefone(string $telefone): string
    {
        $digits = preg_replace('/\D/', '', $telefone);
        return is_string($digits) ? $digits : '';
    }

    private function quebrarTelefone(string $telefone): array
    {
        $digits = $this->normalizarTelefone($telefone);
        $areaCodePadrao = $this->obterAreaCodePadraoV8();

        if (strlen($digits) < 10) {
            $numero = '9' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $digits = $areaCodePadrao . $numero;
        }

        $areaCode = substr($digits, 0, 2);
        $phoneNumber = substr($digits, 2);

        if (!preg_match('/^\d{2}$/', $areaCode)) {
            $areaCode = $areaCodePadrao;
        }

        if (strlen($phoneNumber) > 9) {
            $phoneNumber = substr($phoneNumber, -9);
        }

        if (strlen($phoneNumber) < 8) {
            $phoneNumber = '9' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        }

        return [
            'areaCode' => $areaCode !== '' ? $areaCode : $areaCodePadrao,
            'phoneNumber' => $phoneNumber,
        ];
    }

    private function gerarTelefoneAleatorio(): string
    {
        $ddd = str_pad((string) random_int(11, 99), 2, '0', STR_PAD_LEFT);
        $numero = '9' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        return $ddd . $numero;
    }

    private function normalizarDataParaSql(string $data): ?string
    {
        $data = trim($data);

        if ($data === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) === 1) {
            return $data;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data) === 1) {
            [$dia, $mes, $ano] = explode('/', $data);
            return $ano . '-' . $mes . '-' . $dia;
        }

        return null;
    }

    private function criarDataHora(mixed $valor): ?DateTimeImmutable
    {
        if (!$valor) {
            return null;
        }

        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        try {
            return new DateTimeImmutable((string) $valor);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function v8Http()
    {
        $request = Http::acceptJson();

        if (!$this->deveValidarSslV8()) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    private function deveValidarSslV8(): bool
    {
        return filter_var(env('V8_SSL_VERIFY', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function obterRegionCodeV8(): string
    {
        $value = trim((string) env('V8_REGION_CODE', self::V8_REGION_CODE_FALLBACK));
        return $value !== '' ? $value : self::V8_REGION_CODE_FALLBACK;
    }

    private function obterAreaCodePadraoV8(): string
    {
        $value = preg_replace('/\D/', '', (string) env('V8_AREA_CODE', '11'));

        if (!is_string($value) || strlen($value) !== 2) {
            return '11';
        }

        return $value;
    }

    private function extrairInteiroPositivo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $intValue = (int) $valor;
        return $intValue > 0 ? $intValue : null;
    }

    private function adquirirLockFilaPorConsulta(int $idConsulta): bool
    {
        $resource = 'v8:processar_fila:' . $idConsulta;

        $result = DB::selectOne("
            DECLARE @resultado INT;
            EXEC @resultado = sp_getapplock
                @Resource = ?,
                @LockMode = 'Exclusive',
                @LockOwner = 'Session',
                @LockTimeout = 0;
            SELECT @resultado AS resultado;
        ", [$resource]);

        return is_object($result) && isset($result->resultado) && (int) $result->resultado >= 0;
    }

    private function liberarLockFilaPorConsulta(int $idConsulta): void
    {
        $resource = 'v8:processar_fila:' . $idConsulta;

        DB::statement("
            EXEC sp_releaseapplock
                @Resource = ?,
                @LockOwner = 'Session';
        ", [$resource]);
    }

    private function formatarIdsBloqueados(array $idsBloqueados): array
    {
        $retorno = [];

        foreach ($idsBloqueados as $idConsulta => $liberaEm) {
            $retorno[] = [
                'id_consulta' => (int) $idConsulta,
                'libera_em' => $liberaEm,
            ];
        }

        return $retorno;
    }

    private function equipePodeUsarSaldo(mixed $equipeIdsPermitidos, mixed $equipeIdFila): bool
    {
        $equipeFila = $this->extrairInteiroPositivo($equipeIdFila);

        if ($equipeFila === null) {
            return false;
        }

        $permitidos = $this->parseEquipeIdsPermitidos($equipeIdsPermitidos);

        // Regra de negocio: equipe 1 pode usar por padrao em qualquer token.
        if (!in_array(1, $permitidos, true)) {
            $permitidos[] = 1;
        }

        return in_array($equipeFila, $permitidos, true);
    }

    /**
     * @return array<int>
     */
    private function parseEquipeIdsPermitidos(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return [1];
        }

        if (is_int($valor)) {
            return $valor > 0 ? [$valor, 1] : [1];
        }

        if (is_string($valor)) {
            $texto = trim($valor);

            if ($texto === '') {
                return [1];
            }

            $texto = trim($texto, "[]{}()");
            $partes = preg_split('/[,\;\|\s]+/', $texto) ?: [];
            $ids = [];

            foreach ($partes as $parte) {
                if ($parte === '') {
                    continue;
                }

                $id = $this->extrairInteiroPositivo($parte);
                if ($id !== null) {
                    $ids[] = $id;
                }
            }

            if (!in_array(1, $ids, true)) {
                $ids[] = 1;
            }

            return array_values(array_unique($ids));
        }

        return [1];
    }
}
