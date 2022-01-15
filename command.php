<?php

use Rdlv\WordPress\Dummy\Compositor;

if (!class_exists('\WP_CLI')) {
	return;
}

if (!class_exists('\Rdlv\Wordpress\Dummy\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder')) {
	return;
}

require __DIR__ . '/vendor/autoload.php';

Compositor::instance()->init('dummy', __DIR__ . '/services.yaml');
