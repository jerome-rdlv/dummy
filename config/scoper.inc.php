<?php

use Isolated\Symfony\Component\Finder\Finder;

return [
	'prefix'   => 'Rdlv\\Wordpress\\Dummy\\Vendor',

	/*
	 * By default when running php-scoper add-prefix, it will prefix all relevant code found in the current working
	 * directory. You can however define which files should be scoped by defining a collection of Finders in the
	 * following configuration key.
	 *
	 * For more see: https://github.com/humbug/php-scoper#finders-and-paths
	 */
	'finders'  => [
		Finder::create()->files()->in('vendor/symfony/config'),
		Finder::create()->files()->in('vendor/symfony/dependency-injection'),
		Finder::create()->files()->in('vendor/symfony/yaml'),
		Finder::create()->files()->in('vendor/psr'),
	],

	/*
	 * When scoping PHP files, there will be scenarios where some of the code being scoped indirectly references the
	 * original namespace. These will include, for example, strings or string manipulations. PHP-Scoper has limited
	 * support for prefixing such strings. To circumvent that, you can define patchers to manipulate the file to your
	 * heart contents.
	 *
	 * For more see: https://github.com/humbug/php-scoper#patchers
	 */
	'patchers' => [
		function (string $filePath, string $prefix, string $content): string {
			switch ($filePath) {
				case dirname(__DIR__) . '/vendor/symfony/dependency-injection/Compiler/ResolveInstanceofConditionalsPass.php':
					return str_replace(
						[
							'$definition = \\substr_replace($definition, \'53\', 2, 2);',
							'$definition = \\substr_replace($definition, \'Child\', 44, 0);',
						],
						[
							'$definition = \\substr_replace($definition, strlen(ChildDefinition::class), 2, 2);',
							'$definition = \\substr_replace($definition, \'Child\', strlen(Definition::class) - 4, 0);',
						],
						$content
					);
			}
			return $content;
		},
	],
];
