<?php


namespace Rdlv\WordPress\Dummy;


/**
 * Provide random dates
 * 
 * [--date-defaults=<defaults>]
 * : Default parameters for date random generation
 * ---
 * default: "3 months ago:now"
 * ---
 */
class RandomDate implements TypeInterface
{
//    public function get_defaults($assoc_args)
//    {
//        return isset($assoc_args) ? $assoc_args['date-defaults'] : null;
//    }

    /**
     * @param $value
     * @return mixed
     */
    public function get($post_id, $options)
    {
        if ($options) {
            $opts = explode(':', $options);
            for ($i = 0; $i < 2; ++$i) {
                $opts[$i] = strtotime(isset($opts[$i]) ? $opts[$i] : 'now');
            }
            return date('Y-m-d H:i:s', rand($opts[0], $opts[1]));
        } else {
            return date('Y-m-d H:i:s');
        }
    }
}