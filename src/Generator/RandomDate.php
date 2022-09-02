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
 *      {id}:<begin>,<end>,<forma>
 *
 * ## Example
 *
 *      {id}:4 months ago,now,d/m/Y
 */
class RandomDate implements GeneratorInterface
{
    const START = 'start';
    const END = 'end';
    const FORMAT = 'format';
    
    const DEFAULT_FORMAT = 'Y-m-d H:i:s';

    public function normalize($args)
    {
        $count = count($args);
        if (!$count) {
            throw new DummyException("expect at least one argument, none given.");
        } elseif ($count > 3) {
            throw new DummyException(sprintf(
                "expect at most three arguments, %s given.",
                $count
            ));
        }

        $normalized = [];
        foreach ($args as $arg) {
            if (strtotime($arg) === false && !array_key_exists(self::FORMAT, $normalized)) {
                $normalized[self::FORMAT] = $arg;
                continue;
            }
            foreach ([self::START, self::END, self::FORMAT] as $key) {
                if (!array_key_exists($key, $normalized)) {
                    $normalized[$key] = $arg;
                    break;
                }
            }
        }

        if (!array_key_exists(self::FORMAT, $normalized)) {
            $normalized[self::FORMAT] = self::DEFAULT_FORMAT;
        }

        foreach ([self::START, self::END] as $key) {
            if (!array_key_exists(self::FORMAT, $normalized)) {
                $normalized[$key] = 'now';
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
            if (in_array($key, [self::START, self::END]) && strtotime($option) === false) {
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
            return date($args[self::FORMAT], mt_rand(
                strtotime($args[self::START]),
                strtotime($args[self::END])
            ));
        } else {
            // return now
            return date($args[self::FORMAT]);
        }
    }
}