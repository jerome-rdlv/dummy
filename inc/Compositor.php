<?php

namespace Rdlv\WordPress\Dummy;


use Exception;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;
use WP_CLI;
use WP_CLI\Dispatcher\Subcommand;
use WP_CLI\DocParser;
use WP_CLI\SynopsisParser;

/**
 * Generate rich and complex dummy content in WordPress for testing and development purpose.
 *
 * ## EXAMPLES
 *
 *      # Generate 10 posts with dummy content and post thumbnail
 *      $ wp dummy generate
 *
 *      # Generate 5 posts with post thumbnail and content including 8 paragraphs, lists, and headings
 *      $ wp dummy generate content=html:8/ul/h2/h3
 *
 *      # Clear the created posts
 *      $ wp dummy clear
 *
 */
class Compositor
{
    use OutputTrait;
    
    public static function instance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /** @var ContainerBuilder */
    private $container;
    private $parent_initialized = false;

    public function init($command_name, $config_file)
    {
        try {
            $containerBuilder = new ContainerBuilder();
            $this->container = $containerBuilder;
            
            $loader = new YamlFileLoader($containerBuilder, new FileLocator(dirname($config_file)));
            $loader->load(basename($config_file));

            // inject CLI arguments
            $containerBuilder->addCompilerPass(new InitializePass());

            // inject handlers
            $containerBuilder->addCompilerPass(new UseHandlersPass());
            
            // inject types
            $containerBuilder->addCompilerPass(new UseTypesPass());
            
            $containerBuilder->compile(true);

            // add commands
            foreach ($containerBuilder->findTaggedServiceIds('app.command') as $service_id => $tags) {
                // extend documentation
                $this->add_hook($command_name, $service_id);

                $command = $containerBuilder->get($service_id);
                if ($command instanceof CommandInterface) {
                    WP_CLI::add_command($command_name . ' ' . $service_id, $command);
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    public function add_hook($command_name, $subcommand_name)
    {
        // documentation extension hook
        $doc_hook = sprintf('after_add_command:%s %s', $command_name, $subcommand_name);
        WP_CLI::add_hook($doc_hook, function () use ($command_name, $subcommand_name) {
            /** @var WP_CLI\Dispatcher\CompositeCommand $command */
            $command = WP_CLI::get_root_command()->get_subcommands()[$command_name];

            if (!$this->parent_initialized) {
                $reflection = new ReflectionClass($this);
                $docparser = new DocParser($reflection->getDocComment());
                $command->set_shortdesc($docparser->get_shortdesc());
                $command->set_longdesc($docparser->get_longdesc());
            }

            /** @var Subcommand $subcommand */
            $subcommand = $command->get_subcommands()[$subcommand_name];
            $this->extend_command_doc($subcommand);
        });
    }

    private function set_option_name_as_key($options)
    {
        return array_combine(array_map(function ($item) {
            return $item['name'];
        }, $options), $options);
    }

    /**
     * @param $class
     * @return DocParser
     */
    private function get_class_doc($class)
    {
        try {
            $reflection = new ReflectionClass($class);
            $doc = new DocParser($reflection->getDocComment());
            return $doc;
        } catch (ReflectionException $e) {
            $this->error($e->getMessage());
            exit(1);
        }
    }

    private function get_options_from_doc(DocParser $doc)
    {
        $synopsis = $doc->get_synopsis();
        if (!$synopsis) {
            preg_match_all('/(.+?)[\r\n]+:/', $doc->get_longdesc(), $matches);
            $synopsis = implode(' ', $matches[1]);
        }
        return $this->set_option_name_as_key(array_values(
            SynopsisParser::parse($synopsis)
        ));
    }

    private function extend_command_options(&$options, DocParser $doc,
        /** @noinspection PhpUnusedParameterInspection */
        $class)
    {
        foreach ($this->get_options_from_doc($doc) as $name => $option) {
            if (!isset($options[$name])) {
                $options[$name] = $option;
            }
//            else {
//                WP_CLI::warning(sprintf(
//                    'Service %s has invalid option %s (it is used already).',
//                    $class,
//                    $name
//                ));
//            }
        }
    }

    private function extend_command_longdesc(&$longdesc, DocParser $doc, $class)
    {
        $class_longdesc = trim($doc->get_longdesc());
        if ($class_longdesc) {
            $class_path = explode('\\', $class);
            $title = strtoupper(preg_replace('/(.)([A-Z])/', '\1 \2', array_pop($class_path)));
            $longdesc .= sprintf(
                "\n\n## %s\n\n%s",
                $title,
                $class_longdesc
            );
        }
    }

    private function extend_command_doc(Subcommand $subcommand)
    {
        $options = $this->set_option_name_as_key(SynopsisParser::parse($subcommand->get_synopsis()));
        $longdesc = $subcommand->longdesc;

        try {
            $service = $this->container->get($subcommand->get_name());
            if ($service instanceof UseTypesInterface && is_array($service->get_types())) {
                foreach ($service->get_types() as $type_id => $type_instance) {
                    $type_class = get_class($type_instance);
                    $type_doc = $this->get_class_doc($type_instance);
                    $this->extend_command_options($options, $type_doc, $type_class);
                    $this->extend_command_longdesc($longdesc, $type_doc, $type_class);
                }
            }
            if ($service instanceof UseHandlersInterface && is_array($service->get_handlers())) {
                foreach ($service->get_handlers() as $handler_id => $handler_instance) {
                    $handler_class = get_class($handler_instance);
                    $handler_doc = $this->get_class_doc($handler_instance);
                    $this->extend_command_options($options, $handler_doc, $handler_class);
                    $this->extend_command_longdesc($longdesc, $handler_doc, $handler_class);
                }
            }
        } catch (Exception $e) {
        }

        $subcommand->set_synopsis(SynopsisParser::render($options));
        $subcommand->set_longdesc($longdesc);
    }
}