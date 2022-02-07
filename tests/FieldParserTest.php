<?php

/**
 * @noinspection PhpParamsInspection,PhpUnhandledExceptionInspection
 */

namespace Rdlv\WordPress\Dummy\Tests;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\FieldParser;
use Rdlv\WordPress\Dummy\Generator\Loripsum;
use Rdlv\WordPress\Dummy\Generator\RandomNumber;
use Rdlv\WordPress\Dummy\Generator\RawValue;
use Rdlv\WordPress\Dummy\GeneratorInterface;
use Rdlv\WordPress\Dummy\Handler\Acf;
use Rdlv\WordPress\Dummy\Handler\Meta;

class FieldParserTest extends TestCase
{
    public function testNullHandler()
    {
        $parser = new FieldParser();
        $this->assertNull($parser->parse_field('test')->handler);
        $this->assertNull($parser->parse_field('meta:test')->handler);
    }

    public function testMetaHandler()
    {
        $parser = new FieldParser();
        /** @noinspection PhpParamsInspection */
        $parser->add_handler('meta', new Meta());
        $parser->add_handler('acf', new Acf());
        $this->assertNull($parser->parse_field('test')->handler);
        $this->assertInstanceOf(Meta::class, $parser->parse_field('meta:test')->handler);
    }

    public function testName()
    {
        $parser = new FieldParser();
        $this->assertEquals('post_content', $parser->parse_field('post_content')->alias);
        $this->assertEquals('post_content', $parser->parse_field('post_content=')->alias);
        $this->assertEquals('post_content', $parser->parse_field('post_content=arguments')->alias);
    }

    public function testAlias()
    {
        $parser = new FieldParser();
        $parser->set_aliases([
            'content' => 'post_content',
            'thumb'   => 'meta:_thumbnail_id',
        ]);
        $parser->add_handler('meta', new Meta());
        $this->assertEquals('content', $parser->parse_field('content')->alias);
        $this->assertEquals('post_content', $parser->parse_field('content')->name);
        $this->assertEquals('thumb', $parser->parse_field('thumb')->alias);
        $this->assertEquals('_thumbnail_id', $parser->parse_field('thumb')->name);
        $this->assertEquals('meta:_thumbnail_id', $parser->parse_field('thumb')->key);
        $this->assertInstanceOf(Meta::class, $parser->parse_field('thumb')->handler);
    }

    public function testRawContent()
    {
        $parser = new FieldParser();
        $this->assertEquals('test string', $parser->parse_field('test=test string')->get_value());
        $this->assertEquals('html:test string', $parser->parse_field('test=html:test string')->get_value());
        /** @noinspection PhpParamsInspection */
        $parser->add_generator('html', new RawValue());
        $parser->add_generator('raw', new RawValue());
        $this->assertNotEquals('html:test string', $parser->parse_field('test=html:test string')->get_value());
        $this->assertEquals('html:test string', $parser->parse_field('test=raw:html:test string')->get_value());
        $this->assertEmpty($parser->parse_field('test')->get_value());
        $this->assertEmpty($parser->parse_field('test=')->get_value());
        $this->assertEquals('', $parser->parse_field('test=raw:')->get_value());
    }

    public function testGenerator()
    {
        $parser = new FieldParser();
        $this->assertInstanceOf(RawValue::class, $parser->parse_field('test=html')->callback->get_generator());
        $parser->add_generator('html', new RawValue());
        $this->assertInstanceOf(GeneratorInterface::class,
            $parser->parse_field('test=html')->callback->get_generator());
        $this->assertInstanceOf(GeneratorInterface::class,
            $parser->parse_field('test=html:')->callback->get_generator());
        $this->assertInstanceOf(GeneratorInterface::class,
            $parser->parse_field('test=html:8')->callback->get_generator());
        $this->assertInstanceOf(GeneratorInterface::class,
            $parser->parse_field('test=html:5,ul,h2,h3,medium')->callback->get_generator());
    }

    public function testParsedSimpleArgsArray()
    {
        $parser = new FieldParser();
        $parser->add_generator('html', new RawValue());
        $parser->add_generator('raw', new RawValue());
        $this->assertIsNotArray($parser->parse_field('meta:test=raw:html:lorem')->get_value());
        $this->assertEquals('html:lorem', $parser->parse_field('meta:test=raw:html:lorem')->get_value());
        $this->assertIsArray($parser->parse_field('test=html:2,h2,h3')->callback->get_raw_args());
    }

    public function testParsedStrangeArgsArray()
    {
        $parser = new FieldParser();
        $parser->add_generator('html', new RawValue());
        $this->assertIsArray($parser->parse_field('test=html:[2,h2,h3]')->callback->get_raw_args());
        $this->assertNotEquals(['2', 'h2', 'h3'], $parser->parse_field('test=html:[2,h2,h3]')->callback->get_raw_args());
    }

    public function testParsedYamlField()
    {
        $parser = new FieldParser();
        $parser->add_generator('html', new RawValue());

        $field = $parser->parse_field('test={html:[2,h2,h3]}');
        $this->assertEquals('html', $field->callback->get_generator_id());
        $this->assertIsArray($field->callback->get_raw_args());
        $this->assertEquals(['2', 'h2', 'h3'], $field->callback->get_raw_args());
    }

    public function testFieldWithChildGenerator()
    {
        $parser = new FieldParser();
        $parser->add_generator('number', new RandomNumber());

        $field = $parser->parse_field('test={number:{"min":2,max:{number:[5,20]}}}');
        $args = $field->callback->get_args();
        $this->assertEquals(2, $args['min']);
        $this->assertGreaterThanOrEqual(5, $args['max']);
        $this->assertLessThanOrEqual(20, $args['max']);

        $parser->add_generator('html', new Loripsum());
        $parser->add_generator('raw', new RawValue());
        $field = $parser->parse_field('test={html:{count:{"raw":"[1,7,93]"},options:[h2,h3]}}');
        $this->assertEquals(['count' => '[1,7,93]', 'options' => ['h2', 'h3']], $field->callback->get_args());
    }
}
