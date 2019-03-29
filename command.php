<?php

if (!class_exists('WP_CLI')) {
    return;
}

\Rdlv\WordPress\Dummy\Compositor::instance()->init('dummy', __DIR__ .'/services.yml');
