<?php


namespace Rdlv\WordPress\Dummy\Generator;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\GeneratorCall;
use Rdlv\WordPress\Dummy\GeneratorInterface;
use WP_CLI;
use /** @noinspection PhpComposerExtensionStubsInspection */
    ZipArchive;

/**
 * Random city of the world
 *
 * This use database found here: https://simplemaps.com/data/world-cities
 *
 * ## Arguments
 *
 *      - country_code: ISO country code on two digits
 *      - format: A string with placeholders for city name, lat, lng, country, country_code and region
 *
 * ## Short syntax
 *
 *      {id}:<country_code>,<format>
 *
 * ## Example
 *
 *      {id}:FR,{name} - {region}
 */
class SimpleMapsCity implements GeneratorInterface
{
    const DATABASE_URL = 'https://simplemaps.com/static/data/world-cities/basic/simplemaps_worldcities_basicv1.4.zip';

    const PAGE_SIZE = 40;

    const DEFAULT_FORMAT = '{name}';

    private $cities = null;
    private $countries = null;
    private $total = null;

    private $sets = [];

    public function normalize($args)
    {
        $normalize = [];

        if (count($args) > 2) {
            throw new DummyException("at most two arguments expected.");
        }

        if ($args) {
            foreach ($args as $arg) {
                if (preg_match('/[A-Z]{2}/', $arg)) {
                    $normalize['country_code'] = $arg;
                } else {
                    $normalize['format'] = $arg;
                }
            }
        }

        return $normalize;
    }

    public function validate($args)
    {
        if (array_key_exists('country_code', $args) && !$args['country_code'] instanceof GeneratorCall) {
            $country_code = $args['country_code'];
            if (!array_key_exists($country_code, $this->get_countries())) {
                throw new DummyException(sprintf(
                    "country '%s' does not exist in database.",
                    $country_code
                ));
            }
        }
    }

    /**
     * @return string[]
     * @throws Exception
     */
    private function get_countries()
    {
        if ($this->countries === null) {
            $this->load_database();
        }
        return $this->countries;
    }

    /**
     * @throws Exception
     */
    private function load_database()
    {
        $database_file = $this->get_database_file();

        if ($this->countries === null) {

            if (!file_exists($database_file)) {

                // create dir
                $dir = dirname($database_file);
                if (!file_exists($dir)) {
                    wp_mkdir_p($dir);
                }

                // download database
                try {
                    $client = new Client();
                    $client->request('GET', self::DATABASE_URL, [
                        'sink' => $database_file . '.zip',
                    ]);
                } catch (GuzzleException $e) {
                    throw new DummyException('Exception loading html from API: ' . $e->getMessage());
                }

                /** @noinspection PhpComposerExtensionStubsInspection */
                $zip = new ZipArchive();
                if ($zip->open($database_file . '.zip')) {
                    $zip->extractTo($dir);
                    $zip->close();
                    unlink($database_file . '.zip');
                    unlink($dir . '/worldcities.xlsx');
                    rename($dir . '/worldcities.csv', $database_file);
                } else {
                    throw new DummyException(sprintf(
                        'Unable to open downloaded database in %s',
                        $database_file . '.zip'
                    ));
                }
            }

            // load countries
            if (($handle = fopen($database_file, 'r')) !== false) {
                $this->countries = [];
                $this->total = 0;

                // move after headers
                fgets($handle);

                $previous_offset = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    // get fifth field for country
                    ++$this->total;
                    if (!isset($row[5])) {
                        // invalid line
                        continue;
                    }
                    $country_code = $row[5];
                    if (!array_key_exists($country_code, $this->countries)) {
                        $this->countries[$country_code] = [
                            'count'  => 0,
                            'offset' => $previous_offset !== false ? $previous_offset : 0,
                        ];
                    }
                    ++$this->countries[$country_code]['count'];
                    $previous_offset = ftell($handle);
                }
                if (!feof($handle)) {
                    fclose($handle);
                    throw new DummyException("error while reading cities database.");
                }
                fclose($handle);
                ksort($this->countries);
            }
        }
    }

    private function get_cache_dir()
    {
        if (class_exists('\WP_CLI')) {
            return WP_CLI::get_cache()->get_root();
        } else {
            return '/tmp/';
        }
    }

    /**
     * Load more cities in set
     * @param object $set
     * @param string|null $country_code
     * @throws Exception
     */
    private function load_more(&$set, $country_code = null)
    {
        $this->load_database();

        // initialization
        $database_file = $this->get_database_file();
        $min = 0;
        $max = $this->total;
        $offset = 0;
        if ($country_code) {
            if (!array_key_exists($country_code, $this->countries)) {
                $country_code = null;
            } else {
                $max = $this->countries[$country_code]['count'] - 1;
                $offset = $this->countries[$country_code]['offset'];
            }
        }

        // generate random indexes
        $indexes = [];
        $i = 0;
        $page_size = min(self::PAGE_SIZE, $max - count($set->indexes));
        while ($i++ < $page_size) {
            do {
                $index = mt_rand($min, $max);
            } while (in_array($index, $indexes) || in_array($index, $set->indexes));
            $indexes[] = $index;
        }

        // get corresponding lines in database
        $index = 0;
        $lines = [];
        if (($handle = fopen($database_file, 'r')) !== false) {
            if ($offset === 0) {
                // move after headers
                fgets($handle);
            } else {
                fseek($handle, $offset);
            }
            while (($row = fgetcsv($handle)) !== false) {
                if (!isset($row[5])) {
                    // invalid line
                    continue;
                }
                if ($country_code && $country_code != $row[5]) {
                    // not in required country
                    continue;
                }
                if (in_array($index, $indexes)) {
                    // picked line
                    $lines[$index] = [
                        'name'         => $row[0],
                        'lat'          => $row[2],
                        'lng'          => $row[3],
                        'country'      => $row[4],
                        'country_code' => $row[5],
                        'region'       => $row[7],
                    ];
                }
                ++$index;
            }

            // add to set
            foreach ($indexes as $index) {
                if (array_key_exists($index, $lines)) {
                    $set->cities[] = $lines[$index];
                    $set->indexes[] = $index;
                }
            }
        }
    }

    /**
     * @param array $args Array of arguments
     * @param integer $post_id
     * @return mixed
     * @throws Exception
     */
    public function get($args, $post_id = null)
    {
        $set_id = md5(json_encode($args));
        if (!array_key_exists($set_id, $this->sets)) {
            $this->sets[$set_id] = (object)[
                'cities'  => [],
                'index'   => 0,
                'indexes' => [],
            ];
        }

        $set = &$this->sets[$set_id];
        $total = count($set->cities);
        if ($set->index >= $total) {
            $this->load_more($set, empty($args['country_code']) ? null : $args['country_code']);
        }

        if (!$set->cities) {
            throw new DummyException("unable to load cities.");
        }

        // return next city in set formatted
        return $this->format(
            $set->cities[$set->index++ % count($set->cities)],
            array_key_exists('format', $args) ? $args['format'] : self::DEFAULT_FORMAT
        );
    }

    /**
     * @return string
     */
    private function get_database_file(): string
    {
        return $this->get_cache_dir() . 'dummy_simple_maps_city/db.csv';
    }

    private function format($city, $format)
    {
        return preg_replace_callback('/{([^{}]*?)}/', function ($matches) use ($city) {
            if (array_key_exists($matches[1], $city)) {
                return $city[$matches[1]];
            } else {
                return $matches[0];
            }
        }, $format);
    }
}