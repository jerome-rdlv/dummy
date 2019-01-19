<?php

if (!class_exists('WP_CLI')) {
    return;
}

$autoload = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/** @noinspection PhpUndefinedClassInspection */
try {
    WP_CLI::add_command('dummy', '\Rdlv\WordPress\Dummy\Command');
} catch (Exception $e) {
    echo 'Exception: ' . $e->getMessage() . "\n";
} 
