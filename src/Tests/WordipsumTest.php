<?php

/** @noinspection PhpParamsInspection,PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Tests;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Generator\Wordipsum;
use Rdlv\WordPress\Dummy\Generator\RawValue;
use Rdlv\WordPress\Dummy\GeneratorCall;

class WordipsumTest extends TestCase
{
    public function testCountNormalization()
    {
        $lw = new Wordipsum();

        $normalized = $lw->normalize([2]);
        $this->assertEquals(['count' => 2], $normalized);

        $normalized = $lw->normalize([2, 3]);
        $this->assertArrayHasKey('count', $normalized);
        $this->assertInstanceOf(GeneratorCall::class, $normalized['count']);
        
        /** @var GeneratorCall $gen_call */
        $gen_call = $normalized['count'];
        $this->assertEquals(['min' => 2, 'max' => 3], $gen_call->get_args());

        $this->assertEquals(Wordipsum::DEFAULT_WORD_COUNT, $lw->normalize([])['count']);
        $this->assertEquals(5, $lw->normalize([5])['count']);
    }
    
    public function testTooMuchNumbers()
    {
        $lw = new Wordipsum();
        $this->expectExceptionMessage("3 numbers given but only one");
        $lw->normalize([2, 3, 5]);
    }

    public function testInvalidCountException()
    {
        $lw = new Wordipsum();
        $this->expectExceptionMessage("must be a positive integer");
        $lw->validate(['count' => 'test']);
    }

    public function testNegativeCountException()
    {
        $lw = new Wordipsum();
        $this->expectExceptionMessage("must be a positive integer");
        $lw->validate(['count' => -2]);
    }

    public function testFloatCountException()
    {
        $lw = new Wordipsum();
        $this->expectExceptionMessage('must be a positive integer');
        $lw->validate(['count' => 2.3]);
    }
    
    public function testValidateDynamicValue()
    {
        (new Wordipsum())->validate(['count' => new GeneratorCall(null, new RawValue(), '6')]);
        $this->assertNull(null);
    }
}