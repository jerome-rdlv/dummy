<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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
 * : Retrieve random photos; use --no-{id}-random to get predictable results.
 * ---
 * type: flag
 * ---
 *
 * ## Arguments
 *
 *      - orientation: {orientations}
 *      - query: Search terms
 *      - w: Max width of image, default to {max_width}
 *      - h: Max height of image, default to {max_height}
 *
 * ## Short syntax example
 *
 *      {id}:<width>,<height>,landscape,technology
 *
 * ## Example
 *
 *      {id}:photo,places,blue,horizontal
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

    const API_IMAGE_ORIENTATIONS = ['landscape', 'portrait', 'squarish'];

    const API_ARGS = [
        'orientation',
        'query',
    ];

    const DEFAULT_IMAGE_MAX_WIDTH = 1200;
    const DEFAULT_IMAGE_MAX_HEIGHT = 1200;

    private $api_access;

    private $max_upload = self::API_PER_PAGE;
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
            ],
            [
                '[' . implode(', ', self::API_IMAGE_ORIENTATIONS) . ']',
                $this->max_upload,
                self::DEFAULT_IMAGE_MAX_WIDTH,
                self::DEFAULT_IMAGE_MAX_HEIGHT,
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
            } elseif (in_array($arg, self::API_IMAGE_ORIENTATIONS)) {
                if (!array_key_exists('orientation', $normalized)) {
                    $normalized['orientation'] = $arg;
                    continue;
                }
            } else {
                if (!array_key_exists('search', $normalized)) {
                    $normalized['query'] = $arg;
                } else {
                    throw new Exception(sprintf(
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
            throw new Exception('You must provide an Unsplash API Key to use Unsplash image generator.');
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
        return $this->loadimage($image->url, $post_id, $image->desc);
    }

    /**
     * @param $params
     * @return array
     * @throws Exception
     */
    private function load_more_images($params)
    {
        $url = $this->random ? self::API_IMAGE_URL_RANDOM : self::API_IMAGE_URL_SEARCH;

        try {
            $client = new Client();
            $response = $client->request('GET', $url, [
                'query' => $params,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Exception loading images from API: ' . $response->getReasonPhrase());
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            $responseBody = $response->getBody()->getContents();
            $rows = json_decode($responseBody);
            if (!$rows) {
                throw new Exception('Exception loading images from API: json_decode returned null');
            }
            
            if (isset($rows->results) && is_array($rows->results)) {
                $rows = $rows->results;
            }

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

            return $images;

        } catch (GuzzleException $e) {
            throw new Exception('Exception loading images from API: ' . $e->getMessage());
        }
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

            $params = [];
            foreach ($args as $param => $value) {
                if (in_array($param, self::API_ARGS)) {
                    $params[$param] = $value;
                }
            }
            $params['client_id'] = $this->api_access;
            if ($this->random) {
                $params['count'] = min($left, self::API_RANDOM_COUNT_MAX);
            } else {
                $params['per_page'] = self::API_PER_PAGE;
                $params['page'] = floor($set['index'] / self::API_PER_PAGE) + 1;
            }

            $images = array_slice($this->load_more_images($params), 0, $left);

            if ($images) {
                foreach (['w', 'h'] as $key) {
                    if (!empty($args[$key])) {
                        foreach ($images as &$image) {
                            $image->url .= "&$key=$args[$key]";
                        }
                    }
                }
                foreach ($images as &$image) {
                    $image->url .= '&dummy.jpg';
                }
                $set['images'] = array_merge($set['images'], $images);
            }
            else {
                // no more images returned, change max_upload
                // to prevent subsequent loadings
                $this->max_upload = count($set['images']);
            }

        }

        if (count($set['images']) == 0) {
            throw new Exception('Unsplash API did not return any images.');
        }

        return $set['images'][$set['index']++ % count($set['images'])];
    }
}