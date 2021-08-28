<?php

use Rdlv\WordPress\Dummy\Compositor;

if (!class_exists('WP_CLI')) {
	return;
}

require __DIR__ . '/vendor/autoload.php';

Compositor::instance()->init('dummy', __DIR__ . '/services.yml');
