<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 *
 * You can go to https://pixabay.com/en/photos/ to setup your image selection.
 *
 * To get Unsplash API keys you must create an account on the
 * service https://unsplash.com/join and create an app.
 *
 * [--unsplash-access-key=<key>]
 *
 * [--unsplash-secret-key=<key>]
 *
 * [--unsplash-options=<defaults>]
 * : Options for image query. Possible values are:
 * [ horizontal, vertical ]
 * [ photo, illustration, vector ]
 * [ fashion, nature, backgrounds, science, education, people, feelings, religion, health, places, animals, industry, food, computer, sports, transportation, travel, buildings, business, music ]
 * [ grayscale, transparent, red, orange, yellow, green, turquoise, blue, lilac, pink, white, gray, black, brown ]
 * ---
 * default: photo/horizontal/industry
 * ---
 *
 */
class Unsplash extends AbstractImageGenerator implements GeneratorInterface, Initialized
{
    const API_IMAGE_URL = 'https://api.unsplash.com/photos/random';
    const API_IMAGE_DESC = '<a href="%1$s">Photo</a> by ' .
    '<a href="%2$s">%3$s</a>';
    const API_IMAGE_PAGE_SIZE_MIN = 3;  // api accept 3 as minimum per_page param value
    const API_IMAGE_PAGE_SIZE_MAX = 12;
    const API_IMAGE_OPTIONS = [
        'orientation' => ['horizontal', 'vertical'],
        'image_type'  => ['photo', 'illustration', 'vector'],
        'category'    => [
            'fashion',
            'nature',
            'backgrounds',
            'science',
            'education',
            'people',
            'feelings',
            'religion',
            'health',
            'places',
            'animals',
            'industry',
            'food',
            'computer',
            'sports',
            'transportation',
            'travel',
            'buildings',
            'business',
            'music',
        ],
        'colors'      => [
            'grayscale',
            'transparent',
            'red',
            'orange',
            'yellow',
            'green',
            'turquoise',
            'blue',
            'lilac',
            'pink',
            'white',
            'gray',
            'black',
            'brown',
        ],
    ];

    private $api_access;
    private $api_secret;

    private $max_upload = 25;

    private $images_params = [
//        'editors_choice' => 'false', // true, [false]
//        'safesearch'     => 'true',
    ];
    private $images_total = null;
    private $images_data = [];
    private $images_index = 0;

    public function set_keys($access, $secret)
    {
        $this->api_access = $access;
        $this->api_secret = $secret;
    }

    public function init($args, $assoc_args)
    {
        $this->images_params['client_id'] = $this->api_access;

//        foreach (explode('/', $assoc_args['pixabay-options']) as $option) {
//            if ($option) {
//                $match = false;
//                foreach (self::API_IMAGE_OPTIONS as $param => $values) {
//                    if (in_array($option, $values)) {
//                        $match = true;
//                        $this->images_params[$param] = $option;
//                        break;
//                    }
//                }
//                if (!$match) {
//                    WP_CLI::warning(sprintf('Unknown image option %s', $option));
//                }
//            }
//        }
    }

    public function get($options, $post_id = null)
    {
        if ($this->images_index >= count($this->images_data)) {
            // load more images
            $this->load_images();
        }
        if (count($this->images_data) == 0) {
            throw new Exception('Unsplash API did not return any images.');
        }
        $image = $this->images_data[$this->images_index++ % count($this->images_data)];
        return $this->loadimage($image->url, $post_id, $image->desc);
    }

    private function load_images()
    {
        $images_count = count($this->images_data);
        $page_max_size = $this->max_upload - $images_count;

        if ($page_max_size <= 0) {
            return;
        }

        if (!$this->api_access) {
            $this->error('To use Unsplash image generator, you must provide a Pixabay API Key with either --pixabay-key option, or PIXABAY_KEY environment variable.');
        }

        try {
            $client = new Client();
            $per_page = max(
            // this is a hard min, API errors otherwise
                self::API_IMAGE_PAGE_SIZE_MIN,
                min(
                    $page_max_size,
                    rand(self::API_IMAGE_PAGE_SIZE_MIN, self::API_IMAGE_PAGE_SIZE_MAX)
                )
            );
            $response = $client->request('GET', self::API_IMAGE_URL, [
                'query' => array_replace([], $this->images_params, [
                    'count' => max($page_max_size, 1),
//                    'per_page' => $per_page,
                    // get random page
//                    'page'     => rand(1, floor($this->get_image_total() / $per_page)),
                ]),
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->error('Exception loading images from API: ' . $response->getReasonPhrase());
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            $responseBody = $response->getBody()->getContents();
            $images = json_decode($responseBody);
            if (!$images) {
                $this->error('Exception loading images from API: json_decode returned null');
            }

            for ($i = 0; $i < min(count($images), $page_max_size); ++$i) {
                $this->images_data[] = (object)[
                    'url'  => $images[$i]->urls->raw . '&w=1200&h=1200&dummy.jpg',
                    'desc' => sprintf(
                        self::API_IMAGE_DESC,
                        $images[$i]->links->html,
                        $images[$i]->user->links->html,
                        $images[$i]->user->name
                    ),
                ];
            }

        } catch (GuzzleException $e) {
            $this->error('Exception loading images from API: ' . $e->getMessage());
        }
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function get_image_total()
    {
        if ($this->images_total === null) {
            try {
                $client = new Client();
                $response = $client->request('GET', self::API_IMAGE_URL, [
                    'query' => array_replace([], $this->images_params, [
                        'per_page' => self::API_IMAGE_PAGE_SIZE_MIN,
                    ]),
                ]);
                if ($response->getStatusCode() !== 200) {
                    $this->error('Exception loading total from image from API: ' . $response->getReasonPhrase());
                }
                /** @noinspection PhpComposerExtensionStubsInspection */
                $this->images_total = json_decode($response->getBody())->totalHits;
            } catch (GuzzleException $e) {
                $this->error('Exception loading total from image API: ' . $e->getMessage());
            }
        }
        return $this->images_total;
    }
}