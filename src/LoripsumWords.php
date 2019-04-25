<?php


namespace Rdlv\WordPress\Dummy;

use Exception;

/**
 * Random lorem ipsum words generator
 *
 * ## Options
 *
 * - count: Number of words to generate
 *
 * Short syntax:
 *
 *      {id}:8
 */
class LoripsumWords implements GeneratorInterface
{
    const DEFAULT_WORD_COUNT = 10;

    const TEXT_START = 8;

    /** @var Loripsum */
    private $source;

    public function set_html(Loripsum $loripsum)
    {
        $this->source = $loripsum;
    }

    public function normalize($args)
    {
        $normalized = [];
        foreach ($args as $arg) {
            if (is_numeric($arg)) {
                if (!array_key_exists('count', $normalized)) {
                    // first number in args, it may be the fixed word count
                    $normalized['count'] = $arg;
                } else {
                    // second number, so we have min and max instead of length
                    $normalized['count'] = rand($normalized['count'], $arg);
                    break;
                }
            }
        }
        if (!array_key_exists('count', $normalized)) {
            $normalized['count'] = self::DEFAULT_WORD_COUNT;
        }
        return $normalized;
    }

    public function validate($args)
    {
        if (!array_key_exists('count', $args)) {
            throw new Exception('word count needed.');
        }
        if (!is_numeric($args['count']) || !is_int($args['count'] + 0) || $args['count'] < 0) {
            throw new Exception(sprintf(
                'word count must be a positive integer ("%s" given).',
                $args['count']
            ));
        }
    }

    public function get($args, $post_id = null)
    {
        $raw = $this->source->get([1, 'plaintext', 'verylong']);
        $words = explode(' ', preg_replace('/[^a-z0-9]+/i', ' ', $raw));

        return ucfirst(strtolower(implode(' ', array_slice(
            $words,
            self::TEXT_START,
            $args['count']
        ))));
    }
}