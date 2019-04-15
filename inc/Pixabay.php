<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use WP_CLI;

/**
 *
 * You can go to https://pixabay.com/en/photos/ to setup your image selection.
 *
 *
 * [--pixabay-api-key=<key>]
 * : The Pixabay API key can be obtained by creating an account
 * on the service https://pixabay.com/fr/accounts/register/
 *
 * [--pixabay-options=<defaults>]
 * : Options for image query. Possible values are:
 * [ horizontal, vertical ]
 * [ photo, illustration, vector ]
 * [ fashion, nature, backgrounds, science, education, people, feelings, religion, health, places, animals, industry, food, computer, sports, transportation, travel, buildings, business, music ]
 * [ grayscale, transparent, red, orange, yellow, green, turquoise, blue, lilac, pink, white, gray, black, brown ]
 * ---
 * default: photo/horizontal/industry
 * ---
 *
 * [--image-max-upload=<max>]
 * : Max image number to upload
 * ---
 * default: 25
 * ---
 */
class Pixabay extends AbstractImageGenerator implements GeneratorInterface, Initialized
{
    const API_IMAGE_URL = 'https://pixabay.com/api/';
    const API_IMAGE_DESC = '<a href="%1$s">Photo</a> by ' .
    '<a href="https://pixabay.com/users/%3$s-%2$s/">%3$s</a>';
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

    private $api_key;

    private $max_upload;

    private $images_params = [
        'editors_choice' => 'false', // true, [false]
        'safesearch'     => 'true',
    ];
    private $images_total = null;
    private $images_data = [];
    private $images_index = 0;

    public function set_key($api_key)
    {
        $this->api_key = $api_key;
    }

    public function init($args, $assoc_args)
    {
        // image API key
        if (isset($assoc_args['pixabay-key'])) {
            $this->api_key = $assoc_args['pixabay-key'];
        }

        $this->max_upload = $assoc_args['image-max-upload'];

        $this->images_params['key'] = $this->api_key;

        foreach (explode('/', $assoc_args['pixabay-options']) as $option) {
            if ($option) {
                $match = false;
                foreach (self::API_IMAGE_OPTIONS as $param => $values) {
                    if (in_array($option, $values)) {
                        $match = true;
                        $this->images_params[$param] = $option;
                        break;
                    }
                }
                if (!$match) {
                    WP_CLI::warning(sprintf('Unknown image option %s', $option));
                }
            }
        }
    }

    public function get($options, $post_id = null)
    {
        if ($this->images_index >= count($this->images_data)) {
            // load more images
            $this->load_images();
        }
        if (count($this->images_data) == 0) {
            throw new Exception('Pixabay API did not return any images.');
        }
        $image = $this->images_data[$this->images_index++ % count($this->images_data)];
        return $this->loadimage($image->url, $post_id, $image->desc);
    }

    /**
     * @throws Exception
     */
    private function load_images()
    {
        $images_count = count($this->images_data);
        $page_max_size = $this->max_upload - $images_count;

        if ($page_max_size <= 0) {
            return;
        }

        if (!$this->api_key) {
            throw new Exception('To use Pixabay image generator, you must provide a Pixabay API Key with either --pixabay-key option, or PIXABAY_KEY environment variable.');
        }

        $client = new Client();
        $per_page = max(
        // this is a hard min, API errors otherwise
            self::API_IMAGE_PAGE_SIZE_MIN,
            min(
                $page_max_size,
                rand(self::API_IMAGE_PAGE_SIZE_MIN, self::API_IMAGE_PAGE_SIZE_MAX)
            )
        );
        try {
            $response = $client->request('GET', self::API_IMAGE_URL, [
                'query' => array_replace([], $this->images_params, [
                    'per_page' => $per_page,
                    // get random page
                    'page'     => rand(1, floor($this->get_image_total() / $per_page)),
                ]),
            ]);

        } catch (GuzzleException $e) {
            throw new Exception('Exception loading images from API: ' . $e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Exception loading images from API: ' . $response->getReasonPhrase());
        }

        /** @noinspection PhpComposerExtensionStubsInspection */
        $images = json_decode($response->getBody())->hits;
        if (!$images) {
            throw new Exception('Exception loading images from API: json_decode returned null');
        }

        for ($i = 0; $i < min(count($images), $page_max_size); ++$i) {
            $this->images_data[] = (object)[
                'url'  => $images[$i]->largeImageURL,
                'desc' => sprintf(
                    self::API_IMAGE_DESC,
                    $images[$i]->pageURL,
                    $images[$i]->user_id,
                    $images[$i]->user
                ),
            ];
        }
    }

    /**
     * @return integer
     * @throws Exception
     */
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
                    throw new Exception('Exception loading total from image from API: ' . $response->getReasonPhrase());
                }
                /** @noinspection PhpComposerExtensionStubsInspection */
                $this->images_total = json_decode($response->getBody())->totalHits;
            } catch (GuzzleException $e) {
                throw new Exception('Exception loading total from image API: ' . $e->getMessage());
            }
        }
        return $this->images_total;
    }
}