<?php


namespace Rdlv\WordPress\Dummy\Generator;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Rdlv\WordPress\Dummy\AbstractImageGenerator;
use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\ExtendDocInterface;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\GeneratorInterface;
use Rdlv\WordPress\Dummy\Initialized;

/**
 * Image generator based on Unsplash API
 *
 * This generator get high quality images from Unsplash API, upload it to the
 * media library, and return the resulting attachment id.
 *
 * You can go to https://unsplash.com/ to setup your image selection.
 *
 * ## Options
 *
 * [--access-key=<key>]
 * : API access key; you may use the UNSPLASH_ACCESS environment variable instead to provide this key.
 *
 * To get Unsplash API keys you must create an account on the
 * service (https://unsplash.com/join) and create an app.
 *
 * [--max=<max>]
 * : Max number of photo to load; if more are needed, previously loaded will be reused.
 * ---
 * default: {max_upload}
 * ---
 *
 * [--random]
 * : Retrieve random photos; use --no-{id}-random to get predictable results globally.
 * ---
 * type: flag
 * ---
 *
 * ## Arguments
 *
 *      - w: Max width of loaded image, default to {max_width}
 *      - h: Max height of loaded image, default to {max_height}
 *      - orientation: {orientations}
 *      - random: Random order, defaults to true
 *      - query: Search terms
 *      - fallback: Alternative query if main search query did not returned anything
 * 
 * w and h arguments allow to limit loaded image size,
 * they have no effect on API search request.
 *
 * ## Short syntax
 *
 *      {id}:<w>,<h>,<orientation>,<query>,<fallback>
 *
 * You can add 'sequential' to cancel random order.
 *
 * ## Example
 *
 *      {id}:800,600,landscape,technology
 *
 * ## Warning
 *
 * At least one request will be made on the API for each arguments set, so
 * be cautious with using dynamic values in these arguments as it may
 * consume you API hourly limit very quickly.
 *
 */
class Unsplash extends AbstractImageGenerator implements GeneratorInterface, Initialized, ExtendDocInterface
{
    const API_IMAGE_URL_RANDOM = 'https://api.unsplash.com/photos/random';
    const API_IMAGE_URL_SEARCH = 'https://api.unsplash.com/search/photos';
    const API_IMAGE_DESC = '<a href="%1$s">Photo</a> by ' .
    '<a href="%2$s">%3$s</a>';
    const API_RANDOM_COUNT_MAX = 30;

    const API_PER_PAGE = 30;
    const DEFAULT_MAX_IMAGES_LOADED = 30;

    const API_IMAGE_ORIENTATIONS = ['landscape', 'portrait', 'squarish'];

    const API_ARGS = [
        'orientation',
        'query',
    ];

    const DEFAULT_IMAGE_MAX_WIDTH = 1200;
    const DEFAULT_IMAGE_MAX_HEIGHT = 1200;

    const ORDER_RANDOM = 'random';
    const ORDER_SEQUENTIAL = 'sequential';

    private $api_access;

    private $max_upload = self::DEFAULT_MAX_IMAGES_LOADED;
    private $random = true;

    private $images = [];

    public function set_key($access)
    {
        $this->api_access = $access;
    }

    public function extend_doc($doc)
    {
        return str_replace(
            [
                '{orientations}',
                '{max_upload}',
                '{max_width}',
                '{max_height}',
                '{order}',
            ],
            [
                '[' . implode(', ', self::API_IMAGE_ORIENTATIONS) . ']',
                $this->max_upload,
                self::DEFAULT_IMAGE_MAX_WIDTH,
                self::DEFAULT_IMAGE_MAX_HEIGHT,
                '[' . self::ORDER_RANDOM . ', ' . self::ORDER_SEQUENTIAL . ']',
            ],
            $doc
        );
    }

