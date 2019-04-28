<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

interface GeneratorInterface
{
    /**
     * @param array $args Array of scalar arguments
     * @return array Normalized arguments
     * @throws Exception
     */
    public function normalize($args);

    /**
     * @param array $args Array of normalized options, may contain subarray and GeneratorCall objects
     * @return void
     * @throws Exception That method should throw exception on validation error
     */
    public function validate($args);

    /**
     * @param array $args Array of arguments
     * @param integer $post_id
     * @return string
     * @throws Exception
     */
    public function get($args, $post_id = null);
}