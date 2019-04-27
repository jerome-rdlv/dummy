<?php

use Rdlv\WordPress\Dummy\Compositor;

if (!class_exists('WP_CLI')) {
    return;
}

Compositor::instance()->init('dummy', __DIR__ . '/services.yml');
