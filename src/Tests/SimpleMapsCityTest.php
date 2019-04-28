<?php

/** @noinspection PhpParamsInspection, PhpUnhandledExceptionInspection */

namespace Rdlv\WordPress\Dummy\Tests;


use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Generator\SimpleMapsCity;

class SimpleMapsCityTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // create file before test
        file_put_contents(
            (new SimpleMapsCity())->get_database_file(),
            <<<EOF
"city","city_ascii","lat","lng","country","iso2","iso3","admin_name","capital","population","id"
"Paris","Paris","48.8667","2.3333","France","FR","FRA","Île-de-France","primary","9904000","1250015082"
"Clermont-Ferrand","Clermont-Ferrand","45.7800","3.0800","France","FR","FRA","Auvergne-Rhône-Alpes","minor","233050","1250135509"
"Metz","Metz","49.1203","6.1800","France","FR","FRA","Grand Est","minor","409186","1250778717"
"Renton","Renton","47.4757","-122.1904","United States","US","USA","Washington","","100953","1840019827"
"Chehalis","Chehalis","46.6649","-122.9660","United States","US","USA","Washington","","7498","1840018472"
"Mercer Island","Mercer Island","47.5624","-122.2265","United States","US","USA","Washington","","25134","1840019830"
"Lynnwood","Lynnwood","47.8285","-122.3034","United States","US","USA","Washington","","38092","1840019788"
"Centralia","Centralia","46.7226","-122.9695","United States","US","USA","Washington","","41077.0","1840018471"
"Mountlake Terrace","Mountlake Terrace","47.7921","-122.3077","United States","US","USA","Washington","","21182","1840019792"
"Venice","Venice","45.4387","12.3350","Italy","IT","ITA","Veneto","admin","270816","1380660414"
EOF
        );
    }

    public function testNormalize()
    {
        $gen = new SimpleMapsCity();
        $this->assertEquals(
            ['country_code' => 'FR'],
            $gen->normalize(['FR'])
        );
        $this->assertEquals(
            ['country_code' => 'FR', 'format' => 'test'],
            $gen->normalize(['FR', 'test'])
        );
        $this->assertEquals(
            ['country_code' => 'US', 'format' => 'test'],
            $gen->normalize(['test', 'US'])
        );
    }

    public function testNormalizeTooManyArgs()
    {
        $this->expectExceptionMessage("at most two arguments expected");
        (new SimpleMapsCity())->normalize(['AU', 'test', 'test']);
    }

    public function testValidationInexistantCountryCode()
    {
        $this->expectExceptionMessage("country 'aa' does not exist");
        (new SimpleMapsCity())->validate(['country_code' => 'aa']);
    }

    public function testGet()
    {
        $gen = new SimpleMapsCity();
        $this->assertEquals('Venice', $gen->get(['country_code' => 'IT']));
        
        // test format
        $this->assertEquals('Venice - Italy', $gen->get([
            'country_code' => 'IT',
            'format' => '{name} - {country}'
        ]));
        
        // test that we can get all FR cities
        $args = ['country_code' => 'FR'];
        $test_cities = ['Paris', 'Clermont-Ferrand', 'Metz'];
        $random_cities = [];
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($test_cities as $city) {
            $random_cities[] = $gen->get($args);
        }
        foreach ($test_cities as $city) {
            $this->assertContains($city, $random_cities);
        }
        
        // test that we loop in available cities
        $this->assertNotEmpty($gen->get($args));
        
        
    }
}