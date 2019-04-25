<?php

/** @noinspection PhpParamsInspection,PhpUnhandledExceptionInspection */

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Loripsum;

class LoripsumTest extends TestCase
{
    public function testUnknownArgument()
    {
        $this->expectExceptionMessageRegExp('/unknown argument "test"/');
        (new Loripsum())->validate([2, 'test']);
    }
    
    public function testNegativeWordCount()
    {
        $this->expectExceptionMessage('must be a positive integer');
        (new Loripsum())->validate([-1, 'ul']);
    }
    
    public function testFloatWordCount() {
        $this->expectExceptionMessage('must be a positive integer');
        (new Loripsum())->validate([1.2, 'h2']);
    }
    
    public function testDoubleLengthArgument()
    {
        $this->expectExceptionMessage('length is already set');
        (new Loripsum())->validate([2, 'medium', 'h2', 'short']);
    }
}