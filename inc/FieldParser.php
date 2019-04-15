<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use Symfony\Component\Yaml\Parser;

class FieldParser
{
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
        '(?P<generator>%s)' .
        '(' .
            ':' .
            '(?P<options>.*)' .
        ')?' .
    '$/';
    // @formatter:on

    /** @var HandlerInterface[] */
    private $handlers = [];

    /** @var GeneratorInterface[] */
    private $generators = [];


    private $aliases;
    private $defaults;

    private $field_key_preg = null;
    private $field_value_preg = null;

    public function __construct($aliases)
    {
        $this->aliases = $aliases;
    }

    public function set_aliases($aliases)
    {
        $this->aliases = $aliases;
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
                implode('|', array_keys($this->generators))
            );
        }
        return $this->field_value_preg;
    }

    public function add_handler($id, HandlerInterface $handler)
    {
        $this->handlers[$id] = $handler;
    }

    public function get_handler($id)
    {
        if (array_key_exists($id, $this->handlers)) {
            return $this->handlers[$id];
        }
        return null;
    }

    public function get_handlers()
    {
        return $this->handlers;
    }

    public function add_generator($id, GeneratorInterface $generator)
    {
        $this->generators[$id] = $generator;
    }

    public function get_generator($id)
    {
        if (array_key_exists($id, $this->generators)) {
            return $this->generators[$id];
        }
        return null;
    }

    public function get_generators()
    {
        return $this->generators;
    }

    public function parse_field($arg)
    {
        if (preg_match(self::ARG_PREG, $arg, $m, PREG_UNMATCHED_AS_NULL)) {
            return $this->get_field($m['key'], isset($m['value']) ? $m['value'] : '');
        } else {
            throw new Exception(sprintf('Argument bad format: %s', $arg));
            exit;
        }
    }

    public function get_field($key, $value)
    {
        $field = new Field();
        $this->parse_key($key, $field);
        $field->callback = $this->parse_value($value);
        return $field;
    }

    private function resolve_alias($alias)
    {
        return array_key_exists($alias, $this->aliases) ? $this->aliases[$alias] : $alias;
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
            $field->handler = empty($m['handler']) ? null : $this->handlers[$m['handler']];
            $field->name = $m['name'];
        } else {
            throw new Exception(sprintf('Field key regex error (blame the dev, this should never happen).'));
        }
        return $field;
    }

    /**
     * @param $raw_value
     * @return GeneratorCall
     */
    public function parse_value($raw_value)
    {
        /* Simple value format is:
         * generator:arg1,arg2,arg3
         * It is transformed in following yaml:
         * {generator:[arg1,arg2,arg3]}
         */

        // transform simple format to yaml
        $value = preg_replace_callback($this->get_field_value_preg(), function ($m) {
            $yaml_regex = '/^(\{.*\}|\[.*\]|".*"|\'.*\')$/';

            if (preg_match($yaml_regex, $m[0])) {
                return $m[0];
            }

            $value = '{' . $m['generator'] . ': ';
            if (empty($m['options'])) {
                $value .= '{}';
            } elseif (!preg_match($yaml_regex, $m['options'])) {
                $value .= '[' . $m['options'] . ']';
            }
            $value .= '}';
            return $value;
        }, $raw_value);

        // try parsing value
        $parser = new Parser();
        try {
            $value = $parser->parse($value);
        } catch (Exception $e) {
            // parsing failed, consider raw value
            $value = $raw_value;
        }

        $this->resolve_generators($value);
        
        if (!$value instanceof GeneratorCall) {
            $value = new GeneratorCall(null, $value);
        }

        return $value;
    }

    /**
     * @param $value
     * @return array|GeneratorCall
     */
    public function resolve_generators(&$value)
    {
        if (is_array($value)) {
            if (count($value) === 1) {
                $key = key($value);
                if (in_array($key, array_keys($this->generators))) {
                    $value = new GeneratorCall($this->get_generator($key), $value[$key]);
                } else {
                    $this->resolve_generators($value[$key]);
                }
            } else {
                foreach ($value as $key => &$subvalue) {
                    $this->resolve_generators($subvalue);
                }
            }
        }
        return $value;
    }

}