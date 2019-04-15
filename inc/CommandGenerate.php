<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use WP_CLI;

/**
 * Generate the dummy content
 *
 * This command create posts with random content. It is possible to specify generation
 * rules for specific fields like post_status, post_excerpt, or thumbnail through
 * the meta handler.
 *
 * ## GENERATE
 *
 * [--count=<count>]
 * : Number of posts to generate
 * ---
 * default: 10
 * ---
 *
 * [--post-type=<post-type>]
 * : The type of post to generate
 * ---
 * default: post
 * ---
 *
 * [<fields>...]
 * : Fields to generate
 */
class CommandGenerate extends AbstractCommand implements CommandInterface, UseFieldParserInterface
{
    use UseFieldParserTrait;

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

    private $defaults;

    private $count;
    private $post_type;

    /** @var Field[] */
    private $fields;

    /** @var FieldParser */
    private $field_parser;

    public function __construct($defaults)
    {
        $this->defaults = $defaults;
    }

    public function set_defaults($defaults)
    {
        $this->defaults = $defaults;
    }

    protected function validate($args, $assoc_args)
    {
        $this->count = $assoc_args['count'];
        $this->post_type = $assoc_args['post-type'];

        if (!function_exists('wp_insert_post') || !function_exists('wp_update_post')) {
            throw new Exception('WordPress admin must be loaded');
        }

        // validate parameters
        if (!is_numeric($this->count) || $this->count < 1) {
            throw new Exception(sprintf(
                'Count must be a number greater than 0 (%s given).',
                $this->count
            ));
        }

        $post_types = get_post_types();
        if (!in_array($this->post_type, $post_types)) {
            throw new Exception(sprintf(
                'Post type %s unknown, must be any of %s',
                $this->post_type,
                implode(', ', $post_types)
            ));
        }
    }

    private function install_companion()
    {
        if (defined('WPMU_PLUGIN_DIR')) {
            $dest = WPMU_PLUGIN_DIR . '/dummy.php';
            if (!file_exists($dest)) {
                copy(dirname(__DIR__) . '/dummy.php', $dest);
            }
        }
    }

    protected function run($args, $assoc_args)
    {
        $this->install_companion();

        $this->fields = $this->get_fields($args, $assoc_args);

        // create posts
        $post_ids = [];
        $progress = 'create posts';
        for ($i = 0; $i < $this->count; ++$i) {
            $this->print_progress($progress, $i, $this->count);

            $data = array_filter(array_map(function (Field $field) {
                if ($field->handler) {
                    return false;
                } elseif (!in_array($field->name, self::AUTHORIZED_FIELDS)) {
                    throw new Exception(sprintf('Bad field name: %s', $field->name));
                } else {
                    return $field->get_value();
                }
            }, $this->fields));

            $data['post_type'] = $this->post_type;
            $data['meta_input'] = [
                '_dummy' => true,
            ];

            $post_id = wp_insert_post($data);

            if ($post_id !== 0) {
                $post_ids[] = $post_id;
            }
        }
        $this->print_progress($progress, $this->count, $this->count);

        foreach ($this->fields as $field) {
            if ($field->handler) {
                $progress = 'generate ' . $field->alias;
                foreach ($post_ids as $i => $post_id) {
                    $this->print_progress($progress, $i, count($post_ids));
                    $field->generate($post_id);
                }
                $this->print_progress($progress, count($post_ids), count($post_ids));
            }
        }

        WP_CLI::success(sprintf('created %s posts', count($post_ids)));
    }

    /**
     * @param $args
     * @param $assoc_args
     * @return array
     * @throws Exception
     */
    private function get_fields($args, $assoc_args)
    {
        $fields = [];

        // parse args
        foreach ($args as $arg) {
            $field = $this->field_parser->parse_field($arg);
            $fields[$field->key] = $field;
        }

        // set defaults
        foreach ($this->defaults as $key => $value) {
            $default = $this->field_parser->get_field($key, $value);
            if ($default !== null && !isset($fields[$default->key]) && $this->is_default_enabled($assoc_args,
                    $default->alias)) {
                $fields[$default->key] = $default;
            }
        }

        return $fields;
    }

    private function is_default_enabled($assoc_args, $alias)
    {
        return !isset($assoc_args[$alias]) || $assoc_args[$alias];
    }

    private function print_progress($message, $count, $total)
    {
        $end = $count === $total;
        printf(
            "\r %s " . $message . " %s",
            WP_CLI::colorize($end ? '%G✔%n' : '%w·%n'),
            str_pad(
                $end ? '(' . $total . ')' : '(' . $count . '/' . $total . ')',
                floor(log($total) + 1) * 2 + 3,
                ' '
            )
        );
        if ($end) {
            echo "\n";
        }
    }
}