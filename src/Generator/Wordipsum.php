<?php


namespace Rdlv\WordPress\Dummy\Generator;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\GeneratorInterface;

/**
 * Random lorem ipsum words generator
 *
 * ## Options
 *
 *      - count: Number of words to generate
 *
 * ## Short syntax
 *
 *      # for fixed word count
 *      {id}:<count>
 * 
 *      # for random word count
 *      {id}:<min>,<max>
 * 
 * ## Examples
 * 
 *      {id}:8
 *      {id}:2,12
 */
class Wordipsum implements GeneratorInterface
{
    const API_HTML_URL = 'https://loripsum.net/api/1/plaintext/verylong';

    const DEFAULT_WORD_COUNT = 10;

    const TEXT_START = 8;

    private $words = [];

    public function normalize($args)
    {
        $normalized = [];

        $numbers = [];
        foreach ($args as $arg) {
            if (is_numeric($arg)) {
                $numbers[] = $arg;
            }
        }

        if (count($numbers) === 1) {
            $normalized['count'] = $numbers[0];
        } elseif (count($numbers) === 2) {
            $normalized['count'] = new GeneratorCall(null, new RandomNumber(), [$numbers[0], $numbers[1]]);
        } elseif (count($numbers) > 2) {
            throw new DummyException(sprintf(
                '%s numbers given but only one or two accepted.',
                count($numbers)
            ));
        }

        if (!array_key_exists('count', $normalized)) {
            $normalized['count'] = self::DEFAULT_WORD_COUNT;
        }
        return $normalized;
    }

    public function validate($args)
    {
        if (!array_key_exists('count', $args)) {
            throw new DummyException('word count needed.');
        }
        // do not validate further if dynamic value
        if (!$args['count'] instanceof GeneratorCall) {
            if (!is_numeric($args['count']) || !is_int($args['count'] + 0) || $args['count'] <= 0) {
                throw new DummyException(sprintf(
                    'word count must be a positive integer greater than zero ("%s" given).',
                    $args['count']
                ));
            }
        }
    }

    /**
     * @throws DummyException
     */
    private function load_words()
    {
        try {
            $client = new Client();
            $response = $client->request('GET', self::API_HTML_URL);
        } catch (GuzzleException $e) {
            throw new DummyException('Exception loading html from API: ' . $e->getMessage());
        }

        $words = preg_split('/[^a-z0-9\-]+/i', $response->getBody());
        $count = count($words);
        for ($i = self::TEXT_START; $i < $count; ++$i) {
            $this->words[] = $words[$i];
        }
    }

    public function get($args, $post_id = null)
    {
        $count = $args['count'];
        if (count($this->words) < $count) {
            $this->load_words();
        }

        $words = array_splice($this->words, 0, $count);

        return ucfirst(strtolower(implode(' ', $words)));
    }
}