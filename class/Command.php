<?php

namespace Rdlv\WordPress\Dummy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use WP_CLI;
use WP_CLI\ExitException;

class Command
{
    const FIELD_ALIASES = [
        'author'  => 'post_author',
        'content' => 'post_content',
        'date'    => 'post_date',
        'thumb'   => 'meta:_thumbnail_id',
        'title'   => 'post_title',
        'status'  => 'post_status',
    ];

    const TYPE_DATE = 'date';
    const TYPE_HTML = 'html';
    const TYPE_IMAGE = 'image';
    const TYPE_TEXT = 'text';
    const TYPE_RAW = 'raw';

    const PREFIX_META = 'meta';
    const PREFIX_ACF = 'acf';

    const DEFAULT_OPTIONS = [
        self::TYPE_DATE  => '4 months ago:now',
        self::TYPE_HTML  => '6/ul/h2/h3',
        self::TYPE_IMAGE => null,
        self::TYPE_TEXT  => '4:16',
        self::TYPE_RAW   => '',
    ];

    const DEFAULT_TYPE = self::TYPE_HTML;

    const DEFAULTS = [
        'content=' . self::TYPE_HTML . ':' . self::DEFAULT_OPTIONS[self::TYPE_HTML],
        'date=' . self::TYPE_DATE . ':' . self::DEFAULT_OPTIONS[self::TYPE_DATE],
        'thumb=' . self::TYPE_IMAGE . ':' . self::DEFAULT_OPTIONS[self::TYPE_IMAGE],
        'title=' . self::TYPE_TEXT . ':' . self::DEFAULT_OPTIONS[self::TYPE_TEXT],
        'status=publish',
        'author=1',
    ];

    const API_HTML_URL = 'https://loripsum.net/api/%s';

    const API_IMAGE_URL = 'https://pixabay.com/api/';
    const API_IMAGE_DESC = '<a href="%1$s">Photo</a> by ' .
    '<a href="https://pixabay.com/users/%3$s-%2$s/">%3$s</a>';
    const API_IMAGE_PAGE_SIZE_MIN = 3;  // api accept 3 as minimum per_page param value
    const API_IMAGE_PAGE_SIZE_MAX = 12;
    const API_IMAGE_LOAD_MAX = 25; // max loaded images
    const API_IMAGE_OPTIONS = [
        'orientation' => ['horizontal', 'vertical'],
        'image_type'  => ['photo', 'illustration', 'vector'],
        'category'    => ['fashion', 'nature', 'backgrounds', 'science', 'education', 'people', 'feelings', 'religion', 'health', 'places', 'animals', 'industry', 'food', 'computer', 'sports', 'transportation', 'travel', 'buildings', 'business', 'music'],
        'colors'      => ['grayscale', 'transparent', 'red', 'orange', 'yellow', 'green', 'turquoise', 'blue', 'lilac', 'pink', 'white', 'gray', 'black', 'brown'],
    ];

    const TEXT_START = 8;

    const AUTHORIZED_FIELDS = [
        'post_author',
        'post_date',
        'post_date_gmt',
        'post_content',
        'post_content_filtered',
        'post_title',
        'post_excerpt',
        'post_status',
        'comment_status',
        'ping_status',
        'post_password',
        'post_name',
        'post_modified',
        'post_modified_gmt',
        'post_parent',
        'menu_order',
        'post_mime_type',
        'post_category',
    ];

    const PROGRESS_MESSAGES = [
        'creating posts',
        'add acf fields',
        'add metas',
    ];

    private $image_api_key;
    private $count;
    private $post_type;

    private $images_params = [
//        'orientation'    => 'horizontal', // [all], horizontal, vertical
//        'image_type'     => 'illustration', // [all], photo, illustration, vector
//        'colors'         => 'blue', // grayscale, transparent, red, orange, yellow, green, turquoise, blue, lilac, pink, white, gray, black, brown
        'editors_choice' => 'true', // true, [false]
        'safesearch'     => 'true',
//        'order'          => 'popular', // latest, [popular]
    ];
    private $images_total = null;
    private $images_data = [];
    private $images_index = 0;

    private $current_post_id;

    private $alias_preg;
    private $arg_preg;

    private $max_progress_length = 0;

