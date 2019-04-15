<?php

/*
 * Plugin Name: Dummy
 * Description: Filters out dummy content
 */

add_action('edit_form_after_title', function ($post) {
    if ($post && get_post_meta($post->ID, '_dummy', true)) {
        ?>
        <div class="notice notice-warning inline">
            <p>
                This post has been generated automatically for testing purpose.
                <strong>You should not use it to enter legitimate content</strong>
                as it will be deleted before website release.
            </p>
            <p>
                <button type="button" id="dummy-enable" class="button button-large">
                    Edit anyway
                </button>
            </p>
            <style id="dummy-disabled-styles">
                #titlediv,
                #postdivrich,
                .postbox-container {
                    transition: filter 300ms, opacity 300ms;
                }

                body:not(.dummy-enabled) #titlediv,
                body:not(.dummy-enabled) #postdivrich,
                body:not(.dummy-enabled) .postbox-container {
                    filter: grayscale(100%);
                    pointer-events: none;
                    opacity: .4;
                }
            </style>
            <script>
                document.getElementById('dummy-enable').addEventListener('click', function (e) {
                    document.body.classList.add('dummy-enabled');
                    e.target.parentNode.parentNode.removeChild(e.target.parentNode);
                });
            </script>
        </div>
        <?php
    }
});