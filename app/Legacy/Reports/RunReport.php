<?php

namespace App\Legacy\Reports;

final class RunReport
{
    public array $counts = [];

    public array $issues = [];

    public array $urls = [];

    public array $records = [];

    public function initialize(string $entity): void
    {
        $this->counts[$entity] ??= array_fill_keys(['READ', 'CREATED', 'SKIPPED', 'QUARANTINED', 'FAILED', 'WARNING'], 0);
    }

    public function count(string $entity, string $status): void
    {
        $this->counts[$entity] ??= array_fill_keys(['READ', 'CREATED', 'SKIPPED', 'QUARANTINED', 'FAILED', 'WARNING'], 0);
        $this->counts[$entity][$status]++;
    }

    public function issue(string $entity, int $id, string $code, array $context = [], string $level = 'WARNING'): void
    {
        $this->count($entity, $level === 'WARNING' ? 'WARNING' : 'QUARANTINED');
        $this->issues[] = ['entity' => $entity, 'legacy_id' => $id, 'code' => $code, 'level' => $level, 'context' => $context];
    }

    public function save(string $directory, array $metadata): array
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        chmod($directory, 0700);
        $summary = $metadata + ['counts' => $this->counts, 'issue_count' => count($this->issues)];
        foreach (['migration-summary.json' => $summary, 'migration-records.json' => $this->records] as $name => $data) {
            file_put_contents($directory.'/'.$name, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
            chmod($directory.'/'.$name, 0600);
        }
        foreach (['migration-errors.csv' => array_values(array_filter($this->issues, fn ($i) => $i['level'] !== 'WARNING')), 'migration-warnings.csv' => array_values(array_filter($this->issues, fn ($i) => $i['level'] === 'WARNING')), 'legacy-url-map.csv' => $this->urls] as $name => $rows) {
            $file = fopen($directory.'/'.$name, 'wb');
            chmod($directory.'/'.$name, 0600);
            if ($rows) {
                fputcsv($file, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($file, array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $row));
                }
            }
            fclose($file);
        }

        return $summary;
    }
}
