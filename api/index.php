<?php

// Prepare storage directory in /tmp for Vercel environment
$tmpStorage = '/tmp/storage';

if (!file_exists($tmpStorage . '/framework/views')) {
    @mkdir($tmpStorage . '/framework/views', 0755, true);
}
if (!file_exists($tmpStorage . '/framework/cache/data')) {
    @mkdir($tmpStorage . '/framework/cache/data', 0755, true);
}
if (!file_exists($tmpStorage . '/framework/sessions')) {
    @mkdir($tmpStorage . '/framework/sessions', 0755, true);
}
if (!file_exists($tmpStorage . '/logs')) {
    @mkdir($tmpStorage . '/logs', 0755, true);
}

putenv('VIEW_COMPILED_PATH=' . $tmpStorage . '/framework/views');
$_ENV['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';

require __DIR__ . '/../public/index.php';
