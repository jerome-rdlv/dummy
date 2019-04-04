<?php


namespace Rdlv\WordPress\Dummy;


/**
 * Provide random numbers
 */
class RandomNumber implements GeneratorInterface
{
    public function get($options, $context = [])
    {
        $min = 0;
        $max = 100;
        if ($options) {
            if (count($options) === 1 && is_numeric($options[0])) {
                $max = $options[0];
            } else {
                foreach ($options as $key => $val) {
                    switch ($key) {
                        case 0:
                        case 'min':
                            $min = $val;
                            break;
                        case 1:
                        case 'max':
                            $max = $val;
                            break;
                    }
                }
            }
        }
        return rand($min, $max);
    }
}