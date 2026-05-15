<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DatabaseService
{
    /**
     * @return array<int, array{name: string, size_mb: float, table_count: int, rows: int, tables: array<int, array{name: string, engine: string, rows: int, size_mb: float}>}>
     */
    public function getDatabases(): array
    {
        DB::statement('SET SESSION information_schema_stats_expiry = 0');

        $rows = DB::select("
            SELECT
                table_schema AS name,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                COUNT(*) AS table_count,
                SUM(table_rows) AS row_count
            FROM information_schema.TABLES
            WHERE table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
            GROUP BY table_schema
            ORDER BY size_mb DESC
        ");

        return array_map(fn ($row) => [
            'name' => $row->name,
            'size_mb' => (float) $row->size_mb,
            'table_count' => (int) $row->table_count,
            'rows' => (int) $row->row_count,
            'tables' => $this->getTablesForDatabase($row->name),
        ], $rows);
    }

    /**
     * @return array<int, array{name: string, engine: string, rows: int, size_mb: float}>
     */
    private function getTablesForDatabase(string $database): array
    {
        $rows = DB::select('
            SELECT
                table_name AS name,
                engine,
                table_rows AS row_count,
                ROUND((data_length + index_length) / 1024 / 1024, 3) AS size_mb
            FROM information_schema.TABLES
            WHERE table_schema = ?
            ORDER BY (data_length + index_length) DESC
        ', [$database]);

        return array_map(fn ($row) => [
            'name' => $row->name,
            'engine' => $row->engine ?? 'unknown',
            'rows' => (int) $row->row_count,
            'size_mb' => (float) $row->size_mb,
        ], $rows);
    }

    /**
     * @return array{version: string, uptime: string, connections: int, max_connections: int, threads_running: int, slow_queries: int, buffer_hit_rate: string, queries: int}
     */
    public function getServerStats(): array
    {
        $status = DB::select("
            SHOW GLOBAL STATUS WHERE Variable_name IN (
                'Uptime', 'Threads_connected', 'Threads_running', 'Queries',
                'Slow_queries', 'Innodb_buffer_pool_reads', 'Innodb_buffer_pool_read_requests'
            )
        ");

        $variables = DB::select("
            SHOW GLOBAL VARIABLES WHERE Variable_name IN ('version', 'max_connections')
        ");

        $map = [];
        foreach ($status as $row) {
            $map[$row->Variable_name] = $row->Value;
        }
        foreach ($variables as $row) {
            $map[$row->Variable_name] = $row->Value;
        }

        $poolReads = (int) ($map['Innodb_buffer_pool_reads'] ?? 0);
        $poolRequests = (int) ($map['Innodb_buffer_pool_read_requests'] ?? 1);
        $hitRate = $poolRequests > 0
            ? round((1 - $poolReads / $poolRequests) * 100, 1)
            : 100.0;

        return [
            'version' => $map['version'] ?? 'unknown',
            'uptime' => $this->formatUptime((int) ($map['Uptime'] ?? 0)),
            'connections' => (int) ($map['Threads_connected'] ?? 0),
            'max_connections' => (int) ($map['max_connections'] ?? 0),
            'threads_running' => (int) ($map['Threads_running'] ?? 0),
            'slow_queries' => (int) ($map['Slow_queries'] ?? 0),
            'buffer_hit_rate' => $hitRate.'%',
            'queries' => (int) ($map['Queries'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{id: int, user: string, db: string|null, command: string, time: int, state: string|null, info: string|null}>
     */
    public function getProcessList(): array
    {
        $rows = DB::select("
            SELECT ID, USER, DB, COMMAND, TIME, STATE, INFO
            FROM information_schema.PROCESSLIST
            WHERE COMMAND != 'Sleep'
            ORDER BY TIME DESC
        ");

        return array_map(fn ($row) => [
            'id' => (int) $row->ID,
            'user' => $row->USER,
            'db' => $row->DB,
            'command' => $row->COMMAND,
            'time' => (int) $row->TIME,
            'state' => $row->STATE,
            'info' => $row->INFO,
        ], $rows);
    }

    /**
     * @return array<int, array{query: string, schema: string|null, count: int, avg_ms: float, max_ms: float, total_ms: float}>
     */
    public function getSlowQueries(int $limit = 15): array
    {
        try {
            $rows = DB::select('
                SELECT
                    DIGEST_TEXT AS query,
                    SCHEMA_NAME AS schema_name,
                    COUNT_STAR AS count,
                    ROUND(AVG_TIMER_WAIT / 1000000000, 2) AS avg_ms,
                    ROUND(MAX_TIMER_WAIT / 1000000000, 2) AS max_ms,
                    ROUND(SUM_TIMER_WAIT / 1000000000, 2) AS total_ms
                FROM performance_schema.events_statements_summary_by_digest
                WHERE DIGEST_TEXT IS NOT NULL
                ORDER BY AVG_TIMER_WAIT DESC
                LIMIT ?
            ', [$limit]);

            return array_map(fn ($row) => [
                'query' => $row->query,
                'schema' => $row->schema_name,
                'count' => (int) $row->count,
                'avg_ms' => (float) $row->avg_ms,
                'max_ms' => (float) $row->max_ms,
                'total_ms' => (float) $row->total_ms,
            ], $rows);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * @return array{active_transactions: int, lock_waits: int, history_list_length: int|null, last_deadlock: string|null}
     */
    public function getInnoDbStatus(): array
    {
        $activeTrx = (int) (DB::selectOne('SELECT COUNT(*) AS count FROM information_schema.INNODB_TRX')->count ?? 0);

        try {
            $lockWaits = (int) (DB::selectOne('SELECT COUNT(*) AS count FROM performance_schema.data_lock_waits')->count ?? 0);
        } catch (\Exception) {
            $lockWaits = 0;
        }

        $statusRow = DB::selectOne('SHOW ENGINE INNODB STATUS');
        $raw = $statusRow->Status ?? '';

        $historyListLength = null;
        if (preg_match('/History list length (\d+)/', $raw, $m)) {
            $historyListLength = (int) $m[1];
        }

        $lastDeadlock = null;
        if (preg_match('/LATEST DETECTED DEADLOCK\n-+\n(.*?)(?=\n-{4,})/s', $raw, $m)) {
            $lastDeadlock = trim($m[1]);
        }

        return [
            'active_transactions' => $activeTrx,
            'lock_waits' => $lockWaits,
            'history_list_length' => $historyListLength,
            'last_deadlock' => $lastDeadlock,
        ];
    }

    private function formatUptime(int $seconds): string
    {
        if ($seconds < 3600) {
            return (int) ($seconds / 60).'m';
        }

        if ($seconds < 86400) {
            return (int) ($seconds / 3600).'h '.(int) (($seconds % 3600) / 60).'m';
        }

        return (int) ($seconds / 86400).'d '.(int) (($seconds % 86400) / 3600).'h';
    }
}
