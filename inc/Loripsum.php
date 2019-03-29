<?php


namespace Rdlv\WordPress\Dummy;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Retrieve random text or rich text from http://loripsum.net/
 *
 * todo drop that, it’s non sense
 * [--loripsum-html-defaults=<defaults>]
 * : Default parameters for html. They can be overridden with each call to html type.
 * ---
 * default: 6/ul/h2/h3
 * ---
 *
 * [--loripsum-text-defaults=<defaults>]
 * : Default parameters for text. They can be overridden with each call to html type.
 * ---
 * default: 6/ul/h2/h3
 * ---
 */
class Loripsum implements TypeInterface
{
    use OutputTrait;
    
    const API_HTML_URL = 'https://loripsum.net/api/%s';

//    public function get_defaults($type, $assoc_args)
//    {
//        return isset($assoc_args) ? $assoc_args['loripsum-defaults'] : null;
//    }

    /**
     * @param $value
     * @return mixed
     */
    public function get($post_id, $options)
    {
        $levels = array_filter(array_map(function ($option) {
            return preg_replace('/(h([1-6])|.*)/', '\2', $option);
        }, explode('/', $options)));
        $query = preg_replace('/\bh[1-6]\b/', 'headers', $options);

        try {
            $client = new Client();
            $response = $client->request('GET', sprintf(self::API_HTML_URL, $query));
            if ($response->getStatusCode() !== 200) {
                $this->error('Exception loading html from API: ' . $response->getReasonPhrase());
                exit(1);
            }
        } catch (GuzzleException $e) {
            $this->error('Exception loading html from API: ' . $e->getMessage());
            exit(1);
        }

        // replace out of range headings and return
        return preg_replace_callback('/<h([1-6])>(.*?)<\/h\1>/', function ($matches) use ($levels) {
            return in_array($matches[1], $levels) ? $matches[0] : sprintf(
                '<h%1$s>%2$s</h%1$s>',
                $levels[array_rand($levels)],
                $matches[2]
            );
        }, $response->getBody());
    }
}