<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

class GeneratorCall
{
    const EXCEPTION_FORMAT = '%s generator: %s';

    const ERROR_CODE_LOCKED = 2;

    /** @var string */
    private $generator_id;

    /** @var GeneratorInterface */
    private $generator;

    /** @var array|null */
    private $args;

    /** @var bool */
    private $locked = false;

    /**
     * @param string $generator_id
     * @param GeneratorInterface $generator
     * @param array|string|GeneratorCall|null $args
     * @throws Exception
     */
    public function __construct($generator_id, $generator, $args = null)
    {
        if (empty($generator_id)) {
            $this->generator_id = sprintf('class:%s', strtolower(get_class($generator)));
        } else {
            $this->generator_id = $generator_id;
        }
        $this->generator = $generator;
        $this->args = $this->prepare_args($args);
    }

    /**
     * Parse args as an array, then normalize and validate it
     * @param mixed $args
     * @return array|GeneratorCall
     * @throws Exception
     */
    private function prepare_args($args)
    {
        if ($args) {
            if (!$args instanceof GeneratorCall) {
                if (is_string($args)) {
                    $args = explode(',', $args);
                } elseif (!is_array($args)) {
                    $args = [$args];
                }
            }
            // $args is now an array or a GeneratorCall
            if (is_array($args)) {
                try {
                    $args = $this->normalize($args);

                    // lock to prevent generator validation to read generator values
                    $this->toggle_lock($args, true);
                    $this->generator->validate($args);
                    $this->toggle_lock($args, false);
                   
                } catch (Exception $e) {
                    throw new DummyException(sprintf(
                        self::EXCEPTION_FORMAT,
                        $this->generator_id,
                        $e->getMessage()
                    ));
                }
            }
        } else {
            $args = [];
        }
        return $args;
    }

    /**
     * Normalize scalar array of args
     * @param array $args
     * @return array
     * @throws Exception
     */
    private function normalize($args)
    {
        // check if normalisation is needed
        if (is_array($args) && is_int(key($args))) {

            // check all args are scalar
            foreach ($args as $arg) {
                if ($arg !== null && !is_scalar($arg)) {
                    throw new DummyException(sprintf(
                        self::EXCEPTION_FORMAT,
                        $this->generator_id,
                        sprintf(
                            "argument must be scalar or null but '%s' given (json encoded).",
                            json_encode($arg)
                        )
                    ));
                }
            }

            // normalize
            return $this->generator->normalize($args);
        }
        return $args;
    }

    /**
     * Return resolved args (sub generators are executed)
     * @param integer $post_id
     * @return array
     * @throws Exception
     */
    public function get_args($post_id = null)
    {
        // copy args to keep internal args unchanged
        $resolved = $this->args;
        $this->resolve_args($resolved, $post_id);
        return $resolved;
    }

    /**
     * @param integer $post_id
     * @param $args
     * @throws Exception
     */
    private function resolve_args(&$args, $post_id = null)
    {
        if ($args) {
            if (is_array($args)) {
                foreach ($args as &$arg) {
                    // recursive call in case $arg is an array
                    $this->resolve_args($arg, $post_id);
                }
            } elseif ($args instanceof self) {
                $args = $args->get($post_id);
            }
        }
    }

    /**
     * @param mixed $args
     * @param bool $lock To either to lock or unlock
     */
    private function toggle_lock(&$args, $lock = true)
    {
        if ($args) {
            if (is_array($args)) {
                foreach ($args as &$arg) {
                    $this->toggle_lock($arg, $lock);
                }
            } elseif ($args instanceof self) {
                $args->locked = $lock;
            }
        }
    }

    /**
     * @param integer|null $post_id
     * @return mixed
     * @throws Exception
     */
    public function get($post_id = null)
    {
        if ($this->locked) {
            throw new DummyException('validation tried to execute sub generator, which is prohibited.');
        }
        try {
            // get resolved args
            $args = $this->get_args($post_id);

            if (!is_array($args)) {
                throw new DummyException(sprintf(
                    self::EXCEPTION_FORMAT,
                    $this->generator_id,
                    sprintf(
                        "dynamic args must be an array, %s given (json encoded).",
                        json_encode($args)
                    )
                ));
            }

            // validate resolved args
            $this->generator->validate($args);

            // execute generator
            return $this->generator->get($args, $post_id);
        } catch (Exception $e) {
            throw new DummyException(sprintf(
                self::EXCEPTION_FORMAT,
                $this->generator_id,
                $e->getMessage()
            ));
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

    public function get_raw_args()
    {
        return $this->args;
    }
}