<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DatabaseService
{
    /**
     * @return array<int, array{name: string, size_mb: float, tables: int, rows: int}>
     */
    public function getDatabases(): array
    {
        $rows = DB::select("
            SELECT
                table_schema AS name,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                COUNT(*) AS tables,
                SUM(table_rows) AS rows
            FROM information_schema.TABLES
            WHERE table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
            GROUP BY table_schema
            ORDER BY size_mb DESC
        ");

        return array_map(fn ($row) => [
            'name' => $row->name,
            'size_mb' => (float) $row->size_mb,
            'tables' => (int) $row->tables,
            'rows' => (int) $row->rows,
        ], $rows);
    }

    /**
     * @return array{version: string, uptime: string, connections: int, queries: int}
     */
    public function getServerStats(): array
    {
        $status = DB::select("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime', 'Threads_connected', 'Queries')");
        $variables = DB::select("SHOW GLOBAL VARIABLES WHERE Variable_name = 'version'");

        $map = [];
        foreach ($status as $row) {
            $map[$row->Variable_name] = $row->Value;
        }
        foreach ($variables as $row) {
            $map[$row->Variable_name] = $row->Value;
        }

        return [
            'version' => $map['version'] ?? 'unknown',
            'uptime' => $this->formatUptime((int) ($map['Uptime'] ?? 0)),
            'connections' => (int) ($map['Threads_connected'] ?? 0),
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