    public function init_task($args, $assoc_args, $globals)
    {
        if (!empty($assoc_args['access-key'])) {
            $this->api_access = $assoc_args['access-key'];
        }
        if (!empty($assoc_args['max'])) {
            $this->max_upload = $assoc_args['max'];
        }
        $this->random = isset($assoc_args['random']) ? $assoc_args['random'] : true;
    }

    public function normalize($args)
    {
        $normalized = [];
        foreach ($args as $arg) {
            if (is_numeric($arg)) {
                foreach (['w', 'h'] as $key) {
                    if (!array_key_exists($key, $normalized)) {
                        $normalized[$key] = $arg;
                        break;
                    }
                }
            } elseif (!array_key_exists('random', $normalized) && in_array($arg,
                    [self::ORDER_RANDOM, self::ORDER_SEQUENTIAL])) {
                $normalized['random'] = $arg === self::ORDER_RANDOM;
            } elseif (!array_key_exists('orientation', $normalized) && in_array($arg, self::API_IMAGE_ORIENTATIONS)) {
                $normalized['orientation'] = $arg;
            } else {
                if (!array_key_exists('query', $normalized)) {
                    $normalized['query'] = $arg;
                } elseif (!array_key_exists('fallback', $normalized)) {
                    $normalized['fallback'] = $arg;
                } else {
                    throw new DummyException(sprintf(
                        "too many parameters ('%s').",
                        $arg
                    ));
                }
            }
        }
        return $normalized;
    }

    public function validate($args)
    {
        if (!$this->api_access) {
            throw new DummyException('You must provide an Unsplash API Key to use Unsplash image generator.');
        }

        foreach (['w', 'h'] as $key) {
            if (array_key_exists($key, $args) && !$args[$key] instanceof GeneratorCall) {
                $val = $args[$key];
                if (!is_numeric($val) || !is_int($val + 0) || $val <= 0) {
                    throw new DummyException(sprintf(
                        "%s must be a positive integer greater than zero ('%s' given).",
                        $key,
                        $val
                    ));
                }
            }
        }

        if (array_key_exists('orientation', $args) && !$args['orientation'] instanceof GeneratorCall) {
            if (!in_array($args['orientation'], self::API_IMAGE_ORIENTATIONS)) {
                throw new DummyException(sprintf(
                    "unknown orientation '%s', must be any of %s",
                    $args['orientation'],
                    implode(', ', self::API_IMAGE_ORIENTATIONS)
                ));
            }
        }
    }

    public function get($args, $post_id = null)
    {
        // apply defaults
        if (!array_key_exists('w', $args)) {
            $args['w'] = self::DEFAULT_IMAGE_MAX_WIDTH;
        }
        if (!array_key_exists('h', $args)) {
            $args['h'] = self::DEFAULT_IMAGE_MAX_HEIGHT;
        }

        $image = $this->get_next_image($args);

        if (!$image && array_key_exists('fallback', $args)) {
            // no image returned but fallback search present, try it
            $args['query'] = $args['fallback'];
            $image = $this->get_next_image($args);

            if (!$image) {
                // no image returned, drop query
                unset($args['query']);
                $image = $this->get_next_image($args);
            }
        }

        if (!$image) {
            throw new DummyException('Unsplash API did not return any images.');
        }

        return $this->loadimage($image->url, $post_id, $image->desc);
    }

    /**
     * @param GuzzleException $e
     * @throws DummyException
     */
    private function throw_if_ratelimit_exeeded($e)
    {
        if ($e instanceof ClientException) {
            $response = $e->getResponse();
            $remaining_header = $response->getHeader('x-ratelimit-remaining');
            $limit_header = $response->getHeader('x-ratelimit-limit');
            $limit = isset($limit_header[0]) ? $limit_header[0] : '';
            if (isset($remaining_header[0]) && $remaining_header[0] == 0) {
                throw new DummyException(sprintf(
                    "You have exeeded the hourly connections limit of %s.",
                    $limit
                ));
            }
        }
    }

