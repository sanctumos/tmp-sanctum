<?php

/**
 * Copy to config.local.php on the server (gitignored).
 *
 * Preferred location on multihost: ONE DIRECTORY ABOVE the docroot, e.g.
 *   /var/www/tmp.sanctumos.org/config.local.php
 * That path is read first and is not touched by the `rsync --delete` deploy, so
 * settings such as ttl_seconds survive the every-2-minute deploy. The in-docroot
 * html/includes/config.local.php location also works but can be overwritten.
 */
return [
    'base_url' => 'https://tmp.sanctumos.org',
    'max_bytes' => 104857600,
    'ttl_seconds' => 3600, // 60 minutes
];
