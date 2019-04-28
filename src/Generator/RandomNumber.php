<?php


namespace Rdlv\WordPress\Dummy\Generator;


use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\GeneratorInterface;

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
        $count = count($args);
        if ($count !== 2) {
            throw new DummyException(sprintf(
                "expect two arguments, %s given.",
                $count ? $count : 'none'
            ));
        }

        $normalized = [];

        foreach ($args as $arg) {
            foreach (['min', 'max'] as $key) {
                if (!array_key_exists($key, $normalized)) {
                    $normalized[$key] = $arg;
                    break;
                }
            }
        }
        return $normalized;
    }

    public function validate($args)
    {
        if (!$args) {
            throw new DummyException("'min' and 'max' arguments are needed.");
        }
        foreach (['min', 'max'] as $key) {
            if (!array_key_exists($key, $args)) {
                throw new DummyException(sprintf("'%s' argument is needed.", $key));
            }
            if ($args[$key] instanceof GeneratorCall) {
                // dynamic value, do not test further
                continue;
            }
            if (!is_numeric($args[$key]) || !is_int($args[$key] + 0)) {
                throw new DummyException(sprintf(
                    "'%s' argument must be an integer ('%s' given).",
                    $key,
                    $args[$key]
                ));
            }
        }
    }

    public function get($args, $post_id = null)
    {
        return mt_rand($args['min'], $args['max']);
    }
}