<?php

/** @noinspection PhpParamsInspection,PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Tests;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Generator\RandomNumber;
use Rdlv\WordPress\Dummy\Generator\RawValue;
use Rdlv\WordPress\Dummy\GeneratorCall;

class GeneratorCallTest extends TestCase
{
    public function testArgumentsArray()
    {
        $this->assertIsArray((new GeneratorCall(null, new RawValue()))->get_raw_args());
        $this->assertIsArray((new GeneratorCall(null, new RawValue(), 'one,two,three'))->get_raw_args());
        $this->assertCount(3, (new GeneratorCall(null, new RawValue(), 'one,two,three'))->get_raw_args());
        $this->assertEquals(['one', 'two'], (new GeneratorCall(null, new RawValue(), 'one,two'))->get_raw_args());
    }

    public function testArgsNormalization()
    {
        $args = [2, 'short', '8', null];
        $gc = new GeneratorCall(null, new RawValue(), $args);
        $this->assertEquals($args, $gc->get_raw_args());
        $this->assertEquals($args, $gc->get_args());
    }

    public function testArgsNormalizationWithSubGenerator()
    {
        $args = [
            2,
            new GeneratorCall(null, new RawValue(), 'test'),
            'short',
        ];
        $this->expectExceptionMessage('must be scalar');
        new GeneratorCall(null, new RawValue(), $args);
    }

    public function testArgsNormalizationWithSubArray()
    {
        $args = [
            2,
            [5, 4, 3],
            'short',
        ];
        $this->expectExceptionMessage('must be scalar');
        new GeneratorCall(null, new RawValue(), $args);
    }

    public function testArgsResolution()
    {
        $gc = new GeneratorCall(null, new RandomNumber(), [
            'min' => 2,
            'max' => new GeneratorCall(null, new RandomNumber(), [5, 20]),
        ]);
        
        // test args state before calling resolve
        $this->assertEquals(2, $gc->get_raw_args()['min']);
        $this->assertInstanceOf(GeneratorCall::class, $gc->get_raw_args()['max']);
        
        // resolve
        $resolved = $gc->get_args();
        $this->assertEquals(2, $resolved['min']);
        $this->assertGreaterThanOrEqual(5, $resolved['max']);
        $this->assertLessThanOrEqual(20, $resolved['max']);
        
        // test args state after calling resolve
        $this->assertEquals(2, $gc->get_raw_args()['min']);
        $this->assertInstanceOf(GeneratorCall::class, $gc->get_raw_args()['max']);
    }
}