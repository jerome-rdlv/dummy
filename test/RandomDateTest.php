<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Test;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\RandomDate;

class RandomDateTest extends TestCase
{
    public function testNormalization()
    {
        $generator = new RandomDate();
        $this->assertEquals([], $generator->normalize([]));
        $this->assertEquals(['start' => '2 days ago'], $generator->normalize(['2 days ago']));
        $this->assertEquals(
            ['start' => '4 days ago', 'end' => '1 day ago'],
            $generator->normalize(['4 days ago', '1 day ago'])
        );
        $this->assertEquals(
            ['start' => 'now', 'end' => 'now'],
            $generator->normalize(['now', 'now', '2 months ago'])
        );
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