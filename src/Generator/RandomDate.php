<?php


namespace Rdlv\WordPress\Dummy\Generator;


use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\GeneratorInterface;

/**
 * Provide random dates
 *
 * Time expressions are parsed by PHP `strtotime` function.
 *
 * ## Arguments
 *
 *      - begin: Start date of the random interval
 *      - end: End date of the random interval
 *
 * Empty arguments result in now.
 *
 * ## Short syntax
 *
 *      {id}:<begin>,<end>
 *
 * ## Example
 *
 *      {id}:4 months ago,now
 */
class RandomDate implements GeneratorInterface
{
    const START = 'start';
    const END = 'end';

    public function normalize($args)
    {
        $count = count($args);
        if (!$count) {
            throw new DummyException("expect at least one argument, none given.");
        } elseif ($count > 2) {
            throw new DummyException(sprintf(
                "expect at most two arguments, %s given.",
                $count
            ));
        }

        $normalized = [];

        foreach ($args as $arg) {
            foreach ([self::START, self::END] as $key) {
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
        if ($args) {
            foreach ([self::START, self::END] as $key) {
                if (!array_key_exists($key, $args)) {
                    throw new DummyException(sprintf("a '%s' argument is needed.", $key));
                }
            }
        }
        foreach ($args as $key => $option) {
            if ($option instanceof GeneratorCall) {
                // dynamic value, do not test further
                continue;
            }
            if (strtotime($option) === false) {
                throw new DummyException(sprintf(
                    "'%s' argument value '%s' is not a valid date expression",
                    $key,
                    $option
                ));
            }
        }
    }

    public function get($args, $post_id = null)
    {
        if ($args) {
            return date('Y-m-d H:i:s', rand(
                strtotime($args[self::START]),
                strtotime($args[self::END])
            ));
        } else {
            // return now
            return date('Y-m-d H:i:s');
        }
    }
}