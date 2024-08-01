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
composer require mostlyserious/craft-imgix-picture

# tell Craft to install the plugin
./craft plugin/install imgix-picture
```

## Configuration

This plugin assumes that you have one Asset Volume and File System configured (Typically an Amazon S3 bucket) an an imgix source that references that File System. Note: multiple imgix sources are not supported at this time.

To get started, create an [imgix account](https://dashboard.imgix.com/sign-up) with a source and an [API Key](https://docs.imgix.com/apis/management/overview).

The API key requires `Purge` permissions, and it is used only to purge assets on Craft's Asset events, such as replace.

Add your imgix url and API key as environment variables in your `.env` file.

```
IMGIX_URL="https://example.imgix.net"
IMGIX_API_KEY="aXzY....."
```

Add then add this config file at `config/imgix-picture`:

```php
<?php

return [
    'imgixUrl' => getenv('IMGIX_URL'),
    'imgixApiKey' => getenv('IMGIX_API_KEY'),
    // altTextHandle => 'alternativeText' /* optional */
    // 'defaultParameters' => [] /* optional */
    // 'useNativeTransforms' => false
    // 'fallBackImageSrc' => '/static-assets/default-image-missing-photo.png'
];
```

- `altTextHandle` Alternative Text: This plugin assumes that Craft's native `alt` text field has been added to the desired Assets field layout. If you are using a different field for alternative text, you can override this by providing a different field handle in your config file.
- `defaultParameters` Default Parameters: Override the default imgix parameters tuned for auto format and modest quality with your own defaults.
- `useNativeTransforms` Craft Transforms: set to `true` to bypass imgix and use Craft Transforms instead.
- `fallBackImageSrc` Fallback Image: Imgix has it's own default image that you can set for a source. Apart from that, if you wish to configure an image to display when an Asset is not provided to the picture function you can set that here.

## Usage

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

### The `picture()` function

This function is called with 3 arguments:
1.  The first argument must be a Craft Asset object.
1. The second is an array of transforms.
    - a non-standard `breakpoint` property is a min-width pixel value that adds a min-width media query to `<source>` elements inside the picture, ex: `media="(min-width: 1024px)"`.
    - Focal Point parameters are added automatically if the Asset has a focal point.
    - Transforms may contain any parameter in the imgix [Rendering API](https://docs.imgix.com/apis/rendering/overview). Recommended default attributes are set by the plugin as a baseline, but will be overidden with any you provide here.
    ```php
    /* Defaults are */
    [
        'auto' => 'format,compress',
        'q' => 35,
        'fit' => 'max',
    ];
    ```
    - If only one transform is passed in, an `<img>` tag will be rendered instead of a `picture` tag.
1. The third parameter is an object representing html attributes you wish to add to the rendered element. Images are lazy loading by default, but you can override this, for example:

```
{
    fetchpriority: 'high',
}
```

### Other Functions
- `singleSrc()` create a single transformed url suitable for a `src=""` attribute
- `downloadUrl()` creates a force download link to an imgix asset with the [dl](https://docs.imgix.com/apis/rendering/format/download) param
- `imgixAttrs` returns the imgix attributes without a picture tag
- `getMaxDimensions` returns the max dimensions of an asset within the defined parameters