<?php


namespace Rdlv\WordPress\Dummy\Handler;


use Exception;
use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\Field;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\HandlerInterface;
use Rdlv\WordPress\Dummy\Initialized;
use Rdlv\WordPress\Dummy\UseFieldParserInterface;
use Rdlv\WordPress\Dummy\UseFieldParserTrait;

/**
 * Populate ACF field
 *
 * ACF fields are filled with random content according to their type by default.
 * If a field is not found for the created post, an error will be thrown.
 * Complex types are populated recursively, for example Flex and Repeater.
 *
 * Example:
 *
 *      {id}:description
 *
 * If ACF field description is of type wysiwyg, it will be filled with random
 * HTML content.
 *
 *      {id}:contents
 *
 * If ACF field contents exists for the created post and is of type flex, a random
 * number of blocs will be created with a random layout for each, and subfields of
 * each bloc will also be populated.
 */
class AcfHandler implements HandlerInterface, UseFieldParserInterface, Initialized
{
    use UseFieldParserTrait;

    private $post_type;

    /**
     * Connections between ACF field types and values
     * @var GeneratorCall[]
     */
    private $connections = [];

    private $initialized = false;

    /**
     * @param $connections
     */
    public function set_connections($connections)
    {
        $this->connections = $connections;
    }

    /**
     * @throws Exception
     */
    public function init()
    {
        if (!$this->initialized) {
            $this->initialized = true;

            // parse / initialize connections
            foreach ($this->connections as $key => $connection) {
                $this->connections[$key] = $this->field_parser->parse_field_value($connection);
            }
        }
    }

    public function init_task($args, $assoc_args, $globals)
    {
        $this->init();

        if (!function_exists('acf_get_field_groups')) {
            throw new DummyException('ACF is not loaded.');
        }
        if (!empty($globals['post-type'])) {
            $this->post_type = $globals['post-type'];
        }
    }

    public function generate($post_id, $field)
    {
        $acf_field_object = $this->get_acf_field_object($field, $post_id);
        if (!$acf_field_object) {
            throw new DummyException(sprintf(
                'ACF: field %s not found for post %s of type %s',
                $field->name,
                $post_id,
                $this->post_type
            ));
        }

        update_field(
            $field->name,
            $this->populate($post_id, $acf_field_object),
            $post_id
        );
    }

    private function get_rand_count($field_object, $max, $min = 1)
    {
        return rand(
            $field_object['min'] ? $field_object['min'] : $min,
            min(
                $field_object['max'] ? $field_object['max'] : $max,
                $max
            )
        );
    }

    /**
     * @param integer $post_id
     * @param array $acf_field_object
     * @return mixed
     * @throws Exception
     */
    private function populate($post_id, $acf_field_object)
    {
        $acf_field_type = $acf_field_object['type'];
        switch ($acf_field_type) {
            case 'tab':
            case 'message':
                return null;
            case 'flexible_content':
                $rows = [];
                $layouts = $acf_field_object['layouts'];

                $count = $this->get_rand_count($acf_field_object, floor(1.6 * count($layouts)));
                for ($i = 0; $i < $count; ++$i) {
                    $layout = $layouts[array_rand($layouts)];
                    $row = [];
                    foreach ($layout['sub_fields'] as $sub_field) {
                        $value = $this->populate($post_id, $sub_field);
                        if ($value) {
                            $row[$sub_field['name']] = $value;
                        }
                    }
                    $row['acf_fc_layout'] = $layout['name'];
                    $rows[] = $row;
                }
                return $rows;
            case 'repeater':
                $rows = [];
                $count = $this->get_rand_count($acf_field_object, 8);
                for ($i = 0; $i < $count; ++$i) {
                    $row = [];
                    foreach ($acf_field_object['sub_fields'] as $sub_field) {
                        $value = $this->populate($post_id, $sub_field);
                        if ($value) {
                            $row[$sub_field['name']] = $value;
                        }
                    }
                    $rows[] = $row;
                }
                return $rows;
            case 'selection':
            case 'checkbox':
            case 'radio':
                $choices = $acf_field_object['choices'];
                return $choices[array_rand($choices)];
            case 'number':
                return $this->get_rand_count($acf_field_object, 1000);
            default:
                if (array_key_exists($acf_field_type, $this->connections)) {
                    return $this->connections[$acf_field_type]->get($post_id);
                } else {
                    return null;
                }
        }
    }

    /**
     * @param Field $field
     * @param integer $post_id
     * @return array|null The ACF field object if found
     */
    private function get_acf_field_object($field, $post_id)
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        $groups = acf_get_field_groups([
            'post_id'   => $post_id,
            'post_type' => $this->post_type,
        ]);

        $fields = [];

        if ($groups) {
            foreach ($groups as $group) {
                /** @noinspection PhpUndefinedFunctionInspection */
                $fields = array_merge($fields, acf_get_fields($group));
            }
        }

        if ($fields) {
            for ($i = count($fields) - 1; $i >= 0; --$i) {
                if ($fields[$i]['key'] === $field->name) {
                    return $fields[$i];
                } elseif ($fields[$i]['name'] === $field->name) {
                    return $fields[$i];
                }
            }
        }

        return null;
    }
}