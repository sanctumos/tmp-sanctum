<?php

declare(strict_types=1);

namespace SanctumTmp;

/**
 * Distinguish browser humans from scripts, agents, and API clients.
 */
final class ClientMode
{
    /** @var list<string> */
    private const AUTOMATED_UA_FRAGMENTS = [
        'curl/',
        'sanctum-broca',
        'python-requests',
        'python-urllib',
        'go-http-client',
        'httpie',
        'wget/',
        'java/',
        'okhttp',
        'postman',
        'insomnia',
        'axios/',
        'node-fetch',
        'undici',
    ];

    public static function isAutomatedClient(): bool
    {
        $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        foreach (self::AUTOMATED_UA_FRAGMENTS as $fragment) {
            if ($ua !== '' && str_contains($ua, $fragment)) {
                return true;
            }
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if ($accept !== ''
            && str_contains($accept, 'application/json')
            && !str_contains($accept, 'text/html')) {
            return true;
        }

        return false;
    }

    public static function wantsBrowserHtml(): bool
    {
        if (self::isAutomatedClient()) {
            return false;
        }

        $mode = (string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '');
        $dest = (string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '');
        if ($mode === 'navigate' || $dest === 'document') {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'text/html')) {
            return true;
        }

        return true;
    }
}
