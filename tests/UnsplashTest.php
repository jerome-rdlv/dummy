<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Tests;


use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Generator\RawValue;
use Rdlv\WordPress\Dummy\Generator\Unsplash;
use Rdlv\WordPress\Dummy\GeneratorCall;

class UnsplashTest extends TestCase
{
    public function testNormalize()
    {
        $gen = new Unsplash();
        $this->assertEquals(
            ['w' => 600, 'h' => 400, 'query' => 'test', 'orientation' => 'landscape'],
            $gen->normalize([600, 400, 'test', 'landscape'])
        );
        $this->assertEquals(
            ['query' => 'test', 'orientation' => 'portrait', 'random' => false],
            $gen->normalize(['test', 'portrait', 'sequential'])
        );
        $this->assertEquals(
            ['query' => 'cityscape', 'fallback' => 'buildings'],
            $gen->normalize(['cityscape', 'buildings'])
            
        );
        $this->assertEquals(['random' => true], $gen->normalize(['random']));
    }

    public function testNormalizeTooManyParameters()
    {
        $this->expectExceptionMessage("too many parameters");
        (new Unsplash())->normalize(['test', 'test2', 'est3']);
    }
    
    public function testValidationWithoutKey()
    {
        $this->expectExceptionMessage("must provide an Unsplash API Key");
        (new Unsplash())->validate([]);
    }

    public function testValidationUnknownOrientation()
    {
        $this->expectExceptionMessage("unknown orientation 'test'");
        $gen = new Unsplash();
        $gen->set_key('access_key');
        $gen->validate(['orientation' => 'test']);
    }

    public function testValidationInvalidWidth()
    {
        $this->expectExceptionMessage("w must be a positive integer greater than zero");
        $gen = new Unsplash();
        $gen->set_key('access_key');
        $gen->validate(['w' => 'test']);
    }

    public function testValidationNegativeWidth()
    {
        $this->expectExceptionMessage("w must be a positive integer greater than zero");
        $gen = new Unsplash();
        $gen->set_key('access_key');
        $gen->validate(['w' => -2]);
    }

    public function testValidationZeroWidth()
    {
        $this->expectExceptionMessage("w must be a positive integer greater than zero");
        $gen = new Unsplash();
        $gen->set_key('access_key');
        $gen->validate(['w' => 0]);
    }

    public function testValidationFloatWidth()
    {
        $this->expectExceptionMessage("w must be a positive integer greater than zero");
        $gen = new Unsplash();
        $gen->set_key('access_key');
        $gen->validate(['w' => 2.5]);
    }
    
    public function testValidationWithDynamicValues()
    {
        $gen = new Unsplash();
        $gen->set_key('access_key');
        $gen->validate([
            'w' => new GeneratorCall(null, new RawValue(), 200),
            'order' => new GeneratorCall(null, new RawValue(), 200),
            'orientation' => new GeneratorCall(null, new RawValue(), 200),
            'query' => new GeneratorCall(null, new RawValue(), 200),
        ]);
        $this->assertNull(null);
    }
}