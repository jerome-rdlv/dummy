<?php


namespace Rdlv\WordPress\Dummy;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Retrieve random text or rich text from http://loripsum.net/
 */
class Loripsum implements GeneratorInterface
{
    use OutputTrait;
    
    const API_HTML_URL = 'https://loripsum.net/api/%s';

    public function get($options, $post_id = null)
    {
        $query = implode('/', array_unique(array_map(function ($option) {
            return preg_match('/^h[1-6]$/', $option) ? 'headers' : $option;
        }, $options)));
        
        $levels = array_filter(array_map(function ($option) {
            return preg_replace('/(h([1-6])|.*)/', '\2', $option);
        }, $options));

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