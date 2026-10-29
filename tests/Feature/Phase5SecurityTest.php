<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminOperationLog;
use App\Services\Storefront\ContentHtml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Tests\TestCase;

final class Phase5SecurityTest extends TestCase
{
    public function test_mime_rejects_parameter_name_header_injection_on_every_entry_point(): void
    {
        foreach (["filename\r\nBcc: injected", 'name; injected', 'bad:name', 'bad name', 'name"'] as $name) {
            foreach (['constructor', 'single', 'bulk'] as $method) {
                try {
                    $header = new ParameterizedHeader('Content-Disposition', 'attachment', $method === 'constructor' ? [$name => 'test'] : []);
                    if ($method === 'single') {
                        $header->setParameter($name, 'test');
                    } elseif ($method === 'bulk') {
                        $header->setParameters([$name => 'test']);
                    }
                    $this->fail('Unsafe MIME parameter name accepted');
                } catch (RfcComplianceException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
        $safe = new ParameterizedHeader('Content-Disposition', 'attachment', ['filename' => 'cây cảnh.png']);
        $this->assertStringContainsString('filename', $safe->toString());
    }

    public function test_v7_operation_log_masks_actual_recursive_input_api_and_reset_path(): void
    {
        $result = AdminOperationLog::maskInputArray(['nested' => ['PASSWORD' => 'synthetic-password', 'checkout_token' => 'synthetic-checkout',
            'Api_Key' => 'synthetic-api', 'name' => 'Allowed business field'], '_token' => 'synthetic-csrf'], 'admin/plugins/bancay_settings/settings');
        $this->assertSame('***', $result['nested']['PASSWORD']);
        $this->assertSame('***', $result['nested']['checkout_token']);
        $this->assertSame('***', $result['nested']['Api_Key']);
        $this->assertSame('***', $result['_token']);
        $this->assertSame('Allowed business field', $result['nested']['name']);
        $this->assertStringNotContainsString('synthetic-reset-token', AdminOperationLog::hidePathParams('admin/auth/reset/synthetic-reset-token'));
    }

    public function test_content_images_are_sanitized_dimensioned_and_lazy_without_fetching_remote_sources(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('image.png', 64, 32);
        Storage::disk('public')->put('articles/test.png', file_get_contents($file->getRealPath()));
        $html = app(ContentHtml::class)->render('<p>Cây &amp; lá</p><img src="/storage/articles/test.png" alt="Cây" onerror="alert(1)"><script>alert(1)</script>');
        $this->assertStringContainsString('Cây &amp; lá', $html);
        $this->assertStringContainsString('width="64"', $html);
        $this->assertStringContainsString('height="32"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<script', $html);
        $remote = app(ContentHtml::class)->render('<img src="https://example.test/never-fetch.png" alt="external">');
        $this->assertStringNotContainsString('width=', $remote);
    }
}
