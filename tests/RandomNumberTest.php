<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Tests;


use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Generator\RandomNumber;
use Rdlv\WordPress\Dummy\Generator\RawValue;
use Rdlv\WordPress\Dummy\GeneratorCall;

class RandomNumberTest extends TestCase
{
    public function testCountNormalization()
    {
        $this->assertEquals(['min' => 2, 'max' => 3], (new RandomNumber())->normalize([2, 3]));
    }

    public function testNormalizationEmptyArgs()
    {
        $this->expectExceptionMessage("expect two arguments, none given");
        (new RandomNumber())->normalize([]);
    }

    public function testNormalizationNotEnoughNumbers()
    {
        $this->expectExceptionMessage("expect two arguments, 1 given");
        (new RandomNumber())->normalize([2]);
    }

    public function testTooManyNumbers()
    {
        $this->expectExceptionMessage("expect two arguments, 3 given");
        (new RandomNumber())->normalize([2, 3, 5]);
    }

    public function testNormalization()
    {
        $gen = new RandomNumber();
        $this->assertEquals(['min' => 0, 'max' => 10], $gen->normalize([0, 10]));
    }

    public function testValidationArgsMissing()
    {
        $this->expectExceptionMessage("arguments are needed");
        (new RandomNumber())->validate([]);
    }

    public function testValidationArgMissing()
    {
        $this->expectExceptionMessage("'max' argument is needed");
        (new RandomNumber())->validate(['min' => 0]);
    }

    public function testValidationArgInvalid()
    {
        $this->expectExceptionMessage("'min' argument must be an integer");
        (new RandomNumber())->validate(['min' => 'ago']);
    }

    public function testDynamicArg()
    {
        (new RandomNumber())->validate([
            'min' => 2,
            'max' => new GeneratorCall(null, new RawValue(), '10'),
        ]);
        $this->assertEmpty(null);
    }
}