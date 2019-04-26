<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

/**
 * Provide random numbers
 *
 * ## Arguments
 *
 *      - min
 *      - max
 *
 * ## Short syntax
 *
 *      {id}:<min>,<max>
 *
 * ## Example
 *
 *      {id}:4,16
 */
class RandomNumber implements GeneratorInterface
{
    public function normalize($args)
    {
        $normalized = [];
        foreach ($args as $arg) {
            if (is_numeric($arg)) {
                foreach (['min', 'max'] as $key) {
                    if (!array_key_exists($key, $normalized)) {
                        $normalized[$key] = $arg;
                        break;
                    }
                }
            }
        }
        return $normalized;
    }

    public function validate($args)
    {
        if (!$args) {
            throw new Exception('"min" and "max" arguments are needed.');
        }
        foreach (['min', 'max'] as $key) {
            if (!array_key_exists($key, $args)) {
                throw new Exception(sprintf('"%s" argument is needed.', $key));
            }
            if (!is_numeric($args[$key]) || !is_int($args[$key] + 0) || $args[$key] < 0) {
                throw new Exception(sprintf(
                    '"%s" argument must be a positive integer ("%s" given).',
                    $key,
                    $args[$key]
                ));
            }
        }
    }

    public function get($args, $post_id = null)
    {
        return rand($args['min'], $args['max']);
    }
}