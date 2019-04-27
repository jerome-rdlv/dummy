<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Test;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\SequentialDate;

class SequentialDateTest extends TestCase
{
    public function testNormalization()
    {
        $generator = new SequentialDate();
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

    public function testEmptyCountValidation()
    {
        $generator = new SequentialDate();
        $this->expectExceptionMessage('count must be a positive integer');
        $generator->validate([]);
    }

    public function testZeroCountValidation()
    {
        $generator = new SequentialDate();
        $this->expectExceptionMessage('count must be a positive integer');
        $generator->init_task([], [], ['count' => 0]);
        $generator->validate([]);
    }

    public function testFloatCountValidation()
    {
        $generator = new SequentialDate();
        $this->expectExceptionMessage('count must be a positive integer');
        $generator->init_task([], [], ['count' => 2.3]);
        $generator->validate([]);
    }

    public function testStartValidation()
    {
        $generator = new SequentialDate();
        $this->expectExceptionMessage("a 'start' argument is needed");
        $generator->init_task([], [], ['count' => 2]);
        $generator->validate(['test' => 'value']);
    }

    public function testEndValidation()
    {
        $generator = new SequentialDate();
        $this->expectExceptionMessage("a 'end' argument is needed");
        $generator->init_task([], [], ['count' => 2]);
        $generator->validate(['start' => '2 days ago']);
    }

    public function testFormatValidation()
    {
        $generator = new SequentialDate();
        $this->expectExceptionMessage("is not a valid date expression");
        $generator->init_task([], [], ['count' => 2]);
        $generator->validate([
            'start' => '2 days ago',
            'end'   => 'somewhere',
        ]);
    }
    
    public function testGeneration()
    {
        $generator = new SequentialDate();
        $generator->init_task([], [], ['count' => 1]);
        $args = [
            'start' => '10 november 2018',
            'end' => '12 november 2018',
        ];
        $this->assertStringStartsWith('2018-11-10', $generator->get($args));

        // change args to reset index
        $generator->init_task([], [], ['count' => 2]);
        $args = [
            'start' => '10 october 2018',
            'end' => '12 october 2018',
        ];
        $this->assertStringStartsWith('2018-10-10', $generator->get($args));
        $this->assertStringStartsWith('2018-10-12', $generator->get($args));
        
        $generator->init_task([], [], ['count' => 3]);
        $args = [
            'start' => '10 september 2018',
            'end' => '12 september 2018',
        ];
        $this->assertStringStartsWith('2018-09-10', $generator->get($args));
        $this->assertStringStartsWith('2018-09-11', $generator->get($args));
        $this->assertStringStartsWith('2018-09-12', $generator->get($args));

        // reverse order
        $args = [
            'start' => '12 august 2018',
            'end' => '10 august 2018',
        ];
        $generator->init_task([], [], ['count' => 3]);
        $this->assertStringStartsWith('2018-08-12', $generator->get($args));
        $this->assertStringStartsWith('2018-08-11', $generator->get($args));
        $this->assertStringStartsWith('2018-08-10', $generator->get($args));
    }
    
    public function testGenerationWithoutArgs()
    {
        $generator = new SequentialDate();
        $generator->init_task([], [], ['count' => 12]);
        $date = date('Y-m-d H:i:s');
        $this->assertEquals($date, $generator->get([]));
    }
}