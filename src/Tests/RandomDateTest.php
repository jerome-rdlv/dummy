<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Tests;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Generator\RandomDate;
use Rdlv\WordPress\Dummy\Generator\RawValue;
use Rdlv\WordPress\Dummy\GeneratorCall;

class RandomDateTest extends TestCase
{
    public function testNormalization()
    {
        $generator = new RandomDate();
        $this->assertEquals(['start' => '2 days ago'], $generator->normalize(['2 days ago']));
        $this->assertEquals(
            ['start' => '4 days ago', 'end' => '1 day ago'],
            $generator->normalize(['4 days ago', '1 day ago'])
        );
    }

    public function testNormalisationEmptyArgs()
    {
        $this->expectExceptionMessage("expect at least one argument");
        (new RandomDate())->normalize([]);
    }

    public function testNormalisationTooMuchArgs()
    {
        $this->expectExceptionMessage("expect at most two arguments");
        (new RandomDate())->normalize(['now', 'now', 'now']);
    }

    public function testStartValidation()
    {
        $generator = new RandomDate();
        $this->expectExceptionMessage("a 'start' argument is needed");
        $generator->validate(['test' => 'value']);
    }

    public function testEndValidation()
    {
        $generator = new RandomDate();
        $this->expectExceptionMessage("a 'end' argument is needed");
        $generator->validate(['start' => '2 days ago']);
    }

    public function testFormatValidation()
    {
        $generator = new RandomDate();
        $this->expectExceptionMessage("is not a valid date expression");
        $generator->validate([
            'start' => '2 days ago',
            'end'   => 'somewhere',
        ]);
    }
    
    public function testValidationWithDynamicValue()
    {
        (new RandomDate())->validate([
            'start' => 'now',
            'end' => new GeneratorCall(null, new RawValue(), '2 day ago'),
        ]);
        $this->assertEmpty(null);
    }

    public function testGeneration()
    {
        $generator = new RandomDate();
        $date_expr = '2 days ago';
        $date = date('Y-m-d', strtotime($date_expr));
        $this->assertStringStartsWith($date, $generator->get([
            'start' => $date_expr,
            'end'   => $date_expr,
        ]));

        $date = date('Y-m-d H:i:s');
        $this->assertEquals($date, $generator->get([]));
    }
}