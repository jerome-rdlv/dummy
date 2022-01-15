<?php


namespace Rdlv\WordPress\Dummy\Generator;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\ExtendDocInterface;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\GeneratorInterface;

/**
 * Retrieve random text or rich text from http://loripsum.net/
 *
 * ## Arguments
 *
 *      - count: Paragraph count
 *      - length: Paragraphe length, any of {length}
 *      - options: Any of {arguments}
 *
 * ## Short syntax
 *
 * For fixed paragraph count:
 *
 *      {id}:<count>,<length>,<arguments…>
 *
 * For random paragraph count:
 *
 *      {id}:<min>,<max>,<arguments…>
 *
 * ## Example
 *
 *      {id}:2,6,short,ul,h2,h3
 */
class Loripsum implements GeneratorInterface, ExtendDocInterface
{
    const API_HTML_URL = 'https://loripsum.net/api/%s';

    const LENGTH = ['short', 'medium', 'long', 'verylong'];

    const OPTIONS = [
        'decorate',
        'link',
        'ul',
        'ol',
        'dl',
        'bq',
        'code',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'allcaps',
        'prude',
        'plaintext',
    ];

    public function extend_doc($doc)
    {
        return str_replace(
            [
                '{length}',
                '{arguments}',
            ],
            [
                implode(', ', self::LENGTH),
                implode(', ', array_filter(self::OPTIONS, function ($item) {
                    return !is_array($item);
                })),
            ],
            $doc
        );
    }

    public function normalize($args)
    {
        $normalize = [
            'options' => [],
        ];

        // get numbers
        $numbers = [];
        foreach ($args as $arg) {
            if (is_numeric($arg)) {
                $numbers[] = $arg;
            }
        }
        if (count($numbers) === 1) {
            $normalize['count'] = $numbers[0];
        } elseif (count($numbers) === 2) {
            $normalize['count'] = new GeneratorCall(null, new RandomNumber(), [$numbers[0], $numbers[1]]);
        } elseif (count($numbers) > 2) {
            throw new DummyException(sprintf(
                '%s numbers given but only one or two accepted.',
                count($numbers)
            ));
        }

        // get strings
        foreach ($args as $arg) {
            if (!is_numeric($arg)) {
                if (in_array($arg, self::LENGTH)) {
                    if (array_key_exists('length', $normalize)) {
                        throw new DummyException(sprintf(
                            "length '%s' already set and new length '%s' found.",
                            $normalize['length'],
                            $arg
                        ));
                    } else {
                        $normalize['length'] = $arg;
                    }
                } else {
                    $normalize['options'][] = $arg;
                }
            }
        }

        if (!$normalize['options']) {
            unset($normalize['options']);
        }

        return $normalize;
    }

    public function validate($args)
    {
        // validate count if present and readable
        if (array_key_exists('count', $args) && !$args['count'] instanceof GeneratorCall) {
            $count = $args['count'];
            if (!is_numeric($count) || !is_int($count + 0) || $count <= 0) {
                throw new DummyException(sprintf(
                    'paragraph count must be a positive integer greater than 0 ("%s" given).',
                    $count
                ));
            }
        }

        // validate length if present and readable
        if (array_key_exists('length', $args) && !$args['length'] instanceof GeneratorCall) {
            $length = $args['length'];
            if (!in_array($length, self::LENGTH)) {
                throw new DummyException(sprintf(
                    "paragraph length must be any of %s ('%s' given).",
                    implode(', ', self::LENGTH),
                    $length
                ));
            }
        }

        // validate options
        if (array_key_exists('options', $args) && !$args['options'] instanceof GeneratorCall) {
            if (!is_array($args['options'])) {
                throw new DummyException(sprintf(
                    "options argument must be an array."
                ));
            }
            foreach ($args['options'] as $option) {
                if (!$option instanceof GeneratorCall && !in_array($option, self::OPTIONS)) {
                    throw new DummyException(sprintf(
                        "unknown option '%s', must be any of %s",
                        $option,
                        implode(', ', self::OPTIONS)
                    ));
                }
            }
        }
    }

    public function get($args, $post_id = null)
    {
        $options = array_key_exists('options', $args) ? $args['options'] : [];

        $query = array_unique(array_map(function ($option) {
            return preg_match('/^h[1-6]$/', $option) ? 'headers' : $option;
        }, $options));

        $levels = array_filter(array_map(function ($option) {
            return preg_replace('/(h([1-6])|.*)/', '\2', $option);
        }, $options));

        // add length
        if (array_key_exists('length', $args)) {
            $query[] = $args['length'];
        }

        // add count
        if (array_key_exists('count', $args)) {
            $query[] = $args['count'];
        }

        try {
            $client = new Client();
            $response = $client->request('GET', sprintf(self::API_HTML_URL, implode('/', $query)));
        } catch (GuzzleException $e) {
            throw new DummyException('Exception loading html from API: ' . $e->getMessage());
        }

        // replace out of range headings and return
        return preg_replace_callback('/<h([1-6])>(.*?)<\/h\1>/', function ($matches) use ($levels) {
            return in_array($matches[1], $levels) ? $matches[0] : sprintf(
                '<h%1$s>%2$s</h%1$s>',
                $levels[array_rand($levels)],
                $matches[2]
            );
        }, $response->getBody());
    }
}
