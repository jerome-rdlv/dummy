<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use Symfony\Component\Yaml\Exception\ParseException;
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
            '(?P<arguments>.*)' .
        ')?' .
    '$/';
    // @formatter:on

    /** @var HandlerInterface[] */
    private $handlers = [];

    /** @var GeneratorInterface[] */
    private $generators = [];


    private $aliases;

    private $field_key_preg = null;
    private $field_value_preg = null;

    public function set_aliases($aliases)
    {
        $this->aliases = $aliases;
    }
    
    public function get_aliases()
    {
        return $this->aliases;
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
        $this->field_key_preg = null;
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
        $this->field_value_preg = null;
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

    /**
     * @param $arg
     * @return Field
     * @throws Exception
     */
    public function parse_field($arg)
    {
        if (preg_match(self::ARG_PREG, $arg, $m, PREG_UNMATCHED_AS_NULL)) {
            return $this->get_field($m['key'], isset($m['value']) ? $m['value'] : '');
        } else {
            throw new Exception(sprintf('Argument bad format: %s', $arg));
        }
    }

    /**
     * @param $key
     * @param $value
     * @return Field
     * @throws Exception
     */
    public function get_field($key, $value)
    {
        $field = new Field();
        $this->parse_field_key($key, $field);
        $field->callback = $value === null ? null : $this->parse_field_value($value);
        return $field;
    }

    private function resolve_alias($alias)
    {
        return $this->aliases && array_key_exists($alias, $this->aliases) ? $this->aliases[$alias] : $alias;
    }

    /**
     * @param $key
     * @param Field $field
     * @return Field
     * @throws Exception
     */
    private function parse_field_key($key, &$field)
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
     * @param string|array $value
     * @return GeneratorCall
     * @throws Exception
     */
    public function parse_field_value($value)
    {
        if (is_string($value)) {

//            // short syntax generator call: generator:arg1,arg2,arg3
//            $generator_call = $this->parse_generator_call($value);
//            if ($generator_call instanceof GeneratorCall) {
//                return $generator_call;
//            }

            try {
                // try parsing value
                $parser = new Parser();
                $value = $parser->parse($value);
            } catch (ParseException $e) {
                // parsing failed, consider raw value
                return new GeneratorCall(null, new RawValue(), $value);
            }
        }

        $this->resolve_generators($value);

        if (!$value instanceof GeneratorCall) {
            $value = new GeneratorCall(null, new RawValue(), $value);
        }

        return $value;
    }

    /**
     * @param $value
     * @return string|GeneratorCall
     * @throws Exception
     */
    public function parse_generator_call($value)
    {
        if (is_string($value) && preg_match($this->get_field_value_preg(), $value, $m)) {
            // value is a short syntax generator call
            return new GeneratorCall(
                $m['generator'],
                $this->get_generator($m['generator']),
                isset($m['arguments']) ? $m['arguments'] : null
            );
        }
        return $value;
    }

    /**
     * @param $value
     * @throws Exception
     */
    public function resolve_generators(&$value)
    {
        if (is_array($value)) {
            if (count($value) === 1) {
                $key = key($value);
                if (in_array($key, array_keys($this->generators))) {
                    $args = $value[$key];
                    if (is_string($args)) {
                        $args = $this->parse_generator_call($args);
                        if ($args instanceof GeneratorCall) {
                            $args = [$args];
                        }
                    } elseif (is_array($args)) {
                        // array may contain generator short syntax calls
                        $this->resolve_generators($args);
                    }
                    $value = new GeneratorCall($key, $this->get_generator($key), $args);
                } else {
                    $this->resolve_generators($value[$key]);
                }
            } else {
                foreach ($value as $key => &$subvalue) {
                    $this->resolve_generators($subvalue);
                }
            }
        } elseif (is_string($value)) {
            $value = $this->parse_generator_call($value);
        }
    }
}