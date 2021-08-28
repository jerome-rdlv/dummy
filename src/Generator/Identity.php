<?php


namespace Rdlv\WordPress\Dummy\Generator;


use Faker\Factory;
use Faker\Generator;
use Rdlv\WordPress\Dummy\DummyException;
use Rdlv\WordPress\Dummy\GeneratorInterface;

/**
 * Identity generator
 *
 * ## Options
 *
 *      - info: Info to generate, like firstname
 *      - locale: Localisation of generated data
 *
 * ## Short syntax
 *
 *        # using default locale
 *        {id}:<info>
 *
 *        # with specific locale
 *        {id}:<info>,<locale>
 *
 * ## Examples
 *
 *        {id}:first
 *        {id}:last
 */
class Identity implements GeneratorInterface
{
	const INFO_ALIASES = [
		'first' => 'firstName',
		'last'  => 'lastName',
	];

	const LOCALE_ALIASES = [
		'en' => 'en_US',
		'fr' => 'fr_FR',
	];

	const DEFAULT_LOCALE = 'fr_FR';

	/** @var array */
	private $fakers = [];

	/**
	 * @inheritDoc
	 */
	public function normalize($args)
	{
		if (isset($args[0])) {
			$args['info'] = $args['0'];
		}

		if (isset($args['locale']) && array_key_exists($args['locale'], self::LOCALE_ALIASES)) {
			$args['locale'] = self::LOCALE_ALIASES[$args['locale']];
		}

		if (isset($args['info']) && array_key_exists($args['info'], self::INFO_ALIASES)) {
			$args['info'] = self::INFO_ALIASES[$args['info']];
		}

		return $args;
	}

	/**
	 * @inheritDoc
	 */
	public function validate($args)
	{
		if (!array_key_exists('info', $args)) {
			throw new DummyException('should specify info to return.');
		}
	}

	private function getFaker(string $locale): Generator
	{
		if (!array_key_exists($locale, $this->fakers)) {
			$this->fakers[$locale] = Factory::create($locale);
		}
		return $this->fakers[$locale];
	}

	/**
	 * @inheritDoc
	 */
	public function get($args, $post_id = null)
	{
		return $this->getFaker($args['locale'] ?? self::DEFAULT_LOCALE)->{$args['info']};
	}
}
