<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Test;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\RawValue;

class GeneratorCallTest extends TestCase
{
    public function testArgumentsArray()
    {
        $this->assertIsArray((new GeneratorCall(null, new RawValue()))->get_args());
        $this->assertIsArray((new GeneratorCall(null, new RawValue(), 'one,two,three'))->get_args());
        $this->assertEquals(['one', 'two'], (new GeneratorCall(null, new RawValue(), 'one,two'))->get_args());
    }
}