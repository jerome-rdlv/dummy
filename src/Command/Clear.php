<?php


namespace Rdlv\WordPress\Dummy\Command;


use Rdlv\WordPress\Dummy\AbstractSubCommand;
use Rdlv\WordPress\Dummy\DummyException;

/**
 * Clear the dummy content
 *
 * ## OPTIONS
 *
 * [--post-type=<post-type>]
 * : The type of post to generate
 *
 */
class Clear extends AbstractSubCommand
{
    public function validate($args, $assoc_args)
    {
        if (!empty($assoc_args['post-type'])) {
            $post_types = get_post_types();
            if (!in_array($assoc_args['post-type'], $post_types)) {
                throw new DummyException(sprintf(
                    "Post type '%s' unknown, must be any of %s",
                    $assoc_args['post-type'],
                    implode(', ', $post_types)
                ));
            }
        }
    }

    private function remove_companion()
    {
        if (defined('WPMU_PLUGIN_DIR')) {
            $local = __DIR__ . '/../../inc/dummy.php';
            $dest = WPMU_PLUGIN_DIR . '/dummy.php';
            if (file_exists($dest) && file_get_contents($local) === file_get_contents($dest)) {
                unlink($dest);
            }
        }
    }

    public function run($args, $assoc_args)
    {
        global $wpdb;

        $post_type = null;
        if (isset($assoc_args['post-type'])) {
            $post_type = $assoc_args['post-type'];
        }

        $query = "
            SELECT ID, post_type
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_dummy'
            WHERE pm.meta_value != 'false'
        ";
        if ($post_type) {
            $query .= " AND p.post_type = '" . esc_sql($post_type) . "'";
        }
        $rows = $wpdb->get_results($query);

        if ($rows) {
            $progress = sprintf('delete dummy %s', $post_type ? $post_type . ' posts' : 'posts');
            $total = count($rows);
            foreach ($rows as $index => $row) {
                $this->print_progress($progress, $index, $total);
                switch ($row->post_type) {
                    case 'attachment':
                        wp_delete_attachment($row->ID, true);
                        break;
                    default:
                        wp_delete_post($row->ID, true);
                }
            }
            $this->print_progress($progress, $total, $total);
        } else {
            $this->print_progress('no dummy post to delete');
        }

        if (empty($this->post_type)) {
            $this->remove_companion();
        }
    }
}