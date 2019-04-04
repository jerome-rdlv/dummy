<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

abstract class AbstractImageGenerator
{
    use OutputTrait;

    private $images = [];

    /**
     * @param string $url
     * @param integer|null $post_id
     * @param string $desc
     * @return integer
     * @throws Exception
     */
    protected function loadimage($url, $post_id, $desc)
    {
        if (array_key_exists($url, $this->images)) {
            return $this->images[$url];
        }

        if (!function_exists('media_sideload_image') || !function_exists('update_post_meta')) {
            throw new Exception('Admin must be loaded for image upload');
        }
        $image_id = media_sideload_image($url, $post_id, $desc, 'id');
        
        if ($image_id instanceof \WP_Error) {
            throw new Exception($image_id->get_error_message());
        }
        update_post_meta($image_id, '_dummy', true);

        // set post_status as draft to hide image in admin screens
        global $wpdb;

        /** @noinspection PhpUndefinedMethodInspection */
        $wpdb->update($wpdb->posts, ['post_status' => 'draft'], ['ID' => $image_id]);

        $this->images[$url] = $image_id;

        return $image_id;
    }
}