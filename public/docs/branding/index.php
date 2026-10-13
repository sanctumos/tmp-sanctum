<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/views.php';
render_markdown_doc('BRANDING.md', 'Branding', 'Website + Android');
