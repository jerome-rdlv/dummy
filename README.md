Dummy
=====

[![pipeline status](https://gitlab.rue-de-la-vieille.fr/jerome/dummy/badges/develop/pipeline.svg)](https://gitlab.rue-de-la-vieille.fr/jerome/dummy/commits/develop)
[![coverage report](https://gitlab.rue-de-la-vieille.fr/jerome/dummy/badges/develop/coverage.svg)](https://gitlab.rue-de-la-vieille.fr/jerome/dummy/commits/develop)

Time saver when you need to fill dummy content in your WordPress site.

For devs, Dummy allow you to stress your integration

    dummy generate --post-type=post --count=15 content=type

## Installing

Installing this package requires WP-CLI v1.1.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@github.com:jerome-rdlv/dummy.git

## Misc

If you need to set a field to an ambiguous value, like you want
to set the value "html:lorem" in meta:test. By default, this value will be interpreted
as HTML generator with "lorem" option. To explicit your raw value, you can use
the raw pseudo generator like this : `meta:test=raw:html:lorem`

## Dummy.yml

Tasks file. Give file example.