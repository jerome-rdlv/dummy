<?php


namespace Rdlv\WordPress\Dummy\Generator;


use Rdlv\WordPress\Dummy\GeneratorInterface;

/**
 * Provide random value from an enum
 *
 * ## Arguments
 *
 *    A list of comma separated values
 *
 * ## Syntax
 *
 *      {id}:default,large,tall
 *
 * ## Example
 *
 *      {id}:default,large,tall
 */
class RandomEnum implements GeneratorInterface
{
	public function normalize($args)
	{
		return $args;
	}

	public function validate($args)
	{
	}

	public function get($args, $post_id = null)
	{
		return $args[array_rand($args)];
	}
}
