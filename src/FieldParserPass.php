<?php


namespace Rdlv\WordPress\Dummy;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FieldParserPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $field_parser_service_id = FieldParser::class;
        $field_parser_service = $container->findDefinition($field_parser_service_id);

        // add handlers to field parser service
        $handlers = $container->findTaggedServiceIds('app.handler');
        foreach ($handlers as $handler_id => $tag) {
            $field_parser_service->addMethodCall('add_handler', [
                $handler_id,
                new Reference($handler_id)
            ]);
        }

        // add generators to field parser service
        $generators = $container->findTaggedServiceIds('app.generator');
        foreach ($generators as $generator_id => $tag) {
            $field_parser_service->addMethodCall('add_generator', [
                $generator_id,
                new Reference($generator_id)
            ]);
        }

        // inject field parser service
        $field_parser_reference = new Reference($field_parser_service_id);
        $use_field_parser_services = $container->findTaggedServiceIds('app.use_field_parser');
        foreach ($use_field_parser_services as $service_id => $tag) {
            $service_definition = $container->getDefinition($service_id);
            $service_definition->addMethodCall('set_field_parser', [
                $field_parser_reference
            ]);
        }
    }
}