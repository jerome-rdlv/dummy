<?php


namespace Rdlv\WordPress\Dummy;


use WP_CLI;

class CommandClear extends AbstractCommand implements CommandInterface
{
    private $post_type;

    /**
     * Clear the dummy content
     *
     * ## OPTIONS
     *
     * [--post-type=<post-type>]
     * : The type of post to generate
     * 
     */
    public function __invoke($args, $assoc_args)
    {
        $this->post_type = $assoc_args['post-type'];
        $this->init($args, $assoc_args);
    }

    protected function run()
    {
        global $wpdb;

        $query = "
            SELECT ID FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_dummy'
            WHERE pm.meta_value != 'false'
        ";
        if ($this->post_type) {
            $query .= " AND p.post_type = '" . $this->post_type . "'";
        }
        $ids = $wpdb->get_col($query);
        if ($ids) {
            $ids_list = implode(', ', $ids);

            // drop attachment files if any
            $media_query = sprintf("
                SELECT pm.meta_value
                FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
                WHERE p.post_type = 'attachment' AND p.ID IN (%s)
                    AND pm.meta_key = '_wp_attached_file'
            ", $ids_list);
            $files = $wpdb->get_col($media_query);

            $uploads_dir = wp_upload_dir()['basedir'];
            foreach ($files as $file) {
                $paths = glob(preg_replace('/(\.[^.]+)$/', '*\1', $uploads_dir . '/' . $file));
                foreach ($paths as $path) {
                    unlink($path);
                }
            }

            /** @noinspection PhpFormatFunctionParametersMismatchInspection */
            $delete_queries = [
                "DELETE FROM $wpdb->postmeta WHERE post_id IN (%s)",
                "DELETE FROM $wpdb->posts WHERE ID IN (%s)",
            ];
            foreach ($delete_queries as $delete_query) {
                $wpdb->query(sprintf($delete_query, $ids_list));
            }
        }
        WP_CLI::success(sprintf('deleted %s posts', count($ids)));
    }
}