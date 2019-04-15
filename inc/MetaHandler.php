<?php


namespace Rdlv\WordPress\Dummy;


use Exception;

class MetaHandler implements HandlerInterface, Initialized
{
    private $post_type = null;

    /**
     * @param $assoc_args
     * @return void
     */
    public function init($args, $assoc_args)
    {
        if (!empty($assoc_args['post-type'])) {
            $this->post_type = $assoc_args['post-type'];
        }
    }

    /**
     * @param integer $post_id
     * @param Field $field
     * @return void
     * @throws Exception
     */
    public function generate($post_id, $field)
    {
        update_post_meta(
            $post_id,
            $field->name,
            $field->get_value($post_id)
        );
    }
}