<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Test;

use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\CommandGenerate;
use Rdlv\WordPress\Dummy\Field;
use Rdlv\WordPress\Dummy\FieldParser;
use Rdlv\WordPress\Dummy\RawValue;

class CommandGenerateTest extends TestCase
{
    public function testValidateNotInstalled()
    {
        $cmd = new CommandGenerate();
        $this->expectExceptionMessage('WordPress admin must be loaded');
        $cmd->validate([], []);
    }

    public function testFieldFormats()
    {
        $cmd = new CommandGenerate();
        $field_parser = new FieldParser();
        $field_parser->add_generator('html', new RawValue());
        $cmd->set_field_parser($field_parser);

        $args_array = [
            ['content=html:2,3,short'],
            ['content' => 'html:2,3,short'],
            ['content' => ['html' => '2,3,short']],
            ['content' => ['html' => [2, 3, 'short']]],
        ];
        foreach ($args_array as $args) {
            $fields = $cmd->get_fields($args);
            $this->assertCount(1, $fields);
            $this->assertArrayHasKey('content', $fields);
            /** @var Field $field */
            $field = $fields['content'];
            $this->assertInstanceOf(RawValue::class, $field->callback->get_generator());
            $this->assertEquals([2, 3, 'short'], $field->callback->get_args());
        }
    }
    
    public function testFieldDefaults()
    {
        $cmd = new CommandGenerate();
        $cmd->set_defaults(['content' => 'html:2,3,short']);
        $field_parser = new FieldParser();
        $field_parser->add_generator('html', new RawValue());
        $cmd->set_field_parser($field_parser);

        // no field passed, default should be applied
        $fields = $cmd->get_fields([]);
        $this->assertCount(1, $fields);
        $this->assertArrayHasKey('content', $fields);
        /** @var Field $field */
        $field = $fields['content'];
        $this->assertEquals('html', $field->callback->get_generator_id());
        $this->assertEquals([2, 3, 'short'], $field->callback->get_args());
        $this->assertEquals('2,3,short', $field->get_value());

        // pass empty field to cancel default
        $args_array = [
            ['content'],
            ['content='],
        ];
        foreach ($args_array as $args) {
            $fields = $cmd->get_fields($args);
            $this->assertEmpty($fields['content']->get_value());
        }
        
        // cancel defaults flag
        $this->assertEmpty($cmd->get_fields([], ['without-defaults' => true]));
    }
}