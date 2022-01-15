<?php


namespace Rdlv\WordPress\Dummy;


use Rdlv\Wordpress\Dummy\Vendor\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Rdlv\Wordpress\Dummy\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;
use Rdlv\Wordpress\Dummy\Vendor\Symfony\Component\DependencyInjection\Reference;

class MetaCommandPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $commands = $container->findTaggedServiceIds('app.command');
        $meta_commands = $container->findTaggedServiceIds('app.meta_command');

        $command_ids = array_filter(array_keys($commands), function ($command_id) use ($container) {
            return !$container->getDefinition($command_id)->hasTag('app.meta_command');
        });

        $commands = array_map(function ($command_id) {
            return [$command_id, new Reference($command_id)];
        }, $command_ids);

        foreach ($meta_commands as $meta_command_id => $tag) {
            $meta_command_def = $container->getDefinition($meta_command_id);
            foreach ($commands as $command) {
                $meta_command_def->addMethodCall('add_command', $command);
            }
        }
    }
}
