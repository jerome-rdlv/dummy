<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use Symfony\Component\Yaml\Dumper;

/**
 * Generate the dummy content
 *
 * This command create posts with random content. It is possible to specify generation
 * rules for specific fields like post_status, post_excerpt, or thumbnail through
 * the meta handler.
 *
 * ## GENERATE
 *
 * [--without-defaults]
 * : Do not apply default fields
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
 * : Generation rules in the form field=options
 *
 * Following generators are available (see detailed doc below):
 * {generators}
 *
 * Following handlers are available (see detailed doc below):
 * {handlers}
 *
 * Following defaults are applied by command configuration:
 * {defaults}
 *
 * Aliases are available to target commons fields:
 * {aliases}
 *
 */
class CommandGenerate extends AbstractSubCommand implements UseFieldParserInterface, ExtendDocInterface
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

    public function set_defaults($defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * @param string $doc Documentation to extend
     * @return string
     */
    public function extend_doc($doc)
    {
        $dumper = new Dumper();
        $aliases = $this->field_parser->get_aliases();
        $generators = $this->field_parser->get_generators();
        $handlers = $this->field_parser->get_handlers();
        return str_replace(
            [
                '{generators}',
                '{handlers}',
                '{defaults}',
                '{aliases}',
            ],
            [
                // generators
                implode(", ", array_keys($generators)),
                // handlers
                implode(", ", array_keys($handlers)),
                // defaults
                implode("\n", array_map(function ($key, $val) use ($dumper) {
                    return "\t\t- $key=" . (is_array($val) ? $dumper->dump($val) : $val);
                }, array_keys($this->defaults), $this->defaults)),
                // aliases
                implode("\n", array_map(function ($key, $val) {
                    return "\t\t- $key=$val";
                }, array_keys($aliases), $aliases)),
            ],
            $doc
        );
    }

    public function validate($args, $assoc_args)
    {
        if (!function_exists('wp_insert_post') || !function_exists('wp_update_post')) {
            throw new DummyException('WordPress admin must be loaded');
        }

        // validate parameters
        if (!is_numeric($assoc_args['count']) || $assoc_args['count'] < 1) {
            throw new DummyException(sprintf(
                'count must be a number greater than 0 (%s given).',
                $assoc_args['count']
            ));
        }

        $post_types = get_post_types();
        if (!in_array($assoc_args['post-type'], $post_types)) {
            throw new DummyException(sprintf(
                'post type %s unknown, must be any of %s',
                $assoc_args['post-type'],
                implode(', ', $post_types)
            ));
        }

        $fields = $this->get_fields($args, $assoc_args);
        if (empty($fields)) {
            throw new DummyException('no fields defined, this task is empty.');
        } else {
            foreach ($fields as $field) {
                if ($field->handler === null && !in_array($field->name, self::AUTHORIZED_FIELDS)) {
                    throw new DummyException(sprintf(
                        'field "%s" is not authorized.',
                        $field->name
                    ));
                }
            }
        }
    }

    private function install_companion()
    {
        if (defined('WPMU_PLUGIN_DIR')) {
            $dest = WPMU_PLUGIN_DIR . '/dummy.php';
            if (!file_exists($dest)) {
                copy(dirname(__DIR__) . '/inc/dummy.php', $dest);
            }
        }
    }

    public function run($args, $assoc_args)
    {
        $this->count = $assoc_args['count'];
        $this->post_type = $assoc_args['post-type'];

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
                    throw new DummyException(sprintf('Bad field name: %s', $field->name));
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
            } else {
                throw new DummyException('post not created.');
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
    }

    /**
     * @param $args
     * @param $assoc_args
     * @return Field[]
     * @throws Exception
     */
    public function get_fields($args, $assoc_args = [])
    {
        $fields = [];

        // parse args
        if (is_array($args)) {
            foreach ($args as $key => $arg) {
                if (is_string($key)) {
                    $field = $this->field_parser->get_field($key, $arg);
                } else {
                    $field = $this->field_parser->parse_field($arg);
                }
                $fields[$field->key] = $field;
            }
        }

        // set defaults
        if ($this->defaults && $this->are_defaults_enabled($assoc_args)) {
            foreach ($this->defaults as $key => $value) {
                $default = $this->field_parser->get_field($key, $value);
                if ($default !== null && !array_key_exists($default->key, $fields)) {
                    $fields[$default->key] = $default;
                }
            }
        }

        $fields = array_filter($fields, function ($field) {
            return $field->callback !== null;
        });

        return $fields;
    }

    private function are_defaults_enabled($assoc_args)
    {
        return !isset($assoc_args['without-defaults']) || $assoc_args['without-defaults'] !== true;
    }
}