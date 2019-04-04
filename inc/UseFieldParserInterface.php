<?php


namespace Rdlv\WordPress\Dummy;


interface UseFieldParserInterface
{
    /**
     * @param FieldParser $parser
     * @return mixed
     */
    public function set_field_parser(FieldParser $parser);

    /**
     * @return FieldParser
     */
    public function get_field_parser();
}