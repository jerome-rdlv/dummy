<?php


namespace Rdlv\WordPress\Dummy;


use Exception;
use WP_CLI;

/**
 * Clear the dummy content
 *
 * ## OPTIONS
 *
 * [--post-type=<post-type>]
 * : The type of post to generate
 *
 */
class CommandClear extends AbstractCommand implements CommandInterface
{
    private $post_type = null;

    protected function validate($args, $assoc_args)
    {
        if (!empty($assoc_args['post-type'])) {
            $post_types = get_post_types();
            if (!in_array($assoc_args['post-type'], $post_types)) {
                throw new Exception(sprintf(
                    'Post type %s unknown, must be any of %s',
                    $this->post_type,
                    implode(', ', $post_types)
                ));
            } else {
                $this->post_type = $assoc_args['post-type'];
            }
        }
    }

    protected function run($args, $assoc_args)
    {
        global $wpdb;

        $query = "
            SELECT ID, post_type
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_dummy'
            WHERE pm.meta_value != 'false'
        ";
        if ($this->post_type) {
            $query .= " AND p.post_type = '" . esc_sql($this->post_type) . "'";
        }
        $rows = $wpdb->get_results($query);
        if ($rows) {
            foreach ($rows as $row) {
                switch ($row->post_type) {
                    case 'attachment':
                        wp_delete_attachment($row->ID, true);
                        break;
                    default:
                        wp_delete_post($row->ID, true);
                }
            }
        }
        WP_CLI::success(sprintf('deleted %s posts', count($rows)));
    }
}