<?php


namespace Rdlv\WordPress\Dummy\Handler;

use Rdlv\WordPress\Dummy\HandlerInterface;

/**
 * Populate post taxonomy
 *
 * Example:
 *
 *      {id}:custom_field=raw:your_value
 */
class Taxonomy implements HandlerInterface
{
	public function generate($post_id, $field)
	{
		$term = get_term($field->get_value($post_id), $field->name);
		wp_set_post_terms($post_id, $term->term_id, $field->name);
		wp_update_term_count_now([$term->term_taxonomy_id], $field->name);
	}
}
