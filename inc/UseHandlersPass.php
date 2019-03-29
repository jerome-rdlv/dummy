<?php


namespace Rdlv\WordPress\Dummy;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class UseHandlersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $handler_services = $container->findTaggedServiceIds('app.handler');
        
        $use_handlers_services = $container->findTaggedServiceIds('app.use_handlers');
        foreach ($use_handlers_services as $service_id => $service_tags) {
            $service_definition = $container->getDefinition($service_id);
            foreach ($handler_services as $handler_id => $handler_tags) {
                $service_definition->addMethodCall('add_handler', [
                    $handler_id,
                    new Reference($handler_id),
                ]);
            }
        }
    }
}