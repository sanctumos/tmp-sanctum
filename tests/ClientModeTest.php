<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;
use SanctumTmp\ClientMode;

final class ClientModeTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_SEC_FETCH_MODE'], $_SERVER['HTTP_SEC_FETCH_DEST']);
    }

    public function test_curl_is_automated(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'curl/8.5.0';
        $this->assertTrue(ClientMode::isAutomatedClient());
        $this->assertFalse(ClientMode::wantsBrowserHtml());
    }

    public function test_broca_is_automated(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Sanctum-Broca/1.0 (ephemeral upload)';
        $this->assertTrue(ClientMode::isAutomatedClient());
    }

    public function test_browser_navigate_wants_html(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate';
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
        $this->assertFalse(ClientMode::isAutomatedClient());
        $this->assertTrue(ClientMode::wantsBrowserHtml());
    }

    public function test_json_accept_is_automated(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'custom-agent/1.0';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->assertTrue(ClientMode::isAutomatedClient());
    }
}
