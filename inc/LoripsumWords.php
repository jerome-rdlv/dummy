<?php


namespace Rdlv\WordPress\Dummy;


class LoripsumWords implements TypeInterface
{
    use OutputTrait;
    
    const TEXT_START = 8;
    
    /** @var Loripsum */
    private $loripsum;
    
    public function __construct(Loripsum $loripsum)
    {
        $this->loripsum = $loripsum;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function get($post_id, $options)
    {
        list($min, $max) = explode(':', $options);

        if (!$max) {
            return '';
        }
        
        $raw = $this->loripsum->get($post_id, '1/plaintext');
        $words = explode(' ', preg_replace('/[^a-z0-9]+/i', ' ', $raw));
        return ucfirst(strtolower(implode(' ', array_slice(
            $words,
            self::TEXT_START + rand(0, count($words) - $max),
            rand($min, $max)
        ))));
    }
}