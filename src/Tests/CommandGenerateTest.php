<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */


use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\CommandGenerate;
use Rdlv\WordPress\Dummy\Field;
use Rdlv\WordPress\Dummy\FieldParser;
use Rdlv\WordPress\Dummy\RawValue;
use Symfony\Component\Yaml\Dumper;

class CommandGenerateTest extends TestCase
{
    const TASKS_FILE = '/tmp/dummy_test_tasks.yml';

    public function testValidateNotInstalled()
    {
        $cmd = new CommandGenerate();
        $this->expectExceptionMessage('WordPress admin must be loaded');
        $cmd->validate([], []);
    }
    
    public function testInlineTask()
    {
        $cmd = new CommandGenerate();
        $tasks = $cmd->load_tasks(['content=html', 'thumb=image'], ['no-tasks' => true]);
        $this->assertCount(1, $tasks);
        $this->assertEquals(['content=html', 'thumb=image'], $tasks[0][0]);
    }

    public function testNoTasksFileAndInlineEmpty()
    {
        $cmd = new CommandGenerate();
        $tasks = $cmd->load_tasks([], ['no-tasks' => true]);
        $this->assertCount(1, $tasks);
        $tasks = $cmd->load_tasks([], []);
        $this->assertCount(1, $tasks);
    }

    private function createTasksFile($path = self::TASKS_FILE)
    {
        $dumper = new Dumper();
        file_put_contents($path, $dumper->dump([
            'references' => [
                'post-type' => 'dummy_ref',
                'count'     => 22,
                'defaults'  => false,
                'fields'    => [
                    'title'        => 'text:8,12',
                    'content'      => [
                        'html' => [2, 3, 'short'],
                    ],
                    'acf:contents' => null,
                ],
            ],
            'agencies'   => [
                'post-type' => 'dummy_agency',
                'count'     => 6,
                'fields'    => [
                    'content=html',
                    'thumb=image:grayscale',
                ],
            ],
        ], 4));
    }

    public function testExistentTasksFile()
    {
        $cmd = new CommandGenerate();
        $this->createTasksFile();

        // if args given, they are considered to be tasks names
        // so here, no tasks corresponds and we end up with a single CLI task
        $tasks = $cmd->load_tasks(['content=html'], ['tasks' => self::TASKS_FILE]);
        $this->assertEquals(['content=html'], $tasks[0][0]);

        // no args given, all tasks found in tasks file are loaded
        $tasks = $cmd->load_tasks([], ['tasks' => self::TASKS_FILE]);
        $this->assertCount(2, $tasks);

        // one task name given, one task loaded
        $tasks = $cmd->load_tasks(['references'], ['tasks' => self::TASKS_FILE]);
        $this->assertCount(1, $tasks);
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

        // pass empty field to cancel default
        $args_array = [
            ['content'],
            ['content='],
        ];
        foreach ($args_array as $args) {
            $fields = $cmd->get_fields($args);
            $this->assertEmpty($fields);
        }
        
        // cancel defaults flag
        $this->assertEmpty($cmd->get_fields([], ['without-defaults' => true]));
    }
}