<?php

declare(strict_types=1);

namespace SanctumTmp;

/**
 * Content normalization for QR payloads (ported from QR Code Studio, AGPL-3.0).
 * Suggests https://, TLD fixes, common email-domain typos.
 */
final class QrNormalize
{
    private const KNOWN_EMAIL_DOMAINS = [
        'gmail.com', 'googlemail.com',
        'yahoo.com', 'yahoo.co.uk', 'ymail.com',
        'hotmail.com', 'hotmail.co.uk',
        'outlook.com', 'live.com', 'msn.com',
        'icloud.com', 'me.com', 'mac.com',
        'aol.com',
        'proton.me', 'protonmail.com',
        'fastmail.com', 'pm.me',
        'zoho.com', 'gmx.com', 'mail.com',
        'duck.com',
        'comcast.net', 'verizon.net', 'att.net', 'sbcglobal.net',
        'decisionsciencecorp.com',
    ];

    private const TLD_FIXES = [
        'con' => 'com', 'cmo' => 'com', 'cm' => 'com', 'om' => 'com', 'comm' => 'com', 'cim' => 'com',
        'og' => 'org', 'or' => 'org', 'orgg' => 'org',
        'ent' => 'net', 'nt' => 'net', 'nett' => 'net',
        'ed' => 'edu', 'edy' => 'edu',
        'gv' => 'gov',
        'ko' => 'co',
        'iio' => 'io', 'oi' => 'io',
        'cu' => 'us',
    ];

