<?php

namespace Rdlv\WordPress\Dummy;


use Exception;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use WP_CLI;
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
    use ErrorTrait;

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

            // configure and inject field parser
            $containerBuilder->addCompilerPass(new FieldParserPass());

            // inject CLI arguments
            $containerBuilder->addCompilerPass(new InitializePass());

            $containerBuilder->compile(true);

            // add commands
            foreach ($containerBuilder->findTaggedServiceIds('app.command') as $service_id => $tags) {
                // extend composite command documentation
                $this->add_hook($command_name, $service_id);

                $command = $containerBuilder->get($service_id);
                if ($command instanceof CommandInterface) {
                    WP_CLI::add_command(
                        $command_name . ' ' . $service_id,
                        $command,
                        $this->get_command_doc($service_id, $command)
                    );
                }
            }

        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @param string $service_id
     * @param CommandInterface $command
     * @return array
     * @throws ReflectionException
     */
    private function get_command_doc($service_id, $command)
    {
        $reflection = new ReflectionClass($command);
        $dp = new DocParser($reflection->getDocComment());

        $synopsis = $dp->get_synopsis();
        if (empty($synopsis)) {
            $synopsis = $this->get_doc_options($dp);
        }

        $longdesc = $dp->get_longdesc();
        if ($command instanceof ExtendDocInterface) {
            $longdesc = $command->extend_doc($longdesc);
        }

        return $this->get_extended_doc($service_id, [
            'synopsis'  => $synopsis,
            'shortdesc' => $dp->get_shortdesc(),
            'longdesc'  => $longdesc,
        ]);
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
//
//            /** @var Subcommand $subcommand */
//            $subcommand = $command->get_subcommands()[$subcommand_name];
//            $this->extend_command_doc($subcommand);
        });
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

    private function get_doc_options(DocParser $doc, $id = null)
    {
        $synopsis = $doc->get_synopsis();
        if (!$synopsis) {
            preg_match_all('/(.+?)[\r\n]+: /', $doc->get_longdesc(), $matches);
            $synopsis = implode(' ', $matches[1]);
        }
        $synopsis = SynopsisParser::parse($synopsis);
        if ($id !== null) {
            array_walk($synopsis, function (&$option) use ($id) {
                $name = $option['name'];
                $option['token'] = str_replace($name, $id . '-' . $name, $option['token']);
                $option['name'] = $id . '-' . $name;
            });
        }
        return array_combine(array_column($synopsis, 'name'), $synopsis);
    }

    /**
     * Extend command doc with generators and handlers options and descriptions.
     * @param string $service_id
     * @param [] $doc
     * @return array Extended doc
     */
    private function get_extended_doc($service_id, $doc)
    {
        $synopsis = $doc['synopsis'];
        $synopsis = array_combine(array_column($synopsis, 'name'), $synopsis);
        $longdesc = $doc['longdesc'];

        try {
            $service = $this->container->get($service_id);
            if ($service instanceof UseFieldParserInterface) {

                // extends command doc with each service own doc
                $field_parser = $service->get_field_parser();
                $services = array_merge($field_parser->get_handlers(), $field_parser->get_generators());
                foreach ($services as $service_id => $service_instance) {
                    $service_class = get_class($service_instance);
                    $service_doc = $this->get_class_doc($service_instance);

                    // extend command options
                    foreach ($this->get_doc_options($service_doc, $service_id) as $name => $option) {
                        // prevent option overwrite by service
                        if (!isset($synopsis[$name])) {
                            $synopsis[$name] = $option;
                        }
                    }

                    // extend command longdesc
                    $class_longdesc = trim(trim($service_doc->get_shortdesc()) . "\n\n" . trim($service_doc->get_longdesc()));
                    if ($class_longdesc) {
                        if ($service_instance instanceof ExtendDocInterface) {
                            $class_longdesc = $service_instance->extend_doc($class_longdesc);
                        }
//                        $class_path = explode('\\', $service_class);
//                        $title = strtoupper(preg_replace('/(.)([A-Z])/', '\1 \2', array_pop($class_path)));
                        $title = $service_id;
                        if ($service_instance instanceof GeneratorInterface) {
                            $title .= ' generator';
                        } elseif ($service_instance instanceof HandlerInterface) {
                            $title .= ' handler';
                        }
                        $longdesc .= sprintf(
                            "\n\n## %s\n\n%s",
                            strtoupper($title),
                            preg_replace(
                                [
                                    '/{id}/',
                                    '/^ *##+ +(.*)$/m',
                                    '/^ *\[--([^\]]+)\]$/m',
                                ],
                                [
                                    $service_id,
                                    WP_CLI::colorize('%9\1%n'),
                                    '[--' . $service_id . '-\1]',
                                ],
                                $class_longdesc
                            )
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
            exit(1);
        }

        $doc['synopsis'] = SynopsisParser::render($synopsis);
        $doc['longdesc'] = $longdesc;

        return $doc;
    }
}