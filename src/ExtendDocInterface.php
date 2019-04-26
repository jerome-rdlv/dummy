<?php

namespace Rdlv\WordPress\Dummy;


interface ExtendDocInterface
{
    /**
     * @param string $doc Documentation to extend
     * @return string Modified documentation
     */
    public function extend_doc($doc);
}