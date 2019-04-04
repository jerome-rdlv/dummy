<?php


namespace Rdlv\WordPress\Dummy;


class MetaHandler implements HandlerInterface, Initialized
{
    private $post_type;

    /**
     * @param $assoc_args
     * @return void
     */
    public function init($args, $assoc_args)
    {
        $this->post_type = $assoc_args['post-type'];
    }

    /**
     * @param integer $post_id
     * @param Field $field
     * @return void
     */
    public function generate($post_id, $field)
    {
        update_post_meta(
            $post_id,
            $field->name,
            $field->get_value()
        );
    }
}