    public function __construct()
    {
        // @formatter:off
        $this->alias_preg = '/^' .
            '(?P<alias>' .
                '(' .
                    '(?P<prefix>' . self::PREFIX_META . '|' . self::PREFIX_ACF . ')' .
                    ':' .
                ')?' .
                '(?P<name>' .
                    '[^=]+' .
                ')' .
            ')' .
        '$/';
        
        $this->value_preg = '/^' .
            '(?P<value>' .
                '(?P<type>' .
                    implode('|', array_keys(self::DEFAULT_OPTIONS)) .
                ')' .
                '(' .
                    ':' .
                    '(?P<options>.*)' .
                ')?' .
                '|' .
                '.*' .
            ')' .
        '$/';
        
        $this->arg_preg = '/^(?P<alias>[^=]+)(=(?P<value>.*))?$/';
        // @formatter:on

        foreach (self::PROGRESS_MESSAGES as $msg) {
            $this->max_progress_length = max($this->max_progress_length, mb_strlen($msg));
        }
    }

    /**
     * Generate dummy content
     *
     * Below options takes a <expr> value which describe the content to
     * generate.
     *
     * ## FIELDS
     *
     * Meta fields are targeted with `meta:` prefix, for example `meta:_thumbnail_id`
     *
     * Following aliases are available:
     *
     * - `content` for `post_content`
     * - `date` for `post_date`
     * - `thumb` for meta `_thumbnail_id`
     *
     * ## CONTENTS
     *
     * <expr> is in format `type:options` with type being either `text`, `html`, `image` or `date`.
     *
     * Options are specific to each type and are detailed below.
     *
     * (To come: users with https://uinames.com/ or https://mockaroo.com/)
     *
     * HTML
     *
     * html,[count],[length:(s|m|l)],[elements:[a,ul,ol,dl,blockquote,code,heading,bold]:[option:[caps,prude]]
     * html/10/short/headers
     * same opts as https://loripsum.net/ except for headings.
     *
     * - text and html use https://loripsum.net/ API
     * - image uses https://pixabay.com/api/docs/
     * - date
     *
     * IMAGE
     *
     * Default image options are horizontal
     *
     *
     * DATE
     *
     * Date type accepts one or two values as from and to dates. These values are
     * parsed by `strtotime` so refer to its documentation.
     *
     * ## OPTIONS
     *
     * [--count=<count>]
     * : Number of posts to generate
     * ---
     * default: 10
     * ---
     *
     * [--post-type=<post_type>]
     * : The type of post to generate
     * ---
     * default: post
     * ---
     *
     * [--pixabay-key=<pixabay_api_key>]
     * : The API key for Pixabay. This value can also be set as environment variable
     * with PIXABAY_KEY
     *
     * [--image-options=<image_options>]
     * : Options for the loaded images
     * ---
     * default: photo/horizontal/industry
     * ---
     * It is possible to chose:
     *  - orientation:
     *    - horizontal
     *    - vertical
     *  - type
     *    - photo
     *    - illustration
     *    - vector
     *  - category
     *    fashion, nature, backgrounds, science, education, people,
     *    feelings, religion, health, places, animals, industry, food,
     *    computer, sports, transportation, travel, buildings, business, music
     *  - color
     *    grayscale, transparent, red, orange, yellow, green, turquoise, blue,
     *    lilac, pink, white, gray, black, brown
     *
     * [--thumb]
     * : Enable default thumb generation, enabled by default, use --no-thumb to disable
     *
     * [<fields>...]
     * : Fields to generate
     *
     * By default, the following values are applied:
     * - content:html:6/ul/decorate/h2/h3
     * - date:date:"1 year ago":"now"
     * - thumb:image
     *
     * These defaults can be disabled with --no-content, --no-date and --no-thumb
     *
     *
     * @when after_wp_load
     */
    public function generate($args, $assoc_args)
    {
        // image API key
        $this->image_api_key = getenv('PIXABAY_KEY');
        if (isset($assoc_args['pixabay-key'])) {
            $this->image_api_key = $assoc_args['pixabay-key'];
        }

        $this->images_params['key'] = $this->image_api_key;

        foreach (explode('/', $assoc_args['image-options']) as $option) {
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

        $this->count = $assoc_args['count'];
        $this->post_type = $assoc_args['post-type'];

        $fields = $this->get_fields($args, $assoc_args);

        // create posts
        $post_ids = [];
        $progress = self::PROGRESS_MESSAGES[0];

        for ($i = 0; $i < $this->count; ++$i) {
            $this->print_progress($progress, $i, $this->count);

            $data = array_filter(array_map(function ($field) {
                return in_array($field->key, self::AUTHORIZED_FIELDS) ? $this->get_content($field->type, $field->value) : false;
            }, $fields));
            $data['meta_input'] = [
                '_dummy' => 'creating',
            ];
            $post_id = wp_insert_post($data);

            if ($post_id !== 0) {
                $post_ids[] = $post_id;
            }
        }
        $this->print_progress($progress, $this->count, $this->count);

        // add acf fields
        $progress = self::PROGRESS_MESSAGES[1];
        foreach ($post_ids as $i => $post_id) {
            $this->current_post_id = $post_id;
            $this->print_progress($progress, $i, count($post_ids));
            foreach ($fields as $field) {
                if ($field->prefix === self::PREFIX_ACF) {
                    $this->add_acf($post_id, $field);
                }
            }
        }
        $this->current_post_id = false;
        $this->print_progress($progress, count($post_ids), count($post_ids));

        // add metas
        $progress = self::PROGRESS_MESSAGES[2];
        foreach ($post_ids as $i => $post_id) {
            $this->current_post_id = $post_id;
            $this->print_progress($progress, $i, count($post_ids));
            $metas = [
                '_dummy' => 'true',
            ];
            foreach ($fields as $field) {
                if ($field->prefix === self::PREFIX_META) {
                    $metas[substr($field->key, 5)] = $this->get_content($field->type, $field->value);
                }
            }

            wp_update_post([
                'ID'         => $post_id,
                'meta_input' => $metas,
            ]);
        }
        $this->current_post_id = false;
        $this->print_progress($progress, count($post_ids), count($post_ids));

        WP_CLI::success(sprintf('Created %s posts', count($post_ids)));
    }


    /**
     * Clean dummy content
     *
     * ## OPTIONS
     *
     * [--post-type=<post_type>]
     * : The type of post to generate
     * @param $args
     * @param $assoc_args
     */
    public function clean(
        /** @noinspection PhpUnusedParameterInspection */
        $args, $assoc_args)
    {
        global $wpdb;

        $query = "
            SELECT ID FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_dummy'
            WHERE pm.meta_value != 'false'
        ";
        if (!empty($assoc_args['post-type'])) {
            $query .= " AND p.post_type = '" . $assoc_args['post-type'] . "'";
        }
        $ids = $wpdb->get_col($query);
        if ($ids) {
            $ids_list = implode(', ', $ids);

            // drop attachment files if any
            $media_query = sprintf("
                SELECT pm.meta_value
                FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
                WHERE p.post_type = 'attachment' AND p.ID IN (%s)
                    AND pm.meta_key = '_wp_attached_file'
            ", $ids_list);
            $files = $wpdb->get_col($media_query);

            $uploads_dir = wp_upload_dir()['basedir'];
            foreach ($files as $file) {
                $paths = glob(preg_replace('/(\.[^.]+)$/', '*\1', $uploads_dir . '/' . $file));
                foreach ($paths as $path) {
                    unlink($path);
                }
            }

            /** @noinspection PhpFormatFunctionParametersMismatchInspection */
            $delete_queries = [
                "DELETE FROM $wpdb->postmeta WHERE post_id IN (%s)",
                "DELETE FROM $wpdb->posts WHERE ID IN (%s)",
            ];
            foreach ($delete_queries as $delete_query) {
                $wpdb->query(sprintf($delete_query, $ids_list));
            }
        }
        WP_CLI::success(sprintf('Deleted %s posts', count($ids)));
    }

    /**
     * @param $options
     */
    private function load_images()
    {
        $images_count = count($this->images_data);
        $page_max_size = self::API_IMAGE_LOAD_MAX - $images_count;

        if ($page_max_size <= 0) {
            return;
        }

        if (!$this->image_api_key) {
            $this->error('To use image type, you must provide a Pixabay API Key with either --pixabay-key option, or PIXABAY_KEY environment variable.');
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
                    'per_page' => $per_page,
                    // get random page
                    'page'     => rand(1, floor($this->get_image_total() / $per_page)),
                ]),
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->error('Exception loading images from API: ' . $response->getReasonPhrase());
            }

            $images = json_decode($response->getBody())->hits;
            if (!$images) {
                $this->error('Exception loading images from API: json_decode returned null');
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

        } catch (GuzzleException $e) {
            $this->error('Exception loading images from API: ' . $e->getMessage());
        }
    }

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
                $this->images_total = json_decode($response->getBody())->totalHits;
            } catch (GuzzleException $e) {
                $this->error('Exception loading total from image API: ' . $e->getMessage());
            }
        }
        return $this->images_total;
    }

    /**
     * todo add structure check based on $field_object['layouts']
     * @param $post_id
     * @param $field
     */
    private function add_acf($post_id, $field)
    {
        // pre-create field to be able to get field object
//        update_field($field->name, null, $post_id);
//        $field_object = get_field_object($field->name, $post_id);
        $value = json_decode($field->value, true);
        if ($value === null) {
            $value = $this->get_content($field->type, $field->value);
        } else {
            $this->acf_get_content($value);
        }
        update_field($field->name, $value, $post_id);
    }

    private function acf_get_content(&$data)
    {
        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                // do not touch acf_* items
                if (strpos($key, 'acf_') !== 0) {
                    if (is_string($value)) {
                        $field = (object)$this->parse_value($value);
                        $data[$key] = $this->get_content($field->type, $field->value);
                    } else {
                        $this->acf_get_content($value);
                    }
                }
            }
        }
    }

    /**
     * @param $field object
     * @return string Generated value
     */
    private function get_content($type, $value)
    {
        $function = sprintf('get_content_%s', $type);
        if (method_exists($this, $function)) {
            return $this->{$function}($type, $value);
        } else {
            $this->error(sprintf('Method %s does not exists.', $function));
            exit;
        }
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */


    /**
     * todo Generate correct random tree structure for headers
     * @param $options
     * @return string
     */
    private function get_content_html(
        /** @noinspection PhpUnusedParameterInspection */
        $type, $options)
    {
        $levels = array_filter(array_map(function ($option) {
            return preg_replace('/(h([1-6])|.*)/', '\2', $option);
        }, explode('/', $options)));
        $query = preg_replace('/\bh[1-6]\b/', 'headers', $options);

        try {
            $client = new Client();
            $response = $client->request('GET', sprintf(self::API_HTML_URL, $query));
            if ($response->getStatusCode() !== 200) {
                $this->error('Exception loading html from API: ' . $response->getReasonPhrase());
                exit;
            }
        } catch (GuzzleException $e) {
            $this->error('Exception loading html from API: ' . $e->getMessage());
            exit;
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

    /** @noinspection PhpUnusedPrivateMethodInspection */

    /**
     * @param $options
     * @return string
     */
    private function get_content_text(
        /** @noinspection PhpUnusedParameterInspection */
        $type, $options)
    {
        list($min, $max) = explode(':', $options);

        if (!$max) {
            return '';
        }

        try {
            $client = new Client();
            $response = $client->request('GET', sprintf(self::API_HTML_URL, '1/plaintext'));
            if ($response->getStatusCode() !== 200) {
                $this->error('Exception loading text from API: ' . $response->getReasonPhrase());
            }
        } catch (GuzzleException $e) {
            $this->error('Exception loading html from API: ' . $e->getMessage());
            exit;
        }

        $words = explode(' ', preg_replace('/[^a-z0-9]+/i', ' ', $response->getBody()));
        return ucfirst(strtolower(implode(' ', array_slice(
            $words,
            self::TEXT_START + rand(0, count($words) - $max),
            rand($min, $max)
        ))));
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */

    /**
     * @param $value
     * @return string
     */
    private function get_content_raw(
        /** @noinspection PhpUnusedParameterInspection */
        $type, $value)

    {
        return isset($value) ? $value : null;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */

    /**
     * @param $options
     * @return string
     */
    private function get_content_date(
        /** @noinspection PhpUnusedParameterInspection */
        $type, $options)
    {
        if ($options) {
            $opts = explode(':', $options);
            for ($i = 0; $i < 2; ++$i) {
                $opts[$i] = strtotime(isset($opts[$i]) ? $opts[$i] : 'now');
            }
            return date('Y-m-d H:i:s', rand($opts[0], $opts[1]));
        } else {
            return date('Y-m-d H:i:s');
        }
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */

    /**
     * @param $options
     * @return string
     */
    private function get_content_image(
        /** @noinspection PhpUnusedParameterInspection */
        $type, $value)
    {
        $image = $this->get_next_image();

        if (isset($image->id)) {
            return $image->id;
        }

        if (!function_exists('media_sideload_image')) {
            $this->error('Admin must be loaded for image upload');
            exit;
        }
        $image_id = media_sideload_image($image->url, $this->current_post_id, $image->desc, 'id');
        update_post_meta($image_id, '_dummy', true);
        $image->id = $image_id;
        return $image_id;
    }

    private function get_next_image()
    {
        if ($this->images_index >= count($this->images_data)) {
            // load more images
            $this->load_images();
        }
        return $this->images_data[$this->images_index++ % count($this->images_data)];
    }

    private function is_default_enabled($assoc_args, $alias)
    {
        return !isset($assoc_args[$alias]) || $assoc_args[$alias];
    }

    /**
     * @param $arg string
     * @return object|null
     */
    private function get_field($arg)
    {
        if (preg_match($this->arg_preg, $arg, $m, PREG_UNMATCHED_AS_NULL)) {
            $field = [];
            $this->parse_alias($m['alias'], $field);
            $this->parse_value(
                isset($m['value']) ? $m['value'] : '',
                $field
            );
            return (object)$field;
        } else {
            $this->error(sprintf('Argument bad format: %s', $arg));
        }
        return null;
    }

    private function parse_alias($alias, &$field = [])
    {
        if (preg_match($this->alias_preg, $this->resolve_alias($alias), $m)) {
            $field['alias'] = $alias;
            $field['prefix'] = $m['prefix'];
            $field['key'] = $m['alias'];
            $field['name'] = $m['name'];

            if (!$field['prefix'] && !in_array($field['name'], self::AUTHORIZED_FIELDS)) {
                $this->error(sprintf('Bad field name: %s', $field['name']));
            }
        } else {
            $this->error(sprintf('Alias regex error (blame the dev, this should never happen).'));
        }
        return $field;
    }

    private function parse_value($value, &$field = [])
    {
        if (preg_match($this->value_preg, $value, $m)) {

            foreach ($m as $key => $val) {
                if (!is_numeric($key)) {
                    $field[$key] = $val;
                }
            }

            if (isset($field['type'])) {
                if (isset($field['options'])) {
                    $field['value'] = $field['options'];
                    unset($field['options']);
                } else {
                    unset($field['value']);
                }
            } else {
                $field['type'] = self::TYPE_RAW;
            }
            if (isset($field['type']) && !isset($field['value'])) {
                // set default options
                $field['value'] = self::DEFAULT_OPTIONS[$field['type']];
            }
        } else {
            $this->error(sprintf('Value regex error (blame the dev, this should never happen).'));
            exit;
        }
        return $field;
    }

    private function resolve_alias($alias)
    {
        return array_key_exists($alias, self::FIELD_ALIASES) ? self::FIELD_ALIASES[$alias] : $alias;
    }

    /**
     * @param $args
     * @param $assoc_args
     * @return array
     */
    private function get_fields($args, $assoc_args)
    {
        $fields = [];

        // parse args
        foreach ($args as $arg) {
            $field = $this->get_field($arg);
            if ($field !== null) {
                $fields[$field->key] = $field;
            }
        }

        // set defaults
        foreach (self::DEFAULTS as $arg) {
            $default = $this->get_field($arg);
            if ($default !== null && !isset($fields[$default->key]) && $this->is_default_enabled($assoc_args, $default->alias)) {
                $fields[$default->key] = $default;
            }
        }

        return $fields;
    }

    private function print_progress($message, $count, $total)
    {
        $progress = $total !== 0 ? floor($count * 100 / $total) : 100;
        printf(
            "\r" . str_pad($message, $this->max_progress_length, ' ', STR_PAD_LEFT) . " %s %s%%",
            str_pad(str_pad('', $count, '.'), $total, ' '),
            $progress
        );
        if ($count === $total) {
            echo "\n";
        }
    }

    private function error($message)
    {
        echo "\n";
        try {
            WP_CLI::error($message);
        } catch (ExitException $e) {
            echo $e->getMessage();
            die($message);
        }
        exit;
    }
}