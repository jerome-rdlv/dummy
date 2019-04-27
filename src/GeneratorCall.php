<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

class GeneratorCall
{
    const EXCEPTION_FORMAT = '%s generator: %s';

    /** @var string */
    private $generator_id;

    /** @var GeneratorInterface */
    private $generator;

    /** @var array|null */
    private $args;

    /**
     * @param GeneratorInterface $generator
     * @param array|string|null $args
     * @throws Exception
     */
    public function __construct($generator_id, $generator, $args = null)
    {
        if ($args) {
            if (is_array($args)) {
                $args = $this->resolve_args($args);
            } else {
                $args = explode(',', $args);
            }
            try {
                $args = $generator->normalize($args);
                $errors = $generator->validate($args);
                if ($errors) {
                    throw new Exception(sprintf(
                        self::EXCEPTION_FORMAT,
                        $generator_id,
                        count($errors) === 1 ? $errors[0] : "\n\t - ". implode("\n\t - ", $errors)
                    ));
                }
            } catch (Exception $e) {
                if ($generator_id) {
                    throw new Exception(sprintf(
                        self::EXCEPTION_FORMAT,
                        $generator_id,
                        $e->getMessage()
                    ));
                }
                else {
                    throw $e;
                }
            }
        } else {
            $args = [];
        }

        $this->generator_id = $generator_id;
        $this->generator = $generator;
        $this->args = $args;
    }

    /**
     * @throws Exception
     */
    private function resolve_args($args)
    {
        if ($args && is_array($args)) {
            foreach ($args as &$arg) {
                if ($arg instanceof self) {
                    // resolve value
                    $arg = $arg->get();
                } else {
                    // recursive call in case $arg is an array
                    $arg = $this->resolve_args($arg);
                }
            }
        }
        return $args;
    }

    /**
     * @param integer|null $post_id
     * @return mixed
     * @throws Exception
     */
    public function get($post_id = null)
    {
        try {
            return $this->generator->get($this->args, $post_id);
        } catch (Exception $e) {
            if ($this->generator_id) {
                throw new Exception(sprintf(
                    self::EXCEPTION_FORMAT,
                    $this->generator_id,
                    $e->getMessage()
                ));
            }
            else {
                throw $e;
            }
        }
    }
    
    public function get_generator_id()
    {
        return $this->generator_id;
    }

    public function get_generator()
    {
        return $this->generator;
    }

    public function get_args()
    {
        return $this->args;
    }
}