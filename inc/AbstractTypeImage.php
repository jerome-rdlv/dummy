<?php


namespace Rdlv\WordPress\Dummy;


abstract class AbstractTypeImage
{
    use OutputTrait;

    private $images = [];

    protected function loadimage($url, $post_id, $desc)
    {
        if (array_key_exists($url, $this->images)) {
            return $this->images[$url];
        }

        if (!function_exists('media_sideload_image') || !function_exists('update_post_meta')) {
            $this->error('Admin must be loaded for image upload');
            exit;
        }
        $image_id = media_sideload_image($url, $post_id, $desc, 'id');
        update_post_meta($image_id, '_dummy', true);

        // set post_status as draft to hide image in admin screens
        global $wpdb;

        /** @noinspection PhpUndefinedMethodInspection */
        $wpdb->update($wpdb->posts, ['post_status' => 'draft'], ['ID' => $image_id]);

        $this->images[$url] = $image_id;

        return $image_id;
    }
}