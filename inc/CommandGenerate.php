<?php


namespace Rdlv\WordPress\Dummy;


use WP_CLI;

class CommandGenerate extends AbstractCommand implements CommandInterface, UseHandlersInterface, UseTypesInterface
{
    use UseHandlersTrait, UseTypesTrait, OutputTrait;

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

    const DEFAULT_TYPE = 'raw';

    // @formatter:off
    const ARG_PREG = '/^(?P<key>[^=]+)(=(?P<value>.*))?$/';
    const FIELD_KEY_PREG = '/^' .
        '(?P<key>' .
            '(' .
                '(?P<handler>%s)' .
                ':' .
            ')?' .
            '(?P<name>' .
                '[^=]+' .
            ')' .
        ')' .
    '$/';
    const FIELD_VALUE_PREG = '/^' .
        '(?P<value>' .
            '(?P<type>%s)' .
            '(' .
                ':' .
                '(?P<options>.*)' .
            ')?' .
            '|' .
            '.*' .
        ')' .
    '$/';
    // @formatter:on

    private $field_key_preg = null;
    private $field_value_preg = null;

    private $aliases;
    private $defaults;

    private $count;
    private $post_type;
    private $fields;

    public function __construct($aliases, $defaults)
    {
        $this->aliases = $aliases;
        $this->defaults = $defaults;
    }

    public function set_aliases($aliases)
    {
        $this->aliases = $aliases;
    }

    public function set_defaults($defaults)
    {
        $this->defaults = $defaults;
    }

    private function get_field_key_preg()
    {
        if ($this->field_key_preg === null) {
            $this->field_key_preg = sprintf(
                self::FIELD_KEY_PREG,
                implode('|', array_keys($this->handlers))
            );
        }
        return $this->field_key_preg;
    }

    private function get_field_value_preg()
    {
        if ($this->field_value_preg === null) {
            $this->field_value_preg = sprintf(
                self::FIELD_VALUE_PREG,
                implode('|', array_keys($this->types))
            );
        }
        return $this->field_value_preg;
    }

    /**
     * Generate the dummy content
     *
     * ## OPTIONS
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
     *
     * @throws \Exception
     */
    public function __invoke($args, $assoc_args)
    {
        $this->count = $assoc_args['count'];
        $this->post_type = $assoc_args['post-type'];
        
        $this->fields = $this->get_fields($args, $assoc_args);

        $this->init($args, $assoc_args);
    }

    /**
     * @throws \Exception
     */
    protected function run()
    {
        if (!function_exists('wp_insert_post') || !function_exists('wp_update_post')) {
            $this->error('WordPress admin must be loaded');
            exit(1);
        }

        // create posts
        $post_ids = [];
        $progress = 'create posts';
        for ($i = 0; $i < $this->count; ++$i) {
            $this->print_progress($progress, $i, $this->count);

            $data = array_filter(array_map(function ($field) {
                if (in_array($field->key, self::AUTHORIZED_FIELDS)) {
                    $type = $this->get_type($field->type);
                    return $type ? $type->get(null, $field->value) : false;
                } else {
                    return false;
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
                $handler = $this->get_handler($field->handler);

                if ($handler) {
                    foreach ($post_ids as $i => $post_id) {
                        $this->print_progress($progress, $i, count($post_ids));
                        $handler->generate($post_id, $field);
                    }
                }

                $this->print_progress($progress, count($post_ids), count($post_ids));
            }
        }

        WP_CLI::success(sprintf('created %s posts', count($post_ids)));
    }

    /**
     * @param $arg string
     * @return object|null
     */
    private function get_field($key, $value)
    {
        $field = new Field();
        $this->parse_key($key, $field);
        $this->parse_value($value, $field);
        return (object)$field;
    }

    /**
     * @param $key
     * @param Field $field
     * @return Field
     */
    private function parse_key($key, &$field)
    {
        if (preg_match($this->get_field_key_preg(), $this->resolve_alias($key), $m)) {
            $field->alias = $key;
            $field->key = $m['key'];
            $field->handler = $m['handler'];
            $field->name = $m['name'];

            if (!$field->handler && !in_array($field->name, self::AUTHORIZED_FIELDS)) {
                $this->error(sprintf('Bad field name: %s', $field->name));
            }
        } else {
            $this->error(sprintf('Field key regex error (blame the dev, this should never happen).'));
        }
        return $field;
    }

    /**
     * @param $value
     * @param Field $field
     * @return Field
     */
    private function parse_value($value, &$field)
    {
        if (preg_match($this->get_field_value_preg(), $value, $m)) {
            $field->value = $m['value'];
            $field->type = isset($m['type']) ? $m['type'] : null;
            $field->options = isset($m['options']) ? $m['options'] : null;

            if ($field->type) {
                if ($field->options) {
                    $field->value = $field->options;
                    $field->options = null;
                } else {
                    $field->value = null;
                }
            } else {
                $field->type = self::DEFAULT_TYPE;
            }
        } else {
            $this->error(sprintf('Field value regex error (blame the dev, this should never happen).'));
            exit(1);
        }
        return $field;
    }

    private function resolve_alias($alias)
    {
        return array_key_exists($alias, $this->aliases) ? $this->aliases[$alias] : $alias;
    }

    /**
     * @return array
     */
    private function get_fields($args, $assoc_args)
    {
        $fields = [];

        // parse args
        foreach ($args as $arg) {
            if (preg_match(self::ARG_PREG, $arg, $m, PREG_UNMATCHED_AS_NULL)) {
                $field = $this->get_field($m['key'], isset($m['value']) ? $m['value'] : '');
                $fields[$field->key] = $field;
            } else {
                $this->error(sprintf('Argument bad format: %s', $arg));
            }
        }

        // set defaults
        foreach ($this->defaults as $key => $value) {
            $default = $this->get_field($key, $value);
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
            str_pad($end ? '(' . $total . ')' : '(' . $count . '/' . $total . ')', floor(log($total) + 1) * 2 + 3,
                ' ')
        );
        if ($end) {
            echo "\n";
        }
    }
}