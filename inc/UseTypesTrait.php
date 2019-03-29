<?php


namespace Rdlv\WordPress\Dummy;


trait UseTypesTrait
{
    /** @var TypeInterface[] */
    private $types = [];

    public function add_type($id, TypeInterface $type)
    {
        $this->types[$id] = $type;
    }
    
    public function get_type($id)
    {
        if (array_key_exists($id, $this->types)) {
            return $this->types[$id];
        }
        return null;
    }
    
    public function get_types()
    {
        return $this->types;
    }
}