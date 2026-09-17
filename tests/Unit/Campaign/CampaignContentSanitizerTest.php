<?php

namespace Tests\Unit\Campaign;

use App\Services\Campaign\CampaignContentSanitizer;
use App\Services\Campaign\Exceptions\CampaignValidationException;
use Tests\TestCase;

/**
 * IMP-007 remediation — description_html was previously persisted
 * completely unsanitized (a real stored-XSS gap, since it is rendered via
 * v-html on public Campaign/Program pages). Adversarial coverage mirrors
 * the discipline already applied to IMP-005's ContentSanitizer.
 */
class CampaignContentSanitizerTest extends TestCase
{
    private function sanitizer(): CampaignContentSanitizer
    {
        return new CampaignContentSanitizer;
    }

    public function test_script_tag_is_rejected(): void
    {
        $this->expectException(CampaignValidationException::class);
        $this->sanitizer()->sanitize('<p>hi</p><script>alert(1)</script>');
    }

    public function test_onerror_and_other_disallowed_attributes_are_stripped_not_the_element(): void
    {
        $result = $this->sanitizer()->sanitize('<p onclick="alert(1)">hello</p>');

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function test_javascript_href_scheme_is_rejected(): void
    {
        $this->expectException(CampaignValidationException::class);
        $this->sanitizer()->sanitize('<a href="javascript:alert(1)">click</a>');
    }

    public function test_img_tag_is_never_permitted(): void
    {
        // Campaign/Program media stays in the separate Media Library
        // gallery, never inline via the editor.
        $this->expectException(CampaignValidationException::class);
        $this->sanitizer()->sanitize('<img src="x" onerror="alert(1)">');
    }

    public function test_allowed_tags_pass_through(): void
    {
        $html = '<h2>Title</h2><p>Some <strong>bold</strong> and <em>italic</em> text.</p>'
            .'<ul><li>one</li><li>two</li></ul><blockquote>quote</blockquote>'
            .'<a href="https://example.com">link</a>';

        $result = $this->sanitizer()->sanitize($html);

        $this->assertStringContainsString('<h2>Title</h2>', $result);
        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringContainsString('href="https://example.com"', $result);
    }

    public function test_unknown_tag_is_unwrapped_not_rejected(): void
    {
        $result = $this->sanitizer()->sanitize('<marquee>hello</marquee>');

        $this->assertStringNotContainsString('marquee', $result);
        $this->assertStringContainsString('hello', $result);
    }
}
