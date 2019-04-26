<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

/**
 * Provide random dates
 *
 * Time expressions are parsed by PHP `strtotime` function.
 *
 * ## Options
 *
 *      - begin: Start date of the random interval
 *      - end: End date of the random interval
 *
 * Empty options result in now
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
                    throw new Exception(sprintf('an "%s" argument is needed.', $key));
                }
            }
        }
        foreach ($args as $key => $option) {
            $date = strtotime($option);
            if ($date === false) {
                throw new Exception(sprintf('"%s" argument value "%s" is not a valid date expression', $key, $option));
            } else {
                $args[$key] = $date;
            }
        }
    }

    public function get($options, $context = [])
    {
        if ($options) {
            return date('Y-m-d H:i:s', rand($options[self::START], $options[self::END]));
        } else {
            // return now
            return date('Y-m-d H:i:s');
        }
    }
}