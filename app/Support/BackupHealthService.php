<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BackupHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $generatedAt = now()->toIso8601String();
        $servers = [];
        $partial = false;

        foreach ($this->connections() as $serverName => $connectionName) {
            try {
                $servers[] = $this->collectConnectionHealth($serverName, $connectionName, $generatedAt);
            } catch (Throwable $e) {
                $partial = true;
                Log::warning('Falha ao coletar health de backup.', [
                    'server' => $serverName,
                    'connection' => $connectionName,
                    'error' => $e->getMessage(),
                ]);

                $servers[] = [
                    'name_database' => $serverName,
                    'latest_backup' => null,
                    'quantity_databases' => 0,
                    'pending' => [],
                    'errors' => [
                        [
                            'message' => 'Nao foi possivel consultar este servidor.',
                            'detail' => $e->getMessage(),
                        ],
                    ],
                    'running_backup_count' => 0,
                    'daily' => $this->emptyPeriod(),
                    'weekly' => $this->emptyPeriod(),
                    'monthly' => $this->emptyPeriod(),
                    'collected_at' => $generatedAt,
                    'backed_up_databases' => [],
                    'databases_last_backup' => [],
                ];
            }
        }

        return [
            'generated_at' => $generatedAt,
            'servers' => $servers,
            'meta' => [
                'source' => 'laravel-backup-health',
                'partial' => $partial,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forceBackup(string $serverName, string $type = 'daily', array $pending = []): array
    {
        if (!config('backups.force_backup_enabled', false)) {
            throw new \RuntimeException('Force backup desabilitado nesta API.');
        }

        $connections = $this->connections();
        $connectionName = $connections[$serverName] ?? null;

        if ($connectionName === null) {
            throw new \InvalidArgumentException('Servidor informado e invalido.');
        }

        $backupDir = trim((string) config('backups.force_backup_dir', ''));
        if ($backupDir === '') {
            throw new \RuntimeException('Diretorio de backup nao configurado.');
        }

        $type = in_array($type, ['daily', 'weekly', 'monthly'], true) ? $type : 'daily';
        $databaseNames = $this->resolveDatabaseNames($connectionName, $pending);
        $executed = [];

        foreach ($databaseNames as $databaseName) {
            $fileName = sprintf(
                '%s_%s_%s.bak',
                Str::slug($serverName, '_'),
                Str::slug($databaseName, '_'),
                now()->format('Ymd_His')
            );

            $diskPath = rtrim($backupDir, "\\/") . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $fileName;
            $sql = sprintf(
                "BACKUP DATABASE [%s] TO DISK = N'%s' WITH INIT, COMPRESSION, STATS = 10",
                str_replace(']', ']]', $databaseName),
                str_replace("'", "''", $diskPath)
            );

            DB::connection($connectionName)->statement($sql);

            $executed[] = [
                'database' => $databaseName,
                'type' => $type,
                'path' => $diskPath,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Backup executado com sucesso.',
            'server' => $serverName,
            'type' => $type,
            'executed' => $executed,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function connections(): array
    {
        return config('backups.connections', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectConnectionHealth(string $serverName, string $connectionName, string $generatedAt): array
    {
        $databases = $this->fetchDatabaseBackupRows($connectionName);
        $databaseCount = count($databases);
        $runningBackupCount = $this->fetchRunningBackupCount($connectionName);
        $backupStats = $this->fetchBackupStats($connectionName);
        $staleHours = (int) config('backups.pending_stale_hours', 36);
        $threshold = now()->subHours(max(1, $staleHours));

        $pending = [];
        $errors = [];
        $backedUpDatabases = [];
        $databasesLastBackup = [];
        $latestBackup = null;

        foreach ($databases as $database) {
            $name = (string) Arr::get($database, 'name', '');
            $state = strtoupper((string) Arr::get($database, 'state_desc', ''));
            $lastBackup = Arr::get($database, 'last_backup');
            $lastBackupIso = $this->normalizeDate($lastBackup);

            if ($state !== 'ONLINE') {
                $errors[] = [
                    'database' => $name,
                    'message' => "Banco fora do ar: {$state}",
                ];
            }

            if ($lastBackupIso !== null) {
                $backedUpDatabases[] = [
                    'name_database' => $name,
                    'last_backup' => $lastBackupIso,
                ];
                $databasesLastBackup[] = [
                    'name_database' => $name,
                    'last_backup' => $lastBackupIso,
                ];

                if ($latestBackup === null || strcmp($lastBackupIso, $latestBackup) > 0) {
                    $latestBackup = $lastBackupIso;
                }
            } else {
                $pending[] = $name;
            }

            if ($lastBackupIso !== null && Carbon::parse($lastBackupIso)->lt($threshold)) {
                $pending[] = $name;
            }
        }

        $pending = array_values(array_unique(array_filter($pending)));

        return [
            'name_database' => $serverName,
            'latest_backup' => $latestBackup,
            'quantity_databases' => $databaseCount,
            'pending' => $pending,
            'errors' => $errors,
            'running_backup_count' => $runningBackupCount,
            'daily' => $this->normalizePeriod($backupStats['daily'] ?? null),
            'weekly' => $this->normalizePeriod($backupStats['weekly'] ?? null),
            'monthly' => $this->normalizePeriod($backupStats['monthly'] ?? null),
            'collected_at' => $generatedAt,
            'backed_up_databases' => $backedUpDatabases,
            'databases_last_backup' => $databasesLastBackup,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDatabaseBackupRows(string $connectionName): array
    {
        $sql = <<<'SQL'
SELECT
    d.name,
    d.state_desc,
    d.create_date,
    (
        SELECT MAX(bs.backup_finish_date)
        FROM msdb.dbo.backupset bs
        WHERE bs.database_name = d.name
          AND bs.type = 'D'
    ) AS last_backup
FROM sys.databases d
WHERE d.database_id > 4
ORDER BY d.name
SQL;

        return array_map(
            static fn ($row) => (array) $row,
            DB::connection($connectionName)->select($sql)
        );
    }

    private function fetchRunningBackupCount(string $connectionName): int
    {
        try {
            $row = DB::connection($connectionName)->selectOne(<<<'SQL'
SELECT COUNT(*) AS total
FROM sys.dm_exec_requests
WHERE command LIKE 'BACKUP%'
SQL);

            return (int) ($row->total ?? 0);
        } catch (Throwable $e) {
            Log::info('Nao foi possivel consultar backups em execucao.', [
                'connection' => $connectionName,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchBackupStats(string $connectionName): array
    {
        $periods = [
            'daily' => '-1 DAY',
            'weekly' => '-7 DAY',
            'monthly' => '-30 DAY',
        ];

        $stats = [];

        foreach ($periods as $key => $modifier) {
            $sql = <<<SQL
SELECT
    COUNT(*) AS quantity,
    COALESCE(SUM(DATEDIFF(SECOND, bs.backup_start_date, bs.backup_finish_date)) / 3600.0, 0) AS timer_hours,
    MIN(bs.backup_start_date) AS first_start,
    MAX(bs.backup_finish_date) AS last_finish
FROM msdb.dbo.backupset bs
WHERE bs.type = 'D'
  AND bs.backup_start_date >= DATEADD(DAY, {$this->modifierToDays($modifier)}, GETDATE())
SQL;

            try {
                $row = DB::connection($connectionName)->selectOne($sql);
                $stats[$key] = (array) $row;
            } catch (Throwable $e) {
                $stats[$key] = $this->emptyPeriod();
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPeriod(): array
    {
        return [
            'quantity' => 0,
            'timer_hours' => 0,
            'first_start' => null,
            'last_finish' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $period
     * @return array<string, mixed>
     */
    private function normalizePeriod(?array $period): array
    {
        $period = $period ?? $this->emptyPeriod();

        return [
            'quantity' => (int) ($period['quantity'] ?? 0),
            'timer_hours' => round((float) ($period['timer_hours'] ?? 0), 2),
            'first_start' => $this->normalizeDate($period['first_start'] ?? null),
            'last_finish' => $this->normalizeDate($period['last_finish'] ?? null),
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function modifierToDays(string $modifier): int
    {
        return match ($modifier) {
            '-1 DAY' => -1,
            '-7 DAY' => -7,
            '-30 DAY' => -30,
            default => -1,
        };
    }

    /**
     * @return array<int, string>
     */
    private function resolveDatabaseNames(string $connectionName, array $pending = []): array
    {
        $available = collect($this->fetchDatabaseBackupRows($connectionName))
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values();

        if ($available->isEmpty()) {
            throw new \RuntimeException('Nenhum banco encontrado para backup.');
        }

        if ($pending === []) {
            return $available->all();
        }

        $selected = $available->intersect($pending)->values()->all();
        if ($selected === []) {
            throw new \InvalidArgumentException('Nenhum banco solicitado foi encontrado neste servidor.');
        }

        return $selected;
    }
}
