<?php


namespace Rdlv\WordPress\Dummy;


/**
 * Provide given value as is
 */
class RawValue implements TypeInterface
{
//    public function get_defaults($assoc_args)
//    {
//        return null;
//    }

    /**
     * @param $value
     * @return mixed
     */
    public function get($post_id, $value)
    {
        return isset($value) ? $value : null;
    }
}