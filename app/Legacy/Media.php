<?php

namespace App\Legacy;

use App\Legacy\Reports\RunReport;
use Illuminate\Support\Facades\Storage;
use Mews\Purifier\Facades\Purifier;

final class Media
{
    public array $mapping = [];

    public array $created = [];

    private array $cache = [];

    public function __construct(private ReviewOverrides $review = new ReviewOverrides) {}

    public function image(?string $relative, string $folder, bool $write, RunReport $report, string $entity, int $id): ?string
    {
        if (! $relative) {
            return null;
        }
        $root = realpath(config('legacy.media_root') ?: '');
        $relative = rawurldecode(ltrim($relative, '/'));
        $resolved = $this->review->mediaPath($relative);
        $path = $root ? realpath($root.'/'.$resolved) : false;
        if (! $root || ! $path || ! str_starts_with($path, $root.'/') || ! is_file($path) || preg_match('/[\x00-\x1f]/', $relative)) {
            $report->issue($entity, $id, 'MISSING_FILE', ['path_hash' => hash('sha256', $relative)]);

            return null;
        }
        $info = $this->cache[$relative] ?? null;
        if ($info === null) {
            $dimensions = @getimagesize($path);
            $mime = $dimensions['mime'] ?? '';
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || filesize($path) > 20000000
                || $dimensions[0] > 8000 || $dimensions[1] > 8000 || $dimensions[0] * $dimensions[1] > 25000000) {
                $report->issue($entity, $id, 'UNSUPPORTED_MEDIA', ['path_hash' => hash('sha256', $relative)]);

                return null;
            }
            $image = @imagecreatefromstring(file_get_contents($path));
            if (! $image) {
                $report->issue($entity, $id, 'INVALID_IMAGE');

                return null;
            }
            imagedestroy($image);
            $info = ['source_sha256' => hash_file('sha256', $path), 'mime' => $mime, 'width' => $dimensions[0], 'height' => $dimensions[1]];
            $this->cache[$relative] = $info;
        }
        $target = $folder.'/legacy/'.$info['source_sha256'].($info['mime'] === 'image/gif' ? '.gif' : '.webp');
        $map = $info + ['legacy_path' => $relative, 'resolved_path' => $resolved, 'new_path' => $target];
        if ($write) {
            $disk = Storage::disk('public');
            if ($info['mime'] === 'image/gif') {
                $bytes = file_get_contents($path);
            } else {
                $image = imagecreatefromstring(file_get_contents($path));
                imagepalettetotruecolor($image);
                imagealphablending($image, false);
                imagesavealpha($image, true);
                ob_start();
                try {
                    if (! imagewebp($image, null, 85)) {
                        throw new MigrationFailure('IMAGE_ENCODING_FAILED');
                    } $bytes = ob_get_contents();
                } finally {
                    ob_end_clean();
                    imagedestroy($image);
                }
            }
            $map['target_sha256'] = hash('sha256', $bytes);
            if ($disk->exists($target)) {
                if (hash_file('sha256', $disk->path($target)) !== $map['target_sha256']) {
                    throw new MigrationFailure('MEDIA_COLLISION');
                }
            } else {
                if (! $disk->put($target, $bytes)) {
                    throw new MigrationFailure('MEDIA_WRITE_FAILED');
                }
                $this->created[] = $target;
            }
        }
        $this->mapping[$relative.'|'.$target] = $map;

        return $target;
    }

    public function html(?string $html, bool $write, RunReport $report, string $entity, int $id): string
    {
        $html ??= '';
        if ($html === '') {
            return '';
        }
        if (preg_match('/<(?:script|iframe|object|embed)\b|\bon[a-z]+\s*=/i', $html)) {
            $report->issue($entity, $id, 'CONTENT_REWRITE_REQUIRED', ['action' => 'unsafe_html_removed']);
        }
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        try {
            $dom->loadHTML('<?xml encoding="utf-8"?><div id="legacy-content-root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            foreach ($dom->getElementsByTagName('*') as $element) {
                foreach (['srcset', 'data-srcset'] as $attribute) {
                    if ($element->hasAttribute($attribute)) {
                        $report->issue($entity, $id, 'CONTENT_REWRITE_REQUIRED', ['attribute' => $attribute]);
                        $element->removeAttribute($attribute);
                    }
                }
                if (preg_match('/url\s*\(|expression\s*\(/i', $element->getAttribute('style'))) {
                    $element->removeAttribute('style');
                    $report->issue($entity, $id, 'CONTENT_REWRITE_REQUIRED', ['attribute' => 'style']);
                }
                if ($element->tagName === 'img' && ! $element->hasAttribute('src') && $element->hasAttribute('data-src')) {
                    $element->setAttribute('src', $element->getAttribute('data-src'));
                }
                foreach (['src', 'poster'] as $attribute) {
                    if (! $element->hasAttribute($attribute)) {
                        continue;
                    }
                    $url = html_entity_decode($element->getAttribute($attribute), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $parts = parse_url($url);
                    $host = strtolower($parts['host'] ?? '');
                    $relative = ltrim($parts['path'] ?? '', '/');
                    $local = in_array($host, ['', 'bancay.vn', 'www.bancay.vn'], true) && preg_match('#\A(?:upload|images)/#', $relative);
                    $path = $local ? $this->image($relative, 'articles', $write, $report, $entity, $id) : null;
                    $element->setAttribute($attribute, $path ? '/storage/'.$path : '/storefront/fallback.svg');
                    if (! $local) {
                        $report->issue($entity, $id, 'CONTENT_REWRITE_REQUIRED', ['action' => 'external_or_unrecognized_media_blocked']);
                    }
                }
                $element->removeAttribute('data-src');
            }
            $body = $dom->getElementById('legacy-content-root');
            $output = '';
            if ($body) {
                foreach ($body->childNodes as $child) {
                    $output .= $dom->saveHTML($child);
                }
            }

            return Purifier::clean($output);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
