<?php

namespace Tests\Unit\Cms;

use App\Services\Content\ContentSanitizer;
use App\Services\Content\Exceptions\ContentSanitizationException;
use Tests\TestCase;

/**
 * IMP-005 slice 9 (ContentSanitizer) coverage, mirroring docs/implementation/
 * IMP-005-cms.md section 29's T-series + section 28's XSS vector list.
 */
class ContentSanitizerTest extends TestCase
{
    private function sanitizer(): ContentSanitizer
    {
        return app(ContentSanitizer::class);
    }

    private function validUlid(): string
    {
        return '01J9ZK3V7Q4XW2N8M5R6T7B1C2';
    }

    public function test_valid_media_placeholder_round_trips_verbatim(): void
    {
        $ulid = $this->validUlid();
        $html = "<p>Hello <img data-media=\"{$ulid}\" alt=\"Volunteers\"></p>";

        $result = $this->sanitizer()->sanitize($html);

        $this->assertStringContainsString("data-media=\"{$ulid}\"", $result);
        $this->assertStringContainsString('alt="Volunteers"', $result);
    }

    public function test_invalid_media_token_is_rejected_not_stripped(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<img data-media="not-a-ulid">');
    }

    public function test_missing_media_token_on_img_is_rejected(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<img alt="no token">');
    }

    public function test_author_supplied_src_on_img_is_rejected_via_missing_token(): void
    {
        // src is never in the allow-list, so it is stripped — but the
        // resulting img has no data-media, which IS a reject (never a
        // silently-imageless tag).
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<img src="https://evil.example/x.png">');
    }

    public function test_src_is_never_emitted_even_when_data_media_is_present(): void
    {
        $ulid = $this->validUlid();
        $result = $this->sanitizer()->sanitize("<img data-media=\"{$ulid}\" src=\"https://evil.example/x.png\">");

        $this->assertStringNotContainsString('src=', $result);
    }

    public function test_script_tag_is_rejected_outright(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<p>hi</p><script>alert(1)</script>');
    }

    public function test_iframe_is_rejected_outright(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<iframe src="https://evil.example"></iframe>');
    }

    public function test_svg_with_embedded_script_is_rejected_outright(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<svg><script>alert(1)</script></svg>');
    }

    public function test_object_and_embed_are_rejected_outright(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<object data="evil.swf"></object>');
    }

    public function test_event_handler_attributes_are_stripped(): void
    {
        $result = $this->sanitizer()->sanitize('<p onclick="alert(1)">hi</p>');

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('hi', $result);
    }

    public function test_javascript_href_is_rejected(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<a href="javascript:alert(1)">click</a>');
    }

    public function test_data_uri_href_is_rejected(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<a href="data:text/html,<script>alert(1)</script>">click</a>');
    }

    public function test_https_href_is_preserved(): void
    {
        $result = $this->sanitizer()->sanitize('<a href="https://example.com/page">link</a>');

        $this->assertStringContainsString('href="https://example.com/page"', $result);
    }

    public function test_relative_root_path_href_is_rejected(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<a href="/">home</a>');
    }

    public function test_relative_reserved_prefix_href_is_rejected(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<a href="/admin/settings">admin</a>');
    }

    public function test_anchor_cannot_carry_both_href_and_data_media(): void
    {
        $ulid = $this->validUlid();
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize("<a href=\"https://example.com\" data-media=\"{$ulid}\">x</a>");
    }

    public function test_document_link_via_data_media_has_no_href(): void
    {
        $ulid = $this->validUlid();
        $result = $this->sanitizer()->sanitize("<a data-media=\"{$ulid}\">Download</a>");

        $this->assertStringContainsString("data-media=\"{$ulid}\"", $result);
        $this->assertStringNotContainsString('href=', $result);
    }

    public function test_unknown_tag_is_unwrapped_children_promoted(): void
    {
        $result = $this->sanitizer()->sanitize('<div><p>kept</p></div>');

        $this->assertStringNotContainsString('<div', $result);
        $this->assertStringContainsString('<p>kept</p>', $result);
    }

    public function test_style_attribute_is_stripped(): void
    {
        $result = $this->sanitizer()->sanitize('<p style="background:url(javascript:alert(1))">hi</p>');

        $this->assertStringNotContainsString('style', $result);
    }

    public function test_allowed_tags_and_text_survive(): void
    {
        $html = '<p>Para</p><h1>Heading</h1><ul><li>Item</li></ul><strong>bold</strong><blockquote>quote</blockquote>';

        $result = $this->sanitizer()->sanitize($html);

        foreach (['<p>Para</p>', '<h1>Heading</h1>', '<li>Item</li>', '<strong>bold</strong>'] as $expected) {
            $this->assertStringContainsString($expected, $result);
        }
    }

    public function test_sanitizing_an_already_sanitized_body_is_idempotent(): void
    {
        $ulid = $this->validUlid();
        $once = $this->sanitizer()->sanitize("<p>Text <img data-media=\"{$ulid}\" alt=\"x\"></p>");
        $twice = $this->sanitizer()->sanitize($once);

        $this->assertSame($once, $twice);
    }

    public function test_body_length_cap_is_enforced(): void
    {
        $this->expectException(ContentSanitizationException::class);
        $this->sanitizer()->sanitize('<p>'.str_repeat('a', 200 * 1024 + 1).'</p>');
    }
}
