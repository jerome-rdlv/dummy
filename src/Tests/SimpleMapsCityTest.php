<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Tests;


use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Generator\SimpleMapsCity;

class SimpleMapsCityTest extends TestCase
{
    public function testNormalize()
    {
        $gen = new SimpleMapsCity();
        $this->assertEquals(
            ['country_code' => 'FR'],
            $gen->normalize(['FR'])
        );
        $this->assertEquals(
            ['country_code' => 'FR', 'format' => 'test'],
            $gen->normalize(['FR', 'test'])
        );
        $this->assertEquals(
            ['country_code' => 'US', 'format' => 'test'],
            $gen->normalize(['test', 'US'])
        );
    }
    
    public function testNormalizeTooManyArgs()
    {
        $this->expectExceptionMessage("at most two arguments expected");
        (new SimpleMapsCity())->normalize(['AU', 'test', 'test']);
    }
}