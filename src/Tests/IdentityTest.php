<?php


namespace Rdlv\WordPress\Dummy\Tests;


use PHPUnit\Framework\TestCase;
use Rdlv\WordPress\Dummy\Generator\Identity;

class IdentityTest extends TestCase
{
	public function testNormalization()
	{
		$this->assertEquals(['info' => 'firstName'], (new Identity())->normalize(['info' => 'first']));
	}

	public function testGenerate()
	{
		$gen = new Identity();
		$this->assertNotNull($gen->get(['info' => 'firstName']));
		$this->assertNotNull($gen->get(['info' => 'lastName']));
	}

}
