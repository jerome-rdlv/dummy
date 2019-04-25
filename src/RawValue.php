<?php


namespace Rdlv\WordPress\Dummy;


/**
 * Allow to explicit raw value in case that value is ambiguous (look like a generator call).
 * 
 * Example:
 * 
 *      {id}:text:value:with:semicolons
 */
class RawValue implements GeneratorInterface
{
    public function normalize($args)
    {
        return $args;
    }

    public function validate($args)
    {
    }

    public function get($args, $post_id = null)
    {
        return implode('', $args);
    }
}