<?php


namespace Rdlv\WordPress\Dummy;


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
 *      {id}:<begin>,<end>
 *
 * ## Example
 *
 *      {id}:4 months ago,now
 */
class SequentialDate implements GeneratorInterface, Initialized
{
    const START = 'start';
    const END = 'end';

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
        if (!$this->count || !is_numeric($this->count) || !is_integer($this->count + 0) || $this->count < 0) {
            throw new DummyException(sprintf(
                "count must be a positive integer greater than 0 ('%s' given).",
                $this->count
            ));
        }

        if ($args) {
            foreach ([self::START, self::END] as $key) {
                if (!array_key_exists($key, $args)) {
                    throw new DummyException(sprintf("a '%s' argument is needed.", $key));
                }
            }
        }
        foreach ($args as $key => $option) {
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
            }
            else {
                $progress = min($this->index[$key], $total) / ($total);
                $ts = ceil(($end_ts - $start_ts) * $progress + $start_ts);
            }
            $this->index[$key]++;

            return date('Y-m-d H:i:s', $ts);
        } else {
            // return now
            return date('Y-m-d H:i:s');
        }
    }
}