<?php


namespace Rdlv\WordPress\Dummy\Generator;


use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\GeneratorInterface;
use Rdlv\WordPress\Dummy\Initialized;

/**
 * Provide sequential dates
 *
 * Time expressions are parsed by PHP `strtotime` function.
 *
 * ## Arguments
 *
 *      - begin: Start date of the interval
 *      - end: End date of the interval
 *
 * Empty arguments result in `now`.
 *
 * Reverse order may be produced by swapping arguments.
 *
 * ## Short syntax
 *
 *      {id}:<begin>,<end>,<format>
 *
 * ## Example
 *
 *      {id}:4 months ago,now,d/m/Y
 */
class SequentialDate implements GeneratorInterface, Initialized
{
    const START = 'start';
    const END = 'end';
    const FORMAT = 'format';
    
    const DEFAULT_FORMAT = 'Y-m-d H:i:s';

    private $count = null;
    private $index = [];

    public function init_task($args, $assoc_args, $globals)
    {
        if (isset($globals['count'])) {
            $this->count = $globals['count'];
        }
    }

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
        foreach ($args as $index => $arg) {
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
        if (!$this->count || !is_numeric($this->count) || !is_integer($this->count + 0) || $this->count < 0) {
            throw new DummyException(sprintf(
                "count must be a positive integer greater than 0 ('%s' given).",
                $this->count
            ));
        }

        if ($args) {
            foreach ([self::START, self::END, self::FORMAT] as $key) {
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
        if ($args && $this->count) {
            $key = md5(json_encode($args));
            if (!array_key_exists($key, $this->index)) {
                $this->index[$key] = 0;
            }

            $start_ts = strtotime($args[self::START]);
            $end_ts = strtotime($args[self::END]);
            $total = $this->count - 1;
            if ($total === 0) {
                $ts = $start_ts;
            } else {
                $progress = min($this->index[$key], $total) / ($total);
                $ts = ceil(($end_ts - $start_ts) * $progress + $start_ts);
            }
            $this->index[$key]++;

            return date($args[self::FORMAT], $ts);
        } else {
            // return now
            return date($args[self::FORMAT]);
        }
    }
}