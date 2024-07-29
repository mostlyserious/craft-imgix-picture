# imgix Picture

A Twig helper to generate picture tags from a set of imgix transforms.

## Requirements

This plugin requires Craft CMS 4.9.0 or later, and PHP 8.0.2 or later.

## Installation

You can install this plugin with Composer.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require mostly-serious/craft-imgix-picture

# tell Craft to install the plugin
./craft plugin/install imgix-picture
```

## Configuration

Create an [imgix account](https://dashboard.imgix.com/sign-up) with a source and an [API Key](https://docs.imgix.com/apis/management/overview).

The API key requires `Purge` permissions, and it is used only to purge assets on Craft's Asset events, such as replace.

Add your imgix url and API key as environment variables in your `.env` file.

```
IMGIX_URL="https://example.imgix.net"
IMGIX_API_KEY="aXzY....."
```

Add this config file at `config/imgix-picture`:

```php
<?php

return [
    'imgixUrl' => getenv('IMGIX_URL'),
    'imgixApiKey' => getenv('IMGIX_API_KEY'),
];
```

Use the `picture` function to generate a `<picture>` tag from an Asset.

```
{{ picture(
    assetField.one(),
    [
        {
            fit: 'crop',
            width: 120,
            height: 120
        },
        {
            breakpoint: 1024,
            fit: 'crop',
            width: 500,
            height: 500
        },
    ],
    {
        class: [
            'block',
            'w-full h-auto aspect-square',
            'object-cover',
            'lg:absolute lg:inset-0',
            'lg:h-full lg:aspect-auto',
        ],
    }
) }}
```

The first argument must be a Craft Asset object.

The second is an array of transforms. If only one transform is passed in, an `<img>` tag will be rendered instead of a `picture` tag.

The transforms may contain any parameter in the imgix [Rendering API](https://docs.imgix.com/apis/rendering/overview). Recommended default attributes are set by the plugin as a baseline, but will be overidden with any you provide here.

The third parameter is an object representing html attributes you wish to add to the rendered element. Images are lazy loading by default, but you can override this, for example:

```
{
    fetchpriority: 'high',
}
```