    /** @return array{encoded?:string,type?:string,suggestion?:?string,suggestion_reason?:?string,error?:?string} */
    public static function process(string $raw, string $forcedType = 'auto'): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return ['error' => 'Content is required.', 'type' => $forcedType];
        }

        $type = $forcedType !== '' ? $forcedType : 'auto';
        if (!in_array($type, ['auto', 'url', 'email', 'tel', 'text'], true)) {
            return ['error' => 'Invalid type. Use auto, url, email, tel, or text.', 'type' => $type];
        }

        if ($type === 'auto') {
            if (preg_match('/^mailto:/i', $trimmed) || self::looksLikeEmail(preg_replace('/^mailto:/i', '', $trimmed))) {
                $type = 'email';
            } elseif (preg_match('/^tel:/i', $trimmed) || self::looksLikePhone($trimmed)) {
                $type = 'tel';
            } elseif (self::looksLikeUrl($trimmed)) {
                $type = 'url';
            } else {
                $type = 'text';
            }
        }

        return match ($type) {
            'email' => self::processEmail($trimmed),
            'tel' => self::processPhone($trimmed),
            'url' => self::processUrl($trimmed),
            default => ['encoded' => $trimmed, 'type' => 'text'],
        };
    }

    private static function dlDistance(string $a, string $b): int
    {
        $m = strlen($a);
        $n = strlen($b);
        if ($m === 0) {
            return $n;
        }
        if ($n === 0) {
            return $m;
        }
        if (abs($m - $n) > 4) {
            return abs($m - $n) + 99;
        }
        $d = [];
        for ($i = 0; $i <= $m; $i++) {
            $d[$i] = [$i];
        }
        for ($j = 0; $j <= $n; $j++) {
            $d[0][$j] = $j;
        }
        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $cost = ($a[$i - 1] === $b[$j - 1]) ? 0 : 1;
                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1,
                    $d[$i][$j - 1] + 1,
                    $d[$i - 1][$j - 1] + $cost
                );
                if ($i > 1 && $j > 1
                    && $a[$i - 1] === $b[$j - 2]
                    && $a[$i - 2] === $b[$j - 1]
                ) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }
        return $d[$m][$n];
    }

    private static function nearestDomain(string $domain, array $candidates, int $maxDist): ?string
    {
        $best = null;
        $bestDist = $maxDist + 1;
        foreach ($candidates as $c) {
            if ($c === $domain) {
                return null;
            }
            $dist = self::dlDistance($domain, $c);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $c;
            }
        }
        return $best;
    }

    private static function fixTld(string $domain): ?string
    {
        $idx = strrpos($domain, '.');
        if ($idx === false || $idx === strlen($domain) - 1) {
            return null;
        }
        $tld = strtolower(substr($domain, $idx + 1));
        if (!isset(self::TLD_FIXES[$tld])) {
            return null;
        }
        return substr($domain, 0, $idx + 1) . self::TLD_FIXES[$tld];
    }

    private static function looksLikeUrl(string $s): bool
    {
        if (preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $s)) {
            return true;
        }
        if (preg_match('/^[a-z][a-z0-9+\-.]*:[^\/]/i', $s)) {
            return true;
        }
        if (preg_match('/\s/', $s)) {
            return false;
        }
        if (!str_contains($s, '.')) {
            return false;
        }
        return (bool) preg_match('/^[\w\-]+(\.[\w\-]+)+(\/.*)?$/', $s);
    }

    private static function looksLikeEmail(string $s): bool
    {
        return (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $s);
    }

    private static function looksLikePhone(string $s): bool
    {
        $digits = preg_replace('/[\s\-().]/', '', $s) ?? '';
        return (bool) preg_match('/^\+?\d{6,16}$/', $digits);
    }

    /** @return array{encoded?:string,type:string,suggestion?:?string,suggestion_reason?:?string,error?:string} */
    private static function processUrl(string $raw): array
    {
        $s = trim($raw);
        if (preg_match('/^mailto:/i', $s)) {
            return self::processEmail(preg_replace('/^mailto:/i', '', $s));
        }
        if (preg_match('/^tel:/i', $s)) {
            return self::processPhone(preg_replace('/^tel:/i', '', $s));
        }

        $hadScheme = (bool) preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $s);
        $encoded = $hadScheme ? $s : 'https://' . $s;

        $parts = parse_url($encoded);
        if ($parts === false || empty($parts['host'])) {
            return [
                'error' => "That doesn't look like a valid URL. Try example.com or https://example.com/path.",
                'type' => 'url',
            ];
        }

        $host = strtolower($parts['host']);
        if ($host === '' || (!str_contains($host, '.') && $host !== 'localhost')) {
            return ['error' => 'A URL needs a domain (e.g. example.com).', 'type' => 'url'];
        }

        $fixed = self::fixTld($host);
        $suggestion = null;
        $reason = null;
        if ($fixed !== null && $fixed !== $host) {
            $parts['host'] = $fixed;
            $suggestion = self::buildUrl($parts);
            $reason = 'TLD typo';
        }

        return [
            'encoded' => $encoded,
            'type' => 'url',
            'suggestion' => $suggestion,
            'suggestion_reason' => $reason,
        ];
    }

    /** @return array{encoded?:string,type:string,suggestion?:?string,suggestion_reason?:?string,error?:string} */
    private static function processEmail(string $raw): array
    {
        $s = trim(preg_replace('/^mailto:/i', '', $raw) ?? '');
        if (!str_contains($s, '@')) {
            return ['error' => 'An email address needs an @ — try name@example.com.', 'type' => 'email'];
        }
        if (!self::looksLikeEmail($s)) {
            return ['error' => 'That email looks incomplete. Use the form name@example.com.', 'type' => 'email'];
        }

        $atIdx = strrpos($s, '@');
        $local = substr($s, 0, $atIdx);
        $domain = strtolower(substr($s, $atIdx + 1));

        $tldFixed = self::fixTld($domain);
        $fixedDomain = $tldFixed ?? $domain;

        $suggestion = null;
        $reason = null;

        if ($tldFixed !== null) {
            $suggestion = $local . '@' . $tldFixed;
            $reason = 'TLD typo';
        }

        $nearest = self::nearestDomain($fixedDomain, self::KNOWN_EMAIL_DOMAINS, 2);
        if ($nearest !== null && $nearest !== $fixedDomain) {
            $suggestion = $local . '@' . $nearest;
            $reason = 'common provider typo';
        }

        return [
            'encoded' => 'mailto:' . $s,
            'type' => 'email',
            'suggestion' => $suggestion !== null ? 'mailto:' . $suggestion : null,
            'suggestion_reason' => $reason,
        ];
    }

    /** @return array{encoded:string,type:string} */
    private static function processPhone(string $raw): array
    {
        $s = trim(preg_replace('/^tel:/i', '', $raw) ?? '');
        $digits = preg_replace('/[\s\-().]/', '', $s) ?? '';
        if (!preg_match('/^\+?\d{6,16}$/', $digits)) {
            return [
                'error' => 'Use digits only, optionally with a leading + and country code (e.g. +1 555 010 0123).',
                'type' => 'tel',
            ];
        }
        return ['encoded' => 'tel:' . $digits, 'type' => 'tel'];
    }

    /** @param array<string,mixed> $parts */
    private static function buildUrl(array $parts): string
    {
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = ($user !== '' || $pass !== '') ? $user . $pass . '@' : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return "{$scheme}://{$auth}{$host}{$port}{$path}{$query}{$fragment}";
    }
}
