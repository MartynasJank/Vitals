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
