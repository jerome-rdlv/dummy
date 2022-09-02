<?php

namespace Rdlv\WordPress\Dummy\Generator;

use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\GeneratorInterface;

/**
 * Provide random terms
 *
 * ## Arguments
 *
 *        - taxonomy: The name of the taxonomy from which retrieve the terms
 *        - field: The field to return on the term object (default: term_id)
 *
 * ## Short syntax
 *
 *        {id}:taxo,<field>
 *
 * ## Example
 *
 *        {id}:category,name
 */
class Term implements GeneratorInterface
{
	const TAXO = 'taxo';
	const FIELD = 'field';

	const AVAILABLE_FIELDS = [
		'name',
		'slug',
		'ID',
		'term_id',
	];

	public function normalize($args)
	{
		if (count($args) < 1) {
			throw new DummyException("expect the taxonomy name as first argument.");
		}
		return [
			self::TAXO => $args[0],
			self::FIELD => $args[1] ?? 'term_id',
		];
	}

	public function validate($args)
	{
		if (!taxonomy_exists($args[self::TAXO])) {
			throw new DummyException(sprintf("'%s' taxonomy doesn’t exist.", $args[self::TAXO]));
		}
		if (!in_array($args[self::FIELD], self::AVAILABLE_FIELDS)) {
			throw new DummyException(sprintf("'%s' field isn’t available.", $args[self::FIELD]));
		}
	}

	public function get($args, $post_id = null)
	{
		$terms = get_terms(
			[
				'taxonomy' => $args[self::TAXO],
				'hide_empty' => false,
			]
		);
		return $terms[array_rand($terms)]->{$args[self::FIELD]};
	}
}
