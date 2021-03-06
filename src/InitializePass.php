<?php


namespace Rdlv\WordPress\Dummy;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class InitializePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $commands = $container->findTaggedServiceIds('app.command');
        $services = $container->findTaggedServiceIds('app.initialized');
        foreach ($commands as $command_id => $command_tag) {
            $command_definition = $container->getDefinition($command_id);
            foreach ($services as $service_id => $initialized_tag) {
                $command_definition->addMethodCall('register_service', [
                    $service_id,
                    new Reference($service_id)
                ]);
            }
        }
    }
}