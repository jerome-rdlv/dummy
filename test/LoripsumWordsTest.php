<?php

/** @noinspection PhpParamsInspection,PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Test;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\LoripsumWords;

class LoripsumWordsTest extends TestCase
{
    public function testCountNormalization()
    {
        $lw = new LoripsumWords();
        $this->assertArrayHasKey('count', $lw->normalize([2]));
        $this->assertArrayHasKey('count', $lw->normalize([2, 3]));
        $this->assertEquals(LoripsumWords::DEFAULT_WORD_COUNT, $lw->normalize([])['count']);
        $this->assertEquals(5, $lw->normalize([5])['count']);
    }

    public function testNegativeCountException()
    {
        $lw = new LoripsumWords();
        $this->expectExceptionMessage('must be a positive integer');
        $lw->validate(['count' => -2]);
    }

    public function testFloatCountException()
    {
        $lw = new LoripsumWords();
        $this->expectExceptionMessage('must be a positive integer');
        $lw->validate(['count' => 2.3]);
    }

}