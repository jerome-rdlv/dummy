<?php


namespace Rdlv\WordPress\Dummy;


class LoripsumWords implements GeneratorInterface, UseFieldParserInterface
{
    use UseFieldParserTrait, OutputTrait;

    const TEXT_START = 8;

    /** @var Loripsum */
    private $loripsum;

    public function __construct(Loripsum $loripsum)
    {
        $this->loripsum = $loripsum;
    }

    public function get($options, $post_id = null)
    {
        list($min, $max) = $options;

        if (!$max) {
            return '';
        }

        $raw = $this->loripsum->get([1, 'plaintext']);
        $words = explode(' ', preg_replace('/[^a-z0-9]+/i', ' ', $raw));
        return ucfirst(strtolower(implode(' ', array_slice(
            $words,
            self::TEXT_START + rand(0, count($words) - $max),
            rand($min, $max)
        ))));
    }
}