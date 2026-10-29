<?php

namespace App\Services\Storefront;

use App\Rules\PublicImagePath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

final class ContentHtml
{
    /** Sanitize first; only inspect validated local files, never fetch an editor's URL. */
    public function render(?string $html): string
    {
        $clean = Purifier::clean($html ?? '');
        if (! str_contains($clean, '<img')) {
            return $clean;
        }
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div id="store-content-root">'.$clean.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($document->getElementsByTagName('img') as $image) {
                $image->setAttribute('loading', 'lazy');
                $image->setAttribute('decoding', 'async');
                $source = $image->getAttribute('src');
                if ($source === '/storefront/fallback.svg') {
                    $image->setAttribute('width', '640');
                    $image->setAttribute('height', '640');
                }
                if (! str_starts_with($source, '/storage/')) {
                    continue;
                }
                $path = substr($source, strlen('/storage/'));
                if (Validator::make(['path' => $path], ['path' => [new PublicImagePath]])->fails() || ! Storage::disk('public')->exists($path)) {
                    continue;
                }
                if (! $image->hasAttribute('width') || ! $image->hasAttribute('height')) {
                    $size = @getimagesize(Storage::disk('public')->path($path));
                    if ($size) {
                        $image->setAttribute('width', (string) $size[0]);
                        $image->setAttribute('height', (string) $size[1]);
                    }
                }
            }
            $result = '';
            foreach ($document->getElementById('store-content-root')->childNodes as $node) {
                $result .= $document->saveHTML($node);
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
