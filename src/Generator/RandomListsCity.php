<?php


namespace Rdlv\WordPress\Dummy\Generator;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\GeneratorInterface;

/**
 * Random city of the world
 * 
 * This use this online resource: https://www.randomlists.com/
 */
class RandomListsCity implements GeneratorInterface
{
    const API_URL = 'https://www.randomlists.com/data/world-cities-3.json';
    
    private $cities = null;
    
    public function normalize($args)
    {
        return $args;
    }

    public function validate($args)
    {
        if ($args) {
            throw new DummyException("no arguments expected.");
        }
    }

    /**
     * @throws DummyException
     */
    private function load_cities()
    {
        try {
            $client = new Client();
            $response = $client->request('GET', self::API_URL);
        } catch (GuzzleException $e) {
            throw new DummyException('Exception loading html from API: ' . $e->getMessage());
        }
        
        $raw = json_decode($response->getBody());
        if (!isset($raw->RandL->items)) {
            throw new DummyException("API response is not in expected format.");
        }
        
        $this->cities = array_map(function ($item) {
            return $item->name;
        }, $raw->RandL->items);
        
        if (!$this->cities) {
            throw new DummyException("API returned no cities.");
        }
    }

    public function get($args, $post_id = null)
    {
        if ($this->cities === null) {
            $this->load_cities();
        }
       
        return $this->cities[array_rand($this->cities)];
       
    }
}