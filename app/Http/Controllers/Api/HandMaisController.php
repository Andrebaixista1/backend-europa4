<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;
use DateTimeImmutable;

class HandMaisController extends Controller
{
    public function handmais_online(Request $request)
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
                    'message' => 'Campos obrigatórios faltando.',
                    'faltando' => $faltando
                ], 400);
            }

            $cpf = $this->normalizarCpf((string) $request->cpf);
            $nome = trim((string) $request->nome);
            $telefone = preg_replace('/\D/', '', (string) ($request->telefone ?? ''));
            $dataNascimento = trim((string) $request->data_nascimento);
            $idConsulta = (int) $request->id_consulta;
            $idUser = (int) $request->id_user;
            $equipeId = (int) $request->equipe_id;

            if ($idUser <= 0 || $equipeId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'id_user e equipe_id devem ser numeros inteiros maiores que zero.'
                ], 400);
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataNascimento)) {
                return response()->json([
                    'success' => false,
                    'message' => 'data_nascimento deve estar no formato yyyy-mm-dd'
                ], 400);
            }

            $dataNascimentoFormatada = $this->converterDataParaBr($dataNascimento);

            if ($telefone === '') {
                $telefone = $this->gerarTelefoneAleatorio();
            }

            $idFila = DB::table('consultas_api.dbo.filaconsulta_handmais')->insertGetId([
                'cpf' => $cpf,
                'nome' => $nome,
                'telefone' => $telefone,
                'dataNascimento' => $dataNascimentoFormatada,
                'status' => 'fila',
                'id_consulta' => $idConsulta,
                'id_user' => $idUser,
                'equipe_id' => $equipeId,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente adicionado na fila.',
                'data' => [
                    'id_fila' => $idFila,
                    'cpf' => $cpf,
                    'nome' => $nome,
                    'telefone' => $telefone,
                    'dataNascimento' => $dataNascimentoFormatada,
                    'id_consulta' => $idConsulta,
                    'id_user' => $idUser,
                    'equipe_id' => $equipeId
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error('Erro em handmais_online', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao adicionar cliente na fila.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function processar_fila()
    {
        $processados = [];
        $erros = [];
        $lockAdquirido = false;

        // A fila pode demorar para finalizar; nao deixar a request expirar.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        try {
            // Bloqueia chamadas concorrentes do endpoint ate o processamento terminar.
            $lockAdquirido = $this->adquirirLockFila();

            if (!$lockAdquirido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Processamento da fila ja esta em andamento. Aguarde finalizar.'
                ], 409);
            }

            while (true) {
                $fila = DB::selectOne("
                    SELECT *
                    FROM (
                        SELECT 
                            ROW_NUMBER() OVER (ORDER BY id ASC) AS posicao,
                            *
                        FROM consultas_api.dbo.filaconsulta_handmais
                    ) fila
                    WHERE posicao = 1
                ");

                if (!$fila) {
                    break;
                }

                try {
                    $saldo = DB::selectOne("
                        SELECT TOP 1 *
                        FROM consultas_api.dbo.saldo_handmais
                        WHERE id = ?
                    ", [$fila->id_consulta]);

                    if (!$saldo) {
                        $this->salvarResultadoSemProduto(
                            $fila,
                            null,
                            'SEM_SALDO',
                            'Token/saldo não encontrado para o id_consulta informado.'
                        );

                        DB::delete("
                            DELETE FROM consultas_api.dbo.filaconsulta_handmais
                            WHERE id = ?
                        ", [$fila->id]);

                        $erros[] = [
                            'cpf' => $fila->cpf,
                            'erro' => 'Token não encontrado'
                        ];

                        sleep(3);
                        continue;
                    }

                    $controleSaldo = $this->validarSaldoDiario($saldo);

                    if (!$controleSaldo['pode_consultar']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Limite de consultas diarias atingidas.',
                            'libera_em' => $controleSaldo['libera_em'],
                            'id_consulta' => (int) $fila->id_consulta,
                        ], 429);
                    }

                    if ($controleSaldo['resetado']) {
                        $saldo = DB::selectOne("
                            SELECT TOP 1 *
                            FROM consultas_api.dbo.saldo_handmais
                            WHERE id = ?
                        ", [$fila->id_consulta]);

                        if (!$saldo) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Saldo nao encontrado apos reset de 24h.',
                                'id_consulta' => (int) $fila->id_consulta,
                            ], 500);
                        }
                    }

                    $cpfNormalizado = $this->normalizarCpf((string) $fila->cpf);
                    $consulta = $this->consultarMargem($cpfNormalizado, $saldo->token_api);
                    $autorizacaoFalhou = false;
                    $maxTentativasAutorizacao = 2;

                    for ($tentativa = 1; $tentativa <= $maxTentativasAutorizacao && $this->precisaAutorizacao($consulta); $tentativa++) {
                        $urlAutorizacao = $this->extrairUrlAutorizacao($consulta);

                        Log::info('HandMais requer autorizacao', [
                            'fila_id' => $fila->id ?? null,
                            'cpf' => $cpfNormalizado,
                            'tentativa' => $tentativa,
                            'url' => $urlAutorizacao,
                        ]);

                        $resultadoAutomacao = $this->executarAutomacaoAutorizacao(
                            $urlAutorizacao,
                            $fila->nome,
                            $cpfNormalizado,
                            $fila->telefone,
                            $fila->dataNascimento
                        );

                        if (!$resultadoAutomacao['success']) {
                            $this->salvarResultadoSemProduto(
                                $fila,
                                $saldo,
                                'ERRO_AUTORIZACAO',
                                'Falha na automacao: ' . ($resultadoAutomacao['message'] ?? 'Erro desconhecido')
                            );

                            DB::update("
                                UPDATE consultas_api.dbo.saldo_handmais
                                SET consultados = consultados + 1,
                                    limite = limite - 1,
                                    updated_at = GETDATE()
                                WHERE id = ?
                            ", [$fila->id_consulta]);

                            DB::delete("
                                DELETE FROM consultas_api.dbo.filaconsulta_handmais
                                WHERE id = ?
                            ", [$fila->id]);

                            $erros[] = [
                                'cpf' => $fila->cpf,
                                'erro' => 'Falha na automacao'
                            ];

                            $autorizacaoFalhou = true;
                            break;
                        }

                        sleep(3);
                        $consulta = $this->consultarMargem($cpfNormalizado, $saldo->token_api);
                    }

                    if ($autorizacaoFalhou) {
                        sleep(3);
                        continue;
                    }

                    if ($this->precisaAutorizacao($consulta)) {
                        $this->salvarResultadoSemProduto(
                            $fila,
                            $saldo,
                            'PENDENTE_AUTORIZACAO',
                            'Autorizacao pendente ou nao concluida automaticamente.'
                        );

                        DB::update("
                            UPDATE consultas_api.dbo.saldo_handmais
                            SET consultados = consultados + 1,
                                limite = limite - 1,
                                updated_at = GETDATE()
                            WHERE id = ?
                        ", [$fila->id_consulta]);

                        DB::delete("
                            DELETE FROM consultas_api.dbo.filaconsulta_handmais
                            WHERE id = ?
                        ", [$fila->id]);

                        $erros[] = [
                            'cpf' => $fila->cpf,
                            'erro' => 'Autorizacao nao concluida'
                        ];

                        sleep(3);
                        continue;
                    }
                    if ($this->consultaTemMargemDireta($consulta)) {
                        DB::table('consultas_api.dbo.consulta_handmais')->insert([
                            'nome' => $fila->nome,
                            'cpf' => $fila->cpf,
                            'telefone' => $fila->telefone,
                            'dataNascimento' => $this->normalizarDataParaSql((string) $fila->dataNascimento),
                            'status' => 'Concluido',
                            'tipoConsulta' => 'CLT',
                            'descricao' => 'OK',
                            'nome_tabela' => $consulta['nome_tabela'] ?? null,
                            'valor_margem' => $consulta['valor_margem'] ?? null,
                            'id_tabela' => $consulta['id'] ?? null,
                            'token_tabela' => $consulta['token_tabela'] ?? null,
                            'id_user' => $this->resolverIdUser($fila, $saldo),
                            'equipe_id' => $this->resolverEquipeId($fila, $saldo),
                            'id_consulta_hand' => $fila->id_consulta,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    } elseif ($this->consultaTemProdutos($consulta)) {
                        foreach ($consulta as $produto) {
                            DB::table('consultas_api.dbo.consulta_handmais')->insert([
                                'nome' => $fila->nome,
                                'cpf' => $fila->cpf,
                                'telefone' => $fila->telefone,
                                'dataNascimento' => $this->normalizarDataParaSql((string) $fila->dataNascimento),
                                'status' => 'Concluido',
                                'tipoConsulta' => 'CLT',
                                'descricao' => 'Consulta realizada',
                                'nome_tabela' => $produto['nome_tabela'] ?? null,
                                'valor_margem' => $produto['valor_margem'] ?? null,
                                'id_tabela' => $produto['id'] ?? null,
                                'token_tabela' => $produto['token_tabela'] ?? null,
                                'id_user' => $this->resolverIdUser($fila, $saldo),
                                'equipe_id' => $this->resolverEquipeId($fila, $saldo),
                                'id_consulta_hand' => $fila->id_consulta,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    } else {
                        $descricao = $this->extrairMensagemConsulta($consulta);
                        $statusResultado = 'SEM_RESULTADO';

                        if ($this->mensagemTemUrl($descricao)) {
                            $statusResultado = 'PENDENTE_AUTORIZACAO';
                            $descricao = 'Autorizacao pendente ou nao concluida automaticamente.';
                        }

                        if ($descricao === '') {
                            $descricao = 'Consulta sem resultado.';
                        }

                        $this->salvarResultadoSemProduto(
                            $fila,
                            $saldo,
                            $statusResultado,
                            $descricao
                        );
                    }

                    DB::update("
                        UPDATE consultas_api.dbo.saldo_handmais
                        SET consultados = consultados + 1,
                            limite = limite - 1,
                            updated_at = GETDATE()
                        WHERE id = ?
                    ", [$fila->id_consulta]);

                    DB::delete("
                        DELETE FROM consultas_api.dbo.filaconsulta_handmais
                        WHERE id = ?
                    ", [$fila->id]);

                    $processados[] = $fila->cpf;

                    sleep(3);
                } catch (Throwable $e) {
                    Log::error('Erro ao processar item da fila handmais', [
                        'fila_id' => $fila->id ?? null,
                        'cpf' => $fila->cpf ?? null,
                        'message' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                    ]);

                    $erros[] = [
                        'cpf' => $fila->cpf ?? null,
                        'erro' => $e->getMessage()
                    ];

                    DB::delete("
                        DELETE FROM consultas_api.dbo.filaconsulta_handmais
                        WHERE id = ?
                    ", [$fila->id]);

                    sleep(3);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Fila processada.',
                'total_processados' => count($processados),
                'cpfs_processados' => $processados,
                'erros' => $erros
            ], 200);
        } catch (Throwable $e) {
            Log::error('Erro geral em processar_fila handmais', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar fila.',
                'error' => $e->getMessage(),
                'processados_ate_agora' => $processados,
                'erros' => $erros
            ], 500);
        } finally {
            if ($lockAdquirido) {
                try {
                    $this->liberarLockFila();
                } catch (Throwable $e) {
                    Log::warning('Falha ao liberar lock da fila handmais', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function consultarMargem(string $cpf, string $token): array
    {
        $url = 'https://app.handmais.com/uy3/simulacao_clt';

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(180)
            ->post($url, [
                'cpf' => $cpf
            ]);

        $json = $response->json();

        if (is_array($json) && isset($json[0])) {
            return $json;
        }

        if (is_array($json)) {
            $json['http_code'] = $json['http_code'] ?? $response->status();
            return $json;
        }

        return [
            'http_code' => $response->status(),
            'mensagem' => $response->body()
        ];
    }

    private function executarAutomacaoAutorizacao(
        string $url,
        string $nome,
        string $cpf,
        string $telefone,
        string $dataNascimento
    ): array {
        try {
            $nodePath = 'C:\\Program Files\\nodejs\\node.exe';
            $scriptPath = base_path('automation\\uy3_autorizacao.cjs');

            if (!file_exists($nodePath)) {
                return [
                    'success' => false,
                    'message' => 'Node não encontrado em: ' . $nodePath
                ];
            }

            if (!file_exists($scriptPath)) {
                return [
                    'success' => false,
                    'message' => 'Script não encontrado em: ' . $scriptPath
                ];
            }

            $process = new Process([
                $nodePath,
                $scriptPath,
                $url,
                $nome,
                $cpf,
                $telefone,
                $dataNascimento
            ], base_path(), [
                'PATH' => getenv('PATH') ?: '',
                'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
                'TEMP' => sys_get_temp_dir(),
                'TMP' => sys_get_temp_dir(),
            ]);

            $process->setTimeout(180);
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'message' => trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : 'Falha na automação'
                ];
            }

            return [
                'success' => true,
                'message' => trim($process->getOutput()) !== '' ? trim($process->getOutput()) : 'AUTORIZACAO_OK'
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function salvarResultadoSemProduto(object $fila, ?object $saldo, string $status, string $descricao): void
    {
        DB::table('consultas_api.dbo.consulta_handmais')->insert([
            'nome' => $fila->nome,
            'cpf' => $fila->cpf,
            'telefone' => $fila->telefone,
            'dataNascimento' => $this->normalizarDataParaSql((string) ($fila->dataNascimento ?? '')),
            'status' => $status,
            'tipoConsulta' => 'CLT',
            'descricao' => $descricao,
            'nome_tabela' => null,
            'valor_margem' => null,
            'id_tabela' => null,
            'token_tabela' => null,
            'id_user' => $this->resolverIdUser($fila, $saldo),
            'equipe_id' => $this->resolverEquipeId($fila, $saldo),
            'id_consulta_hand' => $fila->id_consulta,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function resolverIdUser(?object $fila, ?object $saldo = null): ?int
    {
        $fromFila = $this->extrairInteiroPositivo($fila->id_user ?? null);
        if ($fromFila !== null) {
            return $fromFila;
        }

        return $this->extrairInteiroPositivo($saldo->id_user ?? null);
    }

    private function resolverEquipeId(?object $fila, ?object $saldo = null): ?int
    {
        $fromFila = $this->extrairInteiroPositivo($fila->equipe_id ?? null);
        if ($fromFila !== null) {
            return $fromFila;
        }

        return $this->extrairInteiroPositivo($saldo->equipe_id ?? null);
    }

    private function extrairInteiroPositivo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $intValue = (int) $valor;
        return $intValue > 0 ? $intValue : null;
    }

    private function consultaTemProdutos($consulta): bool
    {
        if (!is_array($consulta) || !isset($consulta[0]) || !is_array($consulta[0])) {
            return false;
        }

        $primeiro = $consulta[0];

        if (isset($primeiro['mensagem']) || isset($primeiro['message'])) {
            return false;
        }

        return isset($primeiro['id'])
            || isset($primeiro['nome_tabela'])
            || isset($primeiro['valor_margem'])
            || isset($primeiro['token_tabela']);
    }

    private function consultaTemMargemDireta(array $consulta): bool
    {
        if (isset($consulta[0]) && is_array($consulta[0])) {
            return false;
        }

        $mensagem = strtoupper(trim((string) ($consulta['mensagem'] ?? $consulta['message'] ?? '')));
        $temMargem = array_key_exists('valor_margem', $consulta);

        return $mensagem === 'OK' && $temMargem;
    }
    private function precisaAutorizacao(array $consulta): bool
    {
        $mensagem = $this->extrairMensagemConsulta($consulta);
        $httpCode = (int) ($consulta['http_code'] ?? 0);

        if ($httpCode === 202) {
            return true;
        }

        return $this->mensagemTemUrl($mensagem);
    }

    private function extrairUrlAutorizacao(array $consulta): string
    {
        $mensagem = $this->extrairMensagemConsulta($consulta);

        if (preg_match('/https?:\/\/\S+/i', $mensagem, $match)) {
            return trim($match[0]);
        }

        return trim($mensagem);
    }

    private function extrairMensagemConsulta(array $consulta): string
    {
        if (isset($consulta['mensagem']) && is_string($consulta['mensagem'])) {
            return trim($consulta['mensagem']);
        }

        if (isset($consulta['message']) && is_string($consulta['message'])) {
            return trim($consulta['message']);
        }

        if (isset($consulta[0]) && is_array($consulta[0])) {
            if (isset($consulta[0]['mensagem']) && is_string($consulta[0]['mensagem'])) {
                return trim($consulta[0]['mensagem']);
            }

            if (isset($consulta[0]['message']) && is_string($consulta[0]['message'])) {
                return trim($consulta[0]['message']);
            }
        }

        return '';
    }

    private function mensagemTemUrl(string $mensagem): bool
    {
        if ($mensagem === '') {
            return false;
        }

        return preg_match('/https?:\/\/\S+/i', $mensagem) === 1;
    }
    private function gerarTelefoneAleatorio(): string
    {
        $ddd = str_pad((string) random_int(11, 99), 2, '0', STR_PAD_LEFT);
        $numero = '9' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        return $ddd . $numero;
    }

    private function converterDataParaBr(string $data): string
    {
        [$ano, $mes, $dia] = explode('-', $data);
        return $dia . '/' . $mes . '/' . $ano;
    }

    private function normalizarCpf(string $cpf): string
    {
        $cpfNumerico = preg_replace('/\D/', '', $cpf);

        if (!is_string($cpfNumerico) || $cpfNumerico === '') {
            return '';
        }

        if (strlen($cpfNumerico) >= 11) {
            return $cpfNumerico;
        }

        return str_pad($cpfNumerico, 11, '0', STR_PAD_LEFT);
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

    private function adquirirLockFila(): bool
    {
        $result = DB::selectOne("
            DECLARE @resultado INT;
            EXEC @resultado = sp_getapplock
                @Resource = ?,
                @LockMode = 'Exclusive',
                @LockOwner = 'Session',
                @LockTimeout = 0;
            SELECT @resultado AS resultado;
        ", ['handmais:processar_fila']);

        return is_object($result) && isset($result->resultado) && (int) $result->resultado >= 0;
    }

    private function liberarLockFila(): void
    {
        DB::statement("
            EXEC sp_releaseapplock
                @Resource = ?,
                @LockOwner = 'Session';
        ", ['handmais:processar_fila']);
    }

    /**
     * @return array{pode_consultar: bool, resetado: bool, libera_em: ?string}
     */
    private function validarSaldoDiario(object $saldo): array
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
            $this->resetarSaldoDiario((int) $saldo->id);

            return [
                'pode_consultar' => true,
                'resetado' => true,
                'libera_em' => null,
            ];
        }

        $liberaEm = $atualizadoEm->modify('+24 hours');
        $agora = new DateTimeImmutable('now', $liberaEm->getTimezone());

        if ($agora >= $liberaEm) {
            $this->resetarSaldoDiario((int) $saldo->id);

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

    private function resetarSaldoDiario(int $saldoId): void
    {
        DB::update("
            UPDATE consultas_api.dbo.saldo_handmais
            SET consultados = 0,
                limite = ISNULL(total, 0),
                updated_at = GETDATE()
            WHERE id = ?
        ", [$saldoId]);
    }

    private function criarDataHora(mixed $valor): ?DateTimeImmutable
    {
        if (!$valor) {
            return null;
        }

        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        if (is_string($valor)) {
            $data = trim($valor);

            if ($data === '') {
                return null;
            }

            try {
                return new DateTimeImmutable($data);
            } catch (Throwable $e) {
                return null;
            }
        }

        try {
            return new DateTimeImmutable((string) $valor);
        } catch (Throwable $e) {
            return null;
        }
    }
}


