<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Tests\Support\ExmentAdminTestCase;

final class AdminFormEncodingTest extends ExmentAdminTestCase
{
    public function test_native_product_form_preserves_literal_entities_and_contains_untrusted_text(): void
    {
        $this->loginAsRole('product_manager');
        $values = ['name' => 'Plant &amp; Notes &quot;01&quot;', 'meta_title' => 'A &amp; B',
            'short_description' => '<p>A &amp; B &lt;literal&gt;</p>',
            'description' => '<p>&quot;Quoted&quot;</p></textarea><script id="encoding-test-injected">bad()</script>'];
        $product = Product::factory()->create($values);
        $response = $this->get($this->storeUrl('catalog', 'products/'.$product->id.'/edit'))->assertOk();
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument;
            $dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            foreach ($values as $field => $expected) {
                $node = $xpath->query('//*[@id="field_'.$field.'"]')->item(0);
                $actual = $node->nodeName === 'textarea' ? $node->textContent : $node->getAttribute('value');
                $this->assertSame($expected, $actual, $field.' must survive HTML form rendering unchanged');
            }
            $this->assertSame(0, $xpath->query('//*[@id="encoding-test-injected"]')->length);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $this->assertSame($values['description'], $product->fresh()->description);
    }

    public function test_native_settings_input_preserves_literal_entities_and_quotes(): void
    {
        $this->loginAsRole(admin: true);
        $value = "Garden &amp; \"Friends\" &lt;tag&gt;\nSecond line";
        Setting::updateOrCreate(['key' => 'meta_description'], ['value' => $value]);
        $response = $this->get($this->storeUrl('settings', 'settings'))->assertOk();
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument;
            $dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame($value, $xpath->query('//*[@id="setting_meta_description"]')->item(0)->textContent);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
