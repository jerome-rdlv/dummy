<?php


namespace Rdlv\WordPress\Dummy;


class Field
{
    /** @var string Alias used on CLI to target the field */
    public $alias;
    
    /** @var string Handler id */
    public $handler;
    
    /** @var string Full field identification, with handler and name */
    public $key;

    /** @var string Name */
    public $name;
    
    /** @var string Content Type id */
    public $type;

    public $options;

    public $value;
}