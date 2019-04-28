<?php

/** @noinspection PhpParamsInspection,PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Tests;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Generator\Loripsum;
use Rdlv\WordPress\Dummy\Generator\RandomNumber;
use Rdlv\WordPress\Dummy\Generator\RawValue;
use Rdlv\WordPress\Dummy\GeneratorCall;

class LoripsumTest extends TestCase
{
    public function testNormalization()
    {
        $this->assertEquals(['count' => 5], (new Loripsum())->normalize([5]));
        $this->assertInstanceOf(GeneratorCall::class, (new Loripsum())->normalize([5, 6])['count']);
        // test that '5' is assigned to count and removed from options
        $this->assertEquals(['count' => 5], (new Loripsum())->normalize(['5']));
    }

    public function testNormalizationTooMuchNumbers()
    {
        $this->expectExceptionMessage('3 numbers given');
        (new Loripsum())->normalize([2, 3, 4]);
    }

    public function testNormalizationTooMuchLengths()
    {
        $this->expectExceptionMessage("length 'short' already set and new length 'long' found.");
        (new Loripsum())->normalize(['short', 2, 4, 'ul', 'long']);
    }

    public function testNotNumberParagraphCount()
    {
        $this->expectExceptionMessage("must be a positive integer");
        (new Loripsum())->validate(['count' => 'test']);
    }

    public function testNegativeParagraphCount()
    {
        $this->expectExceptionMessage("must be a positive integer");
        (new Loripsum())->validate(['count' => -1]);
    }

    public function testFloatParagraphCount()
    {
        $this->expectExceptionMessage("must be a positive integer");
        (new Loripsum())->validate(['count' => 1.2]);
    }

    public function testUnknownLengthCount()
    {
        $this->expectExceptionMessage("paragraph length must be any of");
        (new Loripsum())->validate(['length' => 'test']);
    }

    public function testInvalidOptions()
    {
        $this->expectExceptionMessageRegExp("/must be an array/");
        (new Loripsum())->validate(['options' => 'test']);
    }

    public function testUnknownOptions()
    {
        $this->expectExceptionMessageRegExp("/unknown option 'test'/");
        (new Loripsum())->validate(['options' => ['h2', 'test', 'ul', 'link']]);
    }

    public function testValidationWithDynamicValues()
    {
        (new Loripsum())->validate([
            'options' => new GeneratorCall(null, new RawValue(), ['ul', 'links']),
        ]);
        (new Loripsum())->validate([
            'count'   => new GeneratorCall(null, new RandomNumber(), '2,5'),
            'length'  => new GeneratorCall(null, new RawValue(), 'short'),
            'options' => [
                'h2',
                new GeneratorCall(null, new RawValue(), 'quotes'),
                'ul',
                'link',
            ],
        ]);
        $this->assertEmpty(null);
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

    public function testNumbersOverflow()
    {
        $this->expectExceptionMessage('only one or two accepted');
        (new Loripsum())->normalize(['8', '8', '5']);
    }
}