<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Retrieve random text or rich text from http://loripsum.net/
 *
 * ## Arguments
 *
 *      - {length}
 *      - {arguments}
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
        self::LENGTH,
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
        $normalize = [];
        
        $numbers = [];
        foreach ($args as $arg) {
            if (is_numeric($arg)) {
                $numbers[] = $arg;
            }
        }
        if (count($numbers) === 1) {
            $normalize[] = $numbers[0];
        }
        elseif (count($numbers) === 2) {
            $normalize[] = rand($numbers[0], $numbers[1]);
        }
        elseif (count($numbers) > 2) {
            throw new Exception(sprintf(
                '%s numbers given but only one or two accepted.',
                count($numbers)
            ));
        }
        
        foreach ($args as $arg) {
            if (!is_numeric($arg)) {
                $normalize[] = $arg;
            }
        }
        return $normalize;
    }

    public function validate($args)
    {
        $length = null;
        foreach ($args as &$arg) {
            if (is_numeric($arg)) {
                if (!is_int($arg + 0) || $arg <= 0) {
                    throw new Exception(sprintf(
                        'paragraph count must be a positive integer greater than 0 ("%s" given).',
                        $arg
                    ));
                }
                continue;
            }
            if (in_array($arg, self::LENGTH)) {
                if ($length) {
                    throw new Exception(sprintf(
                        'length is already set ("%s" is set and "%s" given).',
                        $length,
                        $arg
                    ));
                }
                else {
                    $length = $arg;
                }
                continue;
            }
            if (!in_array($arg, self::OPTIONS)) {
                throw new Exception(sprintf(
                    'unknown argument "%s" given; possible arguments are: %s',
                    $arg,
                    implode(', ', array_map(function ($item) {
                        return is_array($item) ? implode('|', $item) : $item;
                    }, self::OPTIONS))
                ));
            }
        }
    }

    public function get($args, $post_id = null)
    {
        $query = implode('/', array_unique(array_map(function ($arg) {
            return preg_match('/^h[1-6]$/', $arg) ? 'headers' : $arg;
        }, $args)));

        $levels = array_filter(array_map(function ($arg) {
            return preg_replace('/(h([1-6])|.*)/', '\2', $arg);
        }, $args));

        try {
            $client = new Client();
            $response = $client->request('GET', sprintf(self::API_HTML_URL, $query));
            if ($response->getStatusCode() !== 200) {
                throw new Exception('Exception loading html from API: ' . $response->getReasonPhrase());
            }
        } catch (GuzzleException $e) {
            throw new Exception('Exception loading html from API: ' . $e->getMessage());
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