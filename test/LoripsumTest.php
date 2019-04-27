<?php

/** @noinspection PhpParamsInspection,PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Test;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Loripsum;

class LoripsumTest extends TestCase
{
    public function testUnknownArgument()
    {
        $this->expectExceptionMessageRegExp('/unknown argument "test"/');
        (new Loripsum())->validate([2, 'test']);
    }

    public function testNegativeParagraphCount()
    {
        $this->expectExceptionMessage('must be a positive integer');
        (new Loripsum())->validate([-1, 'ul']);
    }

    public function testFloatParagraphCount()
    {
        $this->expectExceptionMessage('must be a positive integer');
        (new Loripsum())->validate([1.2, 'h2']);
    }

    private function getNumbers($items)
    {
        $numbers = [];
        foreach ($items as $item) {
            if (is_numeric($item)) {
                $numbers[] = $item;
            }
        }
        return $numbers;
    }

    public function testFixedParagraphCount()
    {
        $this->assertEquals([8], $this->getNumbers((new Loripsum())->normalize(['8'])));
        $this->assertEquals([9], $this->getNumbers((new Loripsum())->normalize(['9', 'h2', 'h3'])));
        $this->assertEquals([10], $this->getNumbers((new Loripsum())->normalize(['ul', '10', 'h3'])));
        $this->assertEquals([11], $this->getNumbers((new Loripsum())->normalize(['ul', 'links', '11'])));
    }

    public function testRandomParagraphCount()
    {
        $this->assertEquals([8], $this->getNumbers((new Loripsum())->normalize(['8', '8'])));
        $this->assertEquals([9], $this->getNumbers((new Loripsum())->normalize(['9', '9', 'h2', 'h3'])));
        $this->assertEquals([10], $this->getNumbers((new Loripsum())->normalize(['h2', '10', 'h3', '10'])));
    }

    public function testNumbersOverflow()
    {
        $this->expectExceptionMessage('only one or two accepted');
        (new Loripsum())->normalize(['8', '8', '5']);
    }

    public function testDoubleLengthArgument()
    {
        $this->expectExceptionMessage('length is already set');
        (new Loripsum())->validate([2, 'medium', 'h2', 'short']);
    }
}