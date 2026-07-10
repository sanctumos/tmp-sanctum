<?php

declare(strict_types=1);

/**
 * Resolve markdown docs from repo root (survives deploy) with public/ fallback.
 */
function sanctum_tmp_doc_path(string $relative): ?string
{
    $root = sanctum_tmp_config()->rootDir;
    foreach ([
        $root . '/docs/' . $relative,
        $root . '/html/docs/' . $relative,
        $root . '/public/docs/' . $relative,
        dirname(__DIR__) . '/docs/' . $relative,
    ] as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function sanctum_tmp_agents_path(): ?string
{
    $root = sanctum_tmp_config()->rootDir;
    foreach ([
        $root . '/AGENTS.md',
        $root . '/html/AGENTS.md',
        $root . '/public/AGENTS.md',
        dirname(__DIR__) . '/AGENTS.md',
    ] as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function sanctum_tmp_send_markdown_file(?string $path, string $downloadName): void
{
    if ($path === null || !is_readable($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not found\n";
        return;
    }

    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
    readfile($path);
}

/** Minimal markdown → HTML for API docs (headings, fences, tables, inline code, links). */
function sanctum_tmp_markdown_to_html(string $markdown): string
{
    $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
    $html = '';
    $inCode = false;
    $codeLang = '';
    $inTable = false;
    $tableHeaderDone = false;

    foreach ($lines as $line) {
        if (preg_match('/^```(\w*)?$/', $line, $m)) {
            if (!$inCode) {
                $inCode = true;
                $codeLang = htmlspecialchars((string) ($m[1] ?? ''));
                $html .= '<pre class="code-block"><code class="lang-' . $codeLang . '">';
            } else {
                $inCode = false;
                $html .= "</code></pre>\n";
            }
            continue;
        }

        if ($inCode) {
            $html .= htmlspecialchars($line) . "\n";
            continue;
        }

        if (str_starts_with($line, '# ')) {
            $html .= '<h1>' . sanctum_tmp_inline_md(substr($line, 2)) . "</h1>\n";
            continue;
        }
        if (str_starts_with($line, '## ')) {
            $html .= '<h2>' . sanctum_tmp_inline_md(substr($line, 3)) . "</h2>\n";
            continue;
        }
        if (str_starts_with($line, '### ')) {
            $html .= '<h3>' . sanctum_tmp_inline_md(substr($line, 4)) . "</h3>\n";
            continue;
        }

        if (str_contains($line, '|') && preg_match('/^\|/', trim($line))) {
            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (preg_match('/^[\s\-:|]+$/', implode('', $cells))) {
                continue;
            }
            if (!$inTable) {
                $inTable = true;
                $tableHeaderDone = false;
                $html .= "<table>\n";
            }
            $tag = !$tableHeaderDone ? 'th' : 'td';
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<' . $tag . '>' . sanctum_tmp_inline_md($cell) . '</' . $tag . '>';
            }
            $html .= "</tr>\n";
            if (!$tableHeaderDone) {
                $tableHeaderDone = true;
            }
            continue;
        }

        if ($inTable) {
            $html .= "</table>\n";
            $inTable = false;
            $tableHeaderDone = false;
        }

        if (trim($line) === '') {
            continue;
        }

        $html .= '<p>' . sanctum_tmp_inline_md($line) . "</p>\n";
    }

    if ($inTable) {
        $html .= "</table>\n";
    }
    if ($inCode) {
        $html .= "</code></pre>\n";
    }

    return $html;
}

function sanctum_tmp_inline_md(string $text): string
{
    $escaped = htmlspecialchars($text, ENT_QUOTES);
    $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
    $escaped = preg_replace(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        '<a href="$2">$1</a>',
        $escaped
    ) ?? $escaped;

    return $escaped;
}
