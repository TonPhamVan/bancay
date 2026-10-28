<?php

namespace App\Legacy;

use Illuminate\Support\Facades\DB;

class Source
{
    public const TABLES = ['tblfooter', 'tbldanhmuc', 'tblsanpham', 'tblchuyenmuc', 'tblbaiviet', 'tblkhachhang', 'tblorder', 'tblbanner', 'tblslider', 'tbladmin', 'tblrole', 'tbl_count'];

    public function assertReadOnly(): void
    {
        $connection = DB::connection('legacy');
        $database = $connection->getDatabaseName();
        if ($connection->getConfig('host') !== 'db' || ! preg_match('/\Abancay_phase4_source(?:_[a-z0-9]+)?\z/', $database) || $database === DB::connection()->getDatabaseName()) {
            throw new MigrationFailure('UNSAFE_SOURCE_DATABASE');
        }
        $grants = implode('\n', array_map(fn ($row) => implode(' ', (array) $row), $connection->select('SHOW GRANTS')));
        if (preg_match('/GRANT (?:ALL|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|FILE|SUPER|EXECUTE|PROCESS)/i', $grants)
            || ! str_contains($grants, 'GRANT SELECT ON `'.$database.'`.*')) {
            throw new MigrationFailure('SOURCE_NOT_SELECT_ONLY');
        }
        $connection->statement('SET SESSION TRANSACTION READ ONLY');
        foreach (self::TABLES as $table) {
            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                throw new MigrationFailure('SOURCE_SCHEMA_MISMATCH', ['table' => $table]);
            }
        }
    }

    public function rows(string $table, int $from = 0, int $limit = 0): iterable
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new MigrationFailure('INVALID_SOURCE_TABLE');
        }
        $query = DB::connection('legacy')->table($table);
        $rows = $table === 'tbl_count' ? $query->cursor() : $query->where('id', '>=', $from)->lazyById(100);
        $seen = 0;
        foreach ($rows as $row) {
            if ($limit > 0 && $seen >= $limit) {
                break;
            }
            $seen++;
            yield (array) $row;
        }
    }

    public function fingerprint(): string
    {
        $hash = hash_init('sha256');
        foreach (self::TABLES as $table) {
            hash_update($hash, $table);
            foreach ($this->rows($table) as $row) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            }
        }

        return hash_final($hash);
    }

    public function find(string $table, int $id): array
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new MigrationFailure('INVALID_SOURCE_TABLE');
        }
        $row = DB::connection('legacy')->table($table)->where('id', $id)->first();
        if (! $row) {
            throw new MigrationFailure('SOURCE_CHANGED');
        }

        return (array) $row;
    }

    public function categoryRows(string $table): iterable
    {
        $byId = [];
        foreach ($this->rows($table) as $row) {
            $byId[$row['id']] = ['id' => $row['id'], 'parent' => $row['parent']];
        }
        $done = [];
        $visiting = [];
        $visit = function ($id) use (&$visit, &$done, &$visiting, $byId) {
            if (isset($done[$id])) {
                return [];
            }
            if (isset($visiting[$id])) {
                throw new MigrationFailure('CATEGORY_CYCLE', ['id' => (int) $id]);
            }
            $visiting[$id] = true;
            $row = $byId[$id];
            $result = [];
            if ($row['parent'] && isset($byId[$row['parent']])) {
                $result = $visit($row['parent']);
            }
            $done[$id] = true;
            unset($visiting[$id]);
            $result[] = $row;

            return $result;
        };
        foreach (array_keys($byId) as $id) {
            foreach ($visit($id) as $row) {
                yield $this->find($table, (int) $row['id']);
            }
        }
    }
}
