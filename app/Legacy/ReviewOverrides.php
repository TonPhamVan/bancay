<?php

namespace App\Legacy;

/** Explicit, private local-staging decisions; never alter source rows or infer permissions. */
final class ReviewOverrides
{
    private array $data = [];

    public function __construct(bool $enabled = true)
    {
        $file = config('legacy.review_manifest');
        if (! $enabled || ! $file || ! is_file($file) || ! config('legacy.enabled')) {
            return;
        }
        $private = realpath(base_path('legacy-private'));
        $path = realpath($file);
        if (! app()->environment(['local', 'testing']) || ! $private || ! str_starts_with($path, $private.'/')) {
            throw new MigrationFailure('UNSAFE_REVIEW_MANIFEST');
        }
        $this->data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($this->data['approval'] ?? '') !== 'USER APPROVED'
            || ($this->data['target_database'] ?? '') !== config('legacy.target_database')) {
            throw new MigrationFailure('UNAPPROVED_REVIEW_MANIFEST');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->data, JSON_THROW_ON_ERROR));
    }

    public function assertSource(string $fingerprint): void
    {
        if ($this->data && ($this->data['source_fingerprint'] ?? '') !== $fingerprint) {
            throw new MigrationFailure('REVIEW_SOURCE_CHANGED');
        }
    }

    public function category(string $entity, array $row): int
    {
        $mapping = $this->data['categories'][$entity][(string) $row['id']] ?? null;
        if ($mapping === null) {
            return (int) $row['cid'];
        }
        if ((int) $row['cid'] !== $mapping['from'] || ! is_int($mapping['to']) || $mapping['to'] <= 0) {
            throw new MigrationFailure('REVIEW_MAPPING_CHANGED');
        }

        return $mapping['to'];
    }

    public function mediaPath(string $relative): string
    {
        $mapping = $this->data['media'][$relative] ?? null;
        if ($mapping === null) {
            return $relative;
        }
        $root = realpath(config('legacy.media_root') ?: '');
        $path = $root ? realpath($root.'/'.$mapping['path']) : false;
        if (! $root || ! $path || ! str_starts_with($path, $root.'/') || ! is_file($path)
            || is_file($root.'/'.$relative) || hash_file('sha256', $path) !== $mapping['sha256']) {
            throw new MigrationFailure('REVIEW_MEDIA_CHANGED');
        }

        return $mapping['path'];
    }

    public function preservesHistoricalNull(int $orderId, int $productId): bool
    {
        return isset($this->data['preserve_historical_null'][(string) $orderId])
            && $this->data['preserve_historical_null'][(string) $orderId] === $productId;
    }

    public function contentRepairs(): array
    {
        return $this->data['content_repairs'] ?? [];
    }

    public function galleryRepairs(): array
    {
        return $this->data['gallery_repairs'] ?? [];
    }
}
