<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

interface GeneratorInterface
{
    /**
     * @param array $args Array of normalized options
     * @return array Normalized arguments
     * @throws Exception
     */
    public function normalize($args);

    /**
     * @param array $args Array of normalized options
     * @return void
     * @throws Exception That method should throw exception on validation error
     */
    public function validate($args);

    /**
     * @param array $args
     * @param integer $post_id
     * @return mixed
     * @throws Exception
     */
    public function get($args, $post_id = null);
}