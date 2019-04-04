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
     * @param array $context
     * @return mixed
     * @throws Exception
     */
    public function __invoke($context = [])
    {
        if ($this->generator) {
            return $this->generator->get($this->options, $context);
        }
        else {
            // raw value
            return $this->options;
        }
    }
}