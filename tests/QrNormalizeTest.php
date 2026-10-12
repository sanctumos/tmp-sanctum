<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;
use SanctumTmp\QrNormalize;

final class QrNormalizeTest extends TestCase
{
    public function test_url_adds_https(): void
    {
        $r = QrNormalize::process('example.com', 'auto');
        $this->assertSame('url', $r['type']);
        $this->assertSame('https://example.com', $r['encoded']);
    }

    public function test_email_mailto(): void
    {
        $r = QrNormalize::process('hello@decisionsciencecorp.com', 'auto');
        $this->assertSame('email', $r['type']);
        $this->assertSame('mailto:hello@decisionsciencecorp.com', $r['encoded']);
    }

    public function test_gmail_typo_suggestion(): void
    {
        $r = QrNormalize::process('hello@gnail.com', 'email');
        $this->assertSame('mailto:hello@gnail.com', $r['encoded']);
        $this->assertSame('mailto:hello@gmail.com', $r['suggestion']);
    }

    public function test_phone(): void
    {
        $r = QrNormalize::process('+1 555 010 0123', 'tel');
        $this->assertSame('tel', $r['type']);
        $this->assertSame('tel:+15550100123', $r['encoded']);
    }

    public function test_empty_errors(): void
    {
        $r = QrNormalize::process('   ', 'auto');
        $this->assertArrayHasKey('error', $r);
    }

    public function test_url_tld_typo_suggestion(): void
    {
        $r = QrNormalize::process('https://example.con/path', 'url');
        $this->assertSame('https://example.con/path', $r['encoded']);
        $this->assertSame('https://example.com/path', $r['suggestion']);
        $this->assertSame('TLD typo', $r['suggestion_reason']);
    }

    public function test_url_invalid_host(): void
    {
        $r = QrNormalize::process('not a url at all!!!', 'url');
        $this->assertArrayHasKey('error', $r);
    }

    public function test_email_missing_at(): void
    {
        $r = QrNormalize::process('not-an-email', 'email');
        $this->assertArrayHasKey('error', $r);
    }

    public function test_phone_invalid(): void
    {
        $r = QrNormalize::process('abc', 'tel');
        $this->assertArrayHasKey('error', $r);
    }

    public function test_text_passthrough(): void
    {
        $r = QrNormalize::process('just a note', 'text');
        $this->assertSame('text', $r['type']);
        $this->assertSame('just a note', $r['encoded']);
    }

    public function test_invalid_type(): void
    {
        $r = QrNormalize::process('x', 'nope');
        $this->assertArrayHasKey('error', $r);
    }

    public function test_auto_detects_mailto_and_tel_prefixes(): void
    {
        $e = QrNormalize::process('mailto:a@b.co', 'auto');
        $this->assertSame('email', $e['type']);
        $t = QrNormalize::process('tel:+15551212', 'auto');
        $this->assertSame('tel', $t['type']);
    }
}
