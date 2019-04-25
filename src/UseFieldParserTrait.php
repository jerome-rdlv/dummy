<?php


namespace Rdlv\WordPress\Dummy;


trait UseFieldParserTrait
{
    /** @var FieldParser */
    private $field_parser;
    
    public function get_field_parser()
    {
        return $this->field_parser;
    }
    
    public function set_field_parser(FieldParser $field_parser)
    {
        $this->field_parser = $field_parser;
    }
}