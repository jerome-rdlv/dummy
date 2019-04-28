<?php


namespace Rdlv\WordPress\Dummy\Generator;


use Exception;
use Rdlv\WordPress\Dummy\Command\Generate;
use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\GeneratorInterface;

class PostFieldValue implements GeneratorInterface
{
    public function normalize($args)
    {
        if (count($args) !== 1) {
            throw new DummyException("only one argument expected.");
        }

        return [
            'name' => $args[0],
        ];
    }

    /**
     * @param array $args Array of normalized options, may contain subarray and GeneratorCall objects
     * @return void
     * @throws Exception That method should throw exception on validation error
     */
    public function validate($args)
    {
        if (array_key_exists('name', $args)) {
            // do not validate further if dynamic value
            if (!$args['name'] instanceof GeneratorCall) {
                if (!in_array($args['name'], Generate::AUTHORIZED_FIELDS)) {
                    throw new DummyException(sprintf(
                        "field '%s' is not authorized, must be any of %s",
                        $args['name'],
                        implode(', ', Generate::AUTHORIZED_FIELDS)
                    ));
                }
            }
        } else {
            throw new DummyException("argument 'name' is required.");
        }
    }

    /**
     * @param array $args Array of arguments
     * @param integer $post_id
     * @return mixed
     * @throws Exception
     */
    public function get($args, $post_id = null)
    {
        if ($post_id === null) {
            return '';
        }

        global $wpdb;
        return $wpdb->get_var(sprintf(
            "SELECT %s FROM $wpdb->posts WHERE ID = %d",
            $args['name'],
            $post_id
        ));
    }
}