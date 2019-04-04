<?php


namespace Rdlv\WordPress\Dummy;


/**
 * Provide random dates
 */
class RandomDate implements GeneratorInterface
{
    public function get($options, $context = [])
    {
        if ($options) {
            for ($i = 0; $i < 2; ++$i) {
                $options[$i] = strtotime(isset($options[$i]) ? $options[$i] : 'now');
            }
            return date('Y-m-d H:i:s', rand($options[0], $options[1]));
        } else {
            return date('Y-m-d H:i:s');
        }
    }
}