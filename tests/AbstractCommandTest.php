<?php

/** @noinspection PhpUnhandledExceptionInspection */


namespace Rdlv\WordPress\Dummy\Tests;


use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\AbstractCommand;
use Rdlv\WordPress\Dummy\Initialized;

class AbstractCommandTest extends TestCase
{
    public function testGlobalAssocArgs()
    {
        /** @var AbstractCommand $cmd */
        $cmd = $this->getMockForAbstractClass(AbstractCommand::class);

        /** @var Initialized $image_service */
        $image_service = $this->getMockClass(Initialized::class);
        $cmd->register_service('image', $image_service);

        /** @var Initialized $html_service */
        $html_service = $this->getMockClass(Initialized::class);
        $cmd->register_service('html', $html_service);

        $assoc_args = [
            'count'         => 12,
            'post-type'     => 'dummy_post',
            'image-key'     => 'aunrustu',
            'image-random'  => true,
            'html-headings' => false,
        ];

        $this->assertEquals(
            [
                'image' => [
                    'key'    => 'aunrustu',
                    'random' => true,
                ],
                'html'  => [
                    'headings' => false,
                ],
            ],
            $cmd->get_services_assoc_args($assoc_args)
        );

        $this->assertEquals(
            [
                'count'     => 12,
                'post-type' => 'dummy_post',
            ],
            $cmd->get_global_assoc_args($assoc_args)
        );
    }

}