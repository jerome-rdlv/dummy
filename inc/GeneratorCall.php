<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

class GeneratorCall
{
    /** @var GeneratorInterface */
    private $generator;
    
    /** @var mixed */
    private $options;
    
    public function __construct($generator, $options)
    {
        $this->generator = $generator;
        $this->options = $options;
    }

    /**
     * @param integer|null $post_id
     * @return mixed
     * @throws Exception
     */
    public function get($post_id = null)
    {
        if ($this->generator) {
            return $this->generator->get($this->options, $post_id);
        }
        else {
            // raw value
            return $this->options;
        }
    }
}