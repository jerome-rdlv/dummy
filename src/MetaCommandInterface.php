<?php


namespace Rdlv\WordPress\Dummy;


interface MetaCommandInterface
{
    /**
     * @param string $id
     * @param CommandInterface $command
     * @return void
     */
    public function add_command($id, $command);
}