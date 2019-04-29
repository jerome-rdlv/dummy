Dummy
=====

[![pipeline status](https://gitlab.rue-de-la-vieille.fr/jerome/dummy/badges/develop/pipeline.svg)](https://gitlab.rue-de-la-vieille.fr/jerome/dummy/commits/develop)
[![coverage report](https://gitlab.rue-de-la-vieille.fr/jerome/dummy/badges/develop/coverage.svg)](https://gitlab.rue-de-la-vieille.fr/jerome/dummy/commits/develop)

A WP-CLI command to fill WordPress with rich dummy content.

Initial need was to quickly generate fake posts in WordPress to test and stress theme integration. That content is then
visible in staging phase when client checks the integration, before importing her own content into the new website.
That’s why I needed the content to be of good quality, especially images. As the models I work on are often made on
[Sketch][sketch], they embed free photographs from [Unsplash][unsplash], so Dummy use them as image source.
For fake text and html contents, it connect to [Loripsum][loripsum] API.

Dummy can be used directly like this:

    wp dummy generate --post-type=post --count=15 content=html thumb=image title=text:2,8

Or with a configuration file which allow to store and possibly version the commands:

    wp dummy tasks news

Dummy can read an ACF field definition and auto fill ACF fields, including complex fields like Flex or Repeater.
For example, a Flex ACF field named `contents` can be auto filled with:

    wp dummy generate acf:contents

Content created by Dummy is tagged (with a post meta) so when time comes to drop fake content:

    wp dummy clear

Following doc provide basic concepts and examples. For more detailed information, please refer to the inline doc:

    wp help dummy generate

**Disclaimer**: I’m publishing this here because it might be useful to others, but USE OF THIS SCRIPT IS
ENTIRELY AT YOUR OWN RISK. I accept no liability from its use.

## Installing

Installing this package requires WP-CLI v1.1.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@github.com:jerome-rdlv/dummy.git

## Concepts

Dummy uses three concepts:

* _field_: a field of WordPress content like `post_title`, `post_content`, a meta field or even an ACF field.
* _handler_: like `meta` or `acf` to target some types of fields.
* _generator_: a fake content generator like Lorem ipsum html, random images, dates or numbers.

## Fields

A field can be targeted simply by its name, like `post_date`, `post_content` or `post_excerpt`.

Field aliases are available for common fields:

* `author`: `post_author`
* `content`: `post_content`
* `date`: `post_date`
* `thumb`: `meta:_thumbnail_id`
* `title`: `post_title`
* `status`: `post_status`
* `template`: `meta:_wp_page_template`
* `excerpt`: `post_excerpt`
* `order`: `menu_order`

A meta field is targeted by adding the `meta` prefix in front of its name, like in `meta:_thumbnail_id`.

## Handlers

A specific handler can be used by adding a prefix to the field name

### `meta`

Allow to target a post meta. To add a `phone` post meta, use `meta:phone`.

### `acf`

As ACF fields are stored as post meta, an ACF field named `address` can be filled with following example command:

    meta:address=text:6,30

But the `acf` handler allow auto fill a field with dummy content corresponding to its type. Correspondences are
the following ones:

* `image`: `image:1040,800,technology`
* `wysiwyg`: `html:4,short,ul,h2,h3`
* `text`: `text:4,16`
* `textarea`: `text:10,60` 

Complex types Repeater and Flex are handled too so it is possible to target a Flex field like this:

    acf:my_flex_field

No value is needed here.

## Generators

### `html`

Lorem ipsum HTML taken from Loripsum API.

### `text`

Plain text taken from Loripsum API and cleaned. This generator accept two arguments for
min and max word count.

### `image`

Images are searched and downloaded from Unsplash. The search is random by default but can be made predictable
by using `sequential` in generator arguments. Running a search for "yosemite" on Unsplash, you can get same
results with the following generator: `image:sequential,yosemite`

Fake images loaded in WordPress are kept in `draft` status so they don’t appear in library to prevent usage
in legitimate content. Failing to do that would result in missing images after a global `clear` command.

### `date` and `seqdate`

Random dates and sequential dates.

### `number`

Random number.

### `raw`

If you need to set a field to an ambiguous value, like you want
to set the value "html:lorem" in "meta:test". By default, this value will be interpreted
as HTML generator with argument "lorem". To explicit your value, you can use
the `raw` pseudo generator like this : `meta:test=raw:html:lorem`

## Commands

### `generate`

This command provide a list of defaults that allow to run it without any arguments. Here are these:

* `content=html:4,8,medium,ul,h2,h3`
* `date=date:4 months ago,now`
* `thumb=image:1600,1200,landscape,technology`
* `title=text:4,16`
* `status=publish`
* `author=''`

To disable defaults, use `--without-defaults` argument.

This command accept arguments formatted like this:

    [<handler>:]<field>=[<generator>[:<arguments>]]

### `clear`

Clear command delete all content that is tagged as dummy (with a post meta). Use `--post-type` argument to
limit the cleaning, for example to drop all dummy attachments (posts entries in database and files):

    wp dummy clear --post-type=attachment

### `tasks`

The tasks command read a tasks file to find commands that are to be executed.

By default, tasks command look for a `dummy.yml` file in current directory. This can be customized with the `--file`
argument.

By default, tasks command execute all found tasks in order. To select specific tasks, you can give their name as
arguments. In this case, tasks are executed in arguments order:

    wp dummy tasks clear-news create-news

Example of tasks file:

```yml
# first task for news deletion
news-clear:
    # clear all dummy posts of type `post`
    command: clear
    post-type: post

# second task for news generation
news:
    command: generate
    post-type: post
    count: 15
    
    # do not apply defaults
    without-defaults: true
    
    # here the fields rules
    fields:
    
        # fill title with between 8 and 12 random words
        title: text:8,12
        
        # set post_date to a random date picked in five last months
        date: date:5 months ago,now
        
        # fill content with 5 short html paragraph containing links, lists and headings
        content: html:5,short,link,ul,h2,h3
        
        # fill thumbnail with a random cityscape photograph, landscape format
        thumb: image:landscape,cityscape
        
        # add a meta filled with a random number between 0 and 100
        meta:news_num number:0,100
        
        # auto fill `contents` acf field
        acf:contents
 
```

## Todo

* Add more unit tests
* Add more generators (especially based on [Faker](https://github.com/fzaninotto/Faker))
* Improve the doc
* Add ACF type supports


[sketch]: https://www.sketch.com/
[unsplash]: https://unsplash.com/
[loripsum]: https://loripsum.net/