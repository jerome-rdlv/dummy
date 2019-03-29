<?php


namespace Rdlv\WordPress\Dummy;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class UseTypesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $type_services = $container->findTaggedServiceIds('app.type');
        
        $use_types_services = $container->findTaggedServiceIds('app.use_types');
        foreach ($use_types_services as $service_id => $service_tags) {
            $service_definition = $container->getDefinition($service_id);
            foreach ($type_services as $type_id => $type_tags) {
                $service_definition->addMethodCall('add_type', [
                    $type_id,
                    new Reference($type_id),
                ]);
            }
        }
    }
}