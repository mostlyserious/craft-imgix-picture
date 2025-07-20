# imgix Picture

A Twig helper to generate responsive, optimized `<picture>` tags from a set of image transforms. It is intended for use with imgix, but can be used with Craft's native transforms also.

## Requirements

This plugin requires Craft CMS 4.9.0 or later, and PHP 8.0.2 or later.

It assumes that you have at least one Asset Volume and File System configured (Typically an Amazon S3 bucket) and a corresponding imgix source that references that Asset Volume's File System.

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

## Getting Started

Create an [imgix account](https://dashboard.imgix.com/sign-up) with at least one source and an [API Key](https://docs.imgix.com/apis/management/overview).

The API key requires `Purge` permissions, and it is used only to purge assets on Asset events, such as replace.

Add your imgix url and API key as environment variables to your `.env` file.

```
IMGIX_API_KEY="aXzY....."
IMGIX_SOURCE_URL="https://example.imgix.net"
```

## Configuration

Create a config file at `/config/imgix-picture.php`.

Configure an imgix source for each Asset Volume by adding each volume to the `volumes` array using the volume handle as the key.

```php
<?php

return [
    'imgixApiKey' => getenv('IMGIX_API_KEY'),
    'volumes' => [
        /** Settings for the "Uploads" volume */
        'uploads' => [
            'imgixSourceUrl' => getenv('IMGIX_SOURCE_URL'),
            // 'useNativeTransforms' => false /* optional */
            // 'altTextHandle' => 'alternativeText' /* optional */
        ],
        /** Settings for the other volumes */
        // 'volumeHandle' => [
        // ...
        // ],
    ],
    // 'defaultParameters' => [] /* optional */
    // 'fallBackImageSrc' => '/static-assets/default-image-missing-photo.png' /* optional */
];
```

If no Volume handle is found, Craft's native transforms are used. You can also force a volume to use native transforms by setting the `useNativeTransforms` config for that volume to `true`.

### Legacy Configuration

Early versions of the plugin used a single imgix source that was applied to all volumes. It is recommended to migrate to a per-volume configuration, as described above; but the following flat configuration is still supported using the `imgixUrl` key. This is equivalent to having one Volume configured:

```php
<?php

return [
    'imgixApiKey' => getenv('IMGIX_API_KEY'),
    'imgixUrl' => getenv('IMGIX_URL'),
    // 'useNativeTransforms' => false /* optional */
    // 'altTextHandle' => 'alternativeText' /* optional */
    // 'defaultParameters' => [] /* optional */
    // 'fallBackImageSrc' => '/static-assets/default-image-missing-photo.png' /* optional */
];
```

### Settings Explained

- `altTextHandle` Alternative Text: This plugin assumes that Craft's native `alt` text field has been added to the desired Asset Volume's field layout. If you are using a different field for alternative text, you can override this by providing a different field handle in your config file.
- `defaultParameters` Default Parameters: The plugin uses initial imgix parameters tuned for auto format and modest quality, but you can override these with with your own.
    ex. `[ 'q' => 100 ]`
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

## Future Improvements (no timeline yet)
- [] Ensure that a `media` attribute is not output if the result is a single `img` tag
- [] Add support for use as a twig filter `|picture()`
- [] Add support for manual media queries
- [] Provide examples of how to use with the Element API
