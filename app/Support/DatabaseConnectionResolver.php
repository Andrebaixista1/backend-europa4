<?php

namespace App\Support;

use PDO;

class DatabaseConnectionResolver
{
    /**
     * Resolve the default database connection, optionally falling back to SQLite
     * for local environments that do not have a SQL Server PDO driver installed.
     */
    public static function defaultConnection(
        ?string $defaultConnection = null,
        ?bool $fallbackToSqlite = null,
        ?array $availableDrivers = null,
    ): string {
        $defaultConnection ??= env('DB_CONNECTION', 'sqlite');
        $fallbackToSqlite ??= filter_var(env('DB_FALLBACK_TO_SQLITE', false), FILTER_VALIDATE_BOOL);
        $availableDrivers ??= class_exists(PDO::class) ? PDO::getAvailableDrivers() : [];

        return self::resolve($defaultConnection, $fallbackToSqlite, $availableDrivers);
    }

    /**
     * @param  array<int, string>  $availableDrivers
     */
    public static function resolve(
        string $defaultConnection,
        bool $fallbackToSqlite,
        array $availableDrivers,
    ): string {
        if (! $fallbackToSqlite || $defaultConnection !== 'sqlsrv') {
            return $defaultConnection;
        }

        foreach (['sqlsrv', 'dblib', 'odbc'] as $driver) {
            if (in_array($driver, $availableDrivers, true)) {
                return $defaultConnection;
            }
        }

        return 'sqlite';
    }
}
