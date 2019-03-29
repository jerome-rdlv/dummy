<?php


namespace Rdlv\WordPress\Dummy;


interface UseTypesInterface
{
    /**
     * @param string $id
     * @param TypeInterface $type
     * @return void
     */
    public function add_type($id, TypeInterface $type);

    /**
     * @param string $id
     * @return TypeInterface
     */
    public function get_type($id);

    /**
     * @return TypeInterface[]
     */
    public function get_types();
}