<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Tests;


use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Command\Tasks;
use Rdlv\WordPress\Dummy\CommandInterface;
use Rdlv\WordPress\Dummy\SubCommandInterface;
use Rdlv\WordPress\Dummy\Task;
use Symfony\Component\Yaml\Dumper;

class CommandTasksTest extends TestCase
{
    const TASKS_FILE = '/tmp/dummy_test_tasks.yml';

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists(self::TASKS_FILE)) {
            unlink(self::TASKS_FILE);
        }
    }

    public function testFileArgumentEmpty()
    {
        $cmd = new Tasks();
        $this->expectExceptionMessage("'file' argument can not be empty");
        $cmd->load_tasks([], []);
    }

    public function testFileDoNotExists()
    {
        $cmd = new Tasks();
        $this->expectExceptionMessage("does not exist.");
        $cmd->load_tasks([], ['file' => self::TASKS_FILE]);
    }

    public function testFileEmpty()
    {
        $cmd = new Tasks();
        $this->createTasksFile(self::TASKS_FILE, []);
        $this->expectExceptionMessage("no tasks found in tasks file");
        $cmd->load_tasks([], ['file' => self::TASKS_FILE]);
    }

    public function testTaskHasNoCommand()
    {
        $cmd = new Tasks();
        $this->createTasksFile(self::TASKS_FILE, [
            'references' => [],
        ]);
        $this->expectExceptionMessage("task 'references' has no command");
        $cmd->load_tasks([], ['file' => self::TASKS_FILE]);
    }

    public function testTaskHasUnknownCommand()
    {
        $cmd = new Tasks();
        $this->createTasksFile(self::TASKS_FILE, [
            'references' => [
                'command' => 'clean',
            ],
        ]);
        $this->expectExceptionMessage("refers to inexistent command 'clean'");
        $cmd->load_tasks([], ['file' => self::TASKS_FILE]);
    }

    public function testNonExistentTask()
    {
        $cmd = new Tasks();
        $cmd->add_command('clear', $this->createMock(CommandInterface::class));
        $this->createTasksFile(self::TASKS_FILE, [
            'refs' => ['command' => 'clear'],
        ]);
        $this->expectExceptionMessage("following tasks are not found: rufs");
        $cmd->load_tasks(['rufs'], ['file' => self::TASKS_FILE]);
    }

    public function testMock()
    {
        $stub = $this->createMock(CommandInterface::class);
        if ($stub instanceof CommandInterface) {
            $this->assertNull($stub([], []));
            $stub->method('__invoke')->willReturn(2);
            $this->assertEquals(2, $stub([], []));
        }
    }

    public function testProphecy()
    {
        // @see https://github.com/phpspec/prophecy#how-to-use-it
        $prophecy = $this->prophesize(CommandInterface::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $prophecy->__invoke(['test'], [])->willReturn(2);
//        $this->assertEquals(2, $prophecy->reveal()([], []));
        /** @var CommandInterface $dummy */
        $dummy = $prophecy->reveal();
        $this->assertEquals(2, $dummy(['test'], []));
    }

    public function testTasksOrder()
    {
        $cmd = new Tasks();
        $cmd->add_command('clear', $this->createMock(CommandInterface::class));
        $this->createTasksFile(self::TASKS_FILE, [
            'test1' => ['command' => 'clear'],
            'test2' => ['command' => 'clear'],
            'test3' => ['command' => 'clear'],
        ]);

        // tasks file order
        $this->assertEquals(
            ['test1', 'test2', 'test3'],
            array_keys($cmd->load_tasks([], ['file' => self::TASKS_FILE]))
        );

        // args order
        $this->assertEquals(
            ['test2', 'test3', 'test1'],
            array_keys($cmd->load_tasks(['test2', 'test3', 'test1'], ['file' => self::TASKS_FILE]))
        );
    }

    public function testTaskLoading()
    {
        $cmd = new Tasks();
        $cmd->add_command('clear', $this->createMock(CommandInterface::class));
        $cmd->add_command('generate', $this->createMock(CommandInterface::class));
        $this->createTasksFile();

        $tasks = $cmd->load_tasks([], ['file' => self::TASKS_FILE]);
        $this->assertCount(3, $tasks);
        $this->assertArrayHasKey('clear', $tasks);
        $this->assertArrayHasKey('references', $tasks);
        $this->assertArrayHasKey('agencies', $tasks);

        /** @var Task[] $tasks */
        $tasks = $cmd->load_tasks(['clear', 'agencies'], ['file' => self::TASKS_FILE]);

        $this->assertCount(2, $tasks);
        $this->assertArrayHasKey('clear', $tasks);
        $this->assertArrayHasKey('agencies', $tasks);
        $this->assertEquals('dummy_agency', $tasks['agencies']->get_global('post-type'));
        $this->assertEquals('6', $tasks['agencies']->get_global('count'));
    }

    public function testAllInvocation()
    {
        $cmd = new Tasks();
        $cmd->add_command('clear', $this->createMock(SubCommandInterface::class));
        $cmd->add_command('generate', $this->createMock(SubCommandInterface::class));
        $this->createTasksFile();
        $this->expectOutputString("task clear:\ntask references:\ntask agencies:\n");
        $this->assertEquals(0, $cmd([], ['file' => self::TASKS_FILE]));
    }

    public function testSpecificInvocation()
    {
        $cmd = new Tasks();
        $cmd->add_command('clear', $this->createMock(SubCommandInterface::class));
        $cmd->add_command('generate', $this->createMock(SubCommandInterface::class));
        $this->createTasksFile();
        $this->expectOutputString("task references:\n");
        $this->assertEquals(0, $cmd(['references'], ['file' => self::TASKS_FILE]));
    }

    private function createTasksFile($path = self::TASKS_FILE, $data = null)
    {
        $dumper = new Dumper();
        if ($data === null) {
            $data = [
                'clear'      => [
                    'command' => 'clear',
                ],
                'references' => [
                    'command'   => 'generate',
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
                    'command'   => 'generate',
                    'post-type' => 'dummy_agency',
                    'count'     => 6,
                    'fields'    => [
                        'content=html',
                        'thumb=image:grayscale',
                    ],
                ],
            ];
        }
        file_put_contents($path, $dumper->dump($data, 4));
    }
}