    private function is_random($args)
    {
        $random = $this->random;
        if (isset($args['random'])) {
            $random = $args['random'];
        }
        if (!$random && empty($args['query'])) {
            // query is needed for sequential search, fallback to random
            $random = true;
        }
        return $random;
    }

    /**
     * @param array $args
     * @param integer $count
     * @param integer $index
     * @return array
     * @throws Exception
     */
    private function load_more_images($args, $count, $index)
    {
        $params = [];
        foreach ($args as $param => $value) {
            if (in_array($param, self::API_ARGS)) {
                $params[$param] = $value;
            }
        }
        $params['client_id'] = $this->api_access;
        $random = $this->is_random($args);
        if ($random) {
            $params['count'] = min($count, self::API_RANDOM_COUNT_MAX);
        } else {
            $params['per_page'] = self::API_PER_PAGE;
            $params['page'] = (int)floor($index / self::API_PER_PAGE) + 1;
        }

        $url = $random ? self::API_IMAGE_URL_RANDOM : self::API_IMAGE_URL_SEARCH;

        try {
            $client = new Client();
            $response = $client->request('GET', $url, [
                'query' => $params,
            ]);
        } catch (GuzzleException $e) {
            switch ($e->getCode()) {
                case 404:
                    // maybe search query is too narrow, let upper level amend args
                    return [];
                case 403:
                    $this->throw_if_ratelimit_exeeded($e);
                    break;
            }
            throw new DummyException(sprintf(
                "Exception loading images from API: %s",
                $e->getMessage()
            ));
        }

        $responseBody = $response->getBody()->getContents();
        $rows = json_decode($responseBody);
        if (!$rows) {
            throw new DummyException('Exception loading images from API: json_decode returned null');
        }

        if (isset($rows->results) && is_array($rows->results)) {
            $rows = $rows->results;
        }

//            if (!$rows && array_key_exists('query', $params)) {
//                // maybe search query is too narrow, drop it
//                $corrected_args = $args;
//                unset($corrected_args['query']);
//                return $this->load_more_images($corrected_args, $count, $index);
//            }

        // transform dataset
        $images = [];
        foreach ($rows as $row) {
            $images[] = (object)[
                'url'  => $row->urls->raw,
                'desc' => sprintf(
                    self::API_IMAGE_DESC,
                    $row->links->html,
                    $row->user->links->html,
                    $row->user->name
                ),
            ];
        }

        // add size attributes to image URL
        foreach (['w', 'h'] as $key) {
            if (!empty($args[$key])) {
                foreach ($images as &$image) {
                    $image->url .= "&$key=$args[$key]";
                }
            }
        }

        // add dummy parameter for .jpg ending
        foreach ($images as &$image) {
            $image->url .= '&dummy.jpg';
        }

        return $images;
    }

    /**
     * @param $args
     * @return mixed
     * @throws Exception
     */
    private function get_next_image($args)
    {
        $set_id = md5(json_encode($args));

        // initialize
        if (!array_key_exists($set_id, $this->images)) {
            $this->images[$set_id] = [
                'images' => [],
                'index'  => 0,
            ];
        }

        // load images
        $set = &$this->images[$set_id];
        $total = count($set['images']);
        $left = $this->max_upload - $total;
        if ($set['index'] >= $total && $left > 0) {

            $images = $this->load_more_images($args, $left, $set['index']);

            if ($images) {
                $set['images'] = array_merge(
                    $set['images'],
                    array_slice($images, 0, $left)
                );
            }
        }

        // commeted to let upper level ajust $args
        // as it may match another set
//        if (count($set['images']) == 0) {
//            throw new DummyException('Unsplash API did not return any images.');
//        }

        $total = count($set['images']);
        if ($total) {
            return $set['images'][$set['index']++ % count($set['images'])];
        } else {
            return null;
        }
    }
}