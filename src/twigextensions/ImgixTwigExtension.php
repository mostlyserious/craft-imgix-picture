<?php

namespace mostlyserious\craftimgixpicture\twigextensions;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Html;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use mostlyserious\craftimgixpicture\services\UrlService;
use mostlyserious\craftimgixpicture\Plugin;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ImgixTwigExtension extends AbstractExtension
{
    private static $instance;
    private $default_src = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getName()
    {
        return 'MS Craft imgix Picture';
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('picture', [$this, 'picture']),
            new TwigFunction('singleSrc', [$this, 'singleSrc']),
            new TwigFunction('imgixAttrs', [$this, 'imgixAttrs']),
            new TwigFunction('downloadUrl', [$this, 'downloadUrl']),
            new TwigFunction('getMaxDimensions', [$this, 'calculateMaxDimensions']),
        ];
    }

    /**
     * Creates a Url to download an original file using the IMGIX 'dl' parameter.
     * See: https://docs.imgix.com/apis/rendering/format/dl
     */
    public function downloadUrl(Asset $asset)
    {
        $download_url = Plugin::getInstance()->urlService->sourceUrl($asset);

        return $download_url . '?dl';
    }

    /**
     * Prepare a single imgix transform and return the raw attributes of the image tag it would generate as an array.
     */
    public function imgixAttrs(Asset $asset, array $transform = [])
    {
        $attributes = $this->buildAttributes($asset, $transform);
        $attributes['src'] = $this->default_src;

        return $attributes;
    }

    /**
     * Generates a single source URL for an asset for use when you need only one size.
     *
     * @param Asset $asset The asset object to generate the URL for.
     * @param array $transform An array of imgix params.
     * @return string The generated URL with suitable for a 'src' attribute.
     */
    public function singleSrc(Asset $asset, array $transform = [])
    {
        if (array_key_exists('breakpoint', $transform)) {
            unset($transform['breakpoint']);
        };

        if ($this->useNative()) {
            $transform = $this->formatTransformForNative($transform);
            $asset->setTransform($transform);

            return $asset->getUrl();
        }

        $transform = $this->inheritImgixDefaults($asset, $transform);
        $url = Plugin::getInstance()->urlService->sourceUrl($asset);

        return $url . '?' . http_build_query($transform);
    }

    /**
     * Generate a picture element.
     *
     * @param Asset|null $asset A Craft Asset object.
     * @param array $transforms An array of imgix transforms with imgix params.
     * @param array $img_attributes An array of HTML attributes to add to the final image tag.
     * @return string The generated picture tag as a raw HTML string.
     */
    public function picture(Asset|null $asset, array $transforms = [], array $img_attributes = [])
    {
        if (!$asset || !($asset instanceof Asset)) {
            $fallback_src = Plugin::getInstance()->settings->getFallBackImageSrc();
            if ($fallback_src) {
                return Template::raw(Html::tag('img', '', array_merge(
                    [
                        'src' => UrlHelper::url($fallback_src),
                        'alt' => 'No Image Available',
                        'aria-hidden' => 'true',
                    ],
                    $img_attributes
                )));
            }

            return App::devMode()
                ? 'Please provide an Asset object as the first argument to the picture() Twig function.'
                : '';
        }

        if (!$this->validateTransforms($transforms)) {
            return App::devMode()
                ? 'Please provide an array of transforms as the second parameter.'
                : '';
        }

        $supported_extensions = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'svg', 'gif'];
        if (!$this->useNative()) {
            $supported_extensions[] = 'pdf';
        }

        if (!in_array(strtolower($asset->extension), $supported_extensions)) {
            return App::devMode()
                ? 'Images must have a valid extension: ' . implode(', ', $supported_extensions)
                : '';
        }

        $alt_text_handle = Plugin::getInstance()->settings->altTextHandle;
        $default_img_attributes = [
            'loading' => 'lazy',
            'alt' => $asset->{$alt_text_handle} ?? '',
        ];

        if (
            isset($img_attributes['fetchpriority'])
            && $img_attributes['fetchpriority'] === 'high'
        ) {
            unset($default_img_attributes['loading']);
        }

        /**
         * SVG and GIF formats are never transformed.
         */
        if (
            in_array($asset->extension, ['svg', 'gif'])
            || count($transforms) === 0
        ) {
            return Template::raw(Html::tag('img', '', array_merge(
                $default_img_attributes,
                [
                    'width' => $asset->width,
                    'height' => $asset->height,
                ],
                $img_attributes,
                [
                    'src' => $asset->url,
                ]
            )));
        }

        /**
         * Handle a single transform.
         * Output an <img> tag instead of a <picture>
         */
        if (count($transforms) === 1) {
            $transform = $transforms[0];
            if (array_key_exists('breakpoint', $transform)) {
                unset($transform['breakpoint']);
            };

            return Template::raw(Html::tag('img', '', array_merge(
                $default_img_attributes,
                $this->buildAttributes($asset, $transform),
                [
                    'src' => $this->default_src,
                ],
                $img_attributes
            )));
        }

        /**
         * Handle multiple transforms.
         */
        $picture_contents = '';
        $source_transforms = $this->sortByBreakpoint($transforms);

        foreach ($source_transforms as $transform) {
            $picture_contents .= Html::tag('source', '', $this->buildAttributes($asset, $transform));
        }

        $fallback_transform = $this->getFallback($transforms);
        if (!$fallback_transform) {
            $fallback_transform = $transforms[0];
        }

        $final_img_attributes = array_merge(
            $default_img_attributes,
            $this->buildAttributes($asset, $fallback_transform),
            [
                'src' => $this->default_src,
            ],
            $img_attributes
        );

        $picture_contents .= Html::tag('img', '', $final_img_attributes);

        return Template::raw(Html::tag('picture', $picture_contents));
    }

    /**
     * Determine the max width and height based on an image's aspect ratio and a max px value..
     *
     * @param  Asset   $asset  An asset object
     * @param  Integer $max_px The max length in pixels of the longest side
     * @return array   The max dimensions
     */
    public function calculateMaxDimensions(Asset $asset, int $max_px = 1200): array
    {
        $aspect_original = floor(($asset->width / $asset->height) * 1000) / 1000;
        $safe_width = $safe_height = 0;

        if ($asset->width >= $max_px || $asset->height >= $max_px) {
            if ($asset->width > $asset->height) {
                $safe_width = $max_px;
                $safe_height = round($max_px / $aspect_original);
            } else {
                $safe_height = $max_px;
                $safe_width = round($max_px * $aspect_original);
            }
        } else {
            $safe_width = $asset->width;
            $safe_height = $asset->height;
        }

        return ['width' => $safe_width, 'height' => $safe_height];
    }

    /**
     * Filters and sorts transforms by their breakpoint from smallest to largest.
     *
     * @param  array $transforms  an array of transform settings.
=     * @return array The sorted transforms.
     */
    private function sortByBreakpoint($transforms)
    {
        $result = array_filter($transforms, function ($item) {
            return isset($item['breakpoint']);
        });

        usort($result, function ($a, $b) {
            $breakpoint_a = array_key_exists('breakpoint', $a)
                ? intval($a['breakpoint'])
                : 0;
            $breakpoint_b = array_key_exists('breakpoint', $b)
                ? intval($b['breakpoint'])
                : 0;

            return $breakpoint_b <=> $breakpoint_a;
        });

        return $result;
    }

    /**
     * Gets the first transform with no breakpoint set.
     *
     * @param  array $transforms  an array of transforms.
     * @return array The fallback transform.
     */
    private function getFallback($transforms)
    {
        $result = array_filter($transforms, function ($item) {
            return !isset($item['breakpoint']);
        });

        return count($result) >= 1 ? $result[0] : null;
    }

    /**
     * Creates an array of attributes to be used on a <source> or <img> tag.
     *
     * @param  Asset $asset     The image asset.
     * @param  array $transform an array of transform settings.
     * @return array The tag attributes with the transform applied.
     */
    private function buildAttributes(Asset $asset, array $transform): array
    {
        $breakpoint = isset($transform['breakpoint'])
            ? intval($transform['breakpoint'])
            : null;
        $dimensions = $this->calculateDimensions($asset, $transform);

        if (array_key_exists('breakpoint', $transform)) {
            unset($transform['breakpoint']);
        };

        if ($this->useNative()) {
            $transform = $this->formatTransformForNative($transform);
            $asset->setTransform($transform);

            return [
                'srcset' => $asset->getSrcset(['1x', '1.5x']),
                'media' => $breakpoint
                    ? "(min-width: {$breakpoint}px)"
                    : null,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
            ];
        }

        $transform = $this->inheritImgixDefaults($asset, $transform);
        $transform_high_dpr = array_merge($transform, ['dpr' => 1.5]);
        $urlService = new UrlService();
        $url = $urlService->sourceUrl($asset);

        return [
            'srcset' => implode(', ', [
                $url . '?' . http_build_query($transform) . ' 1x',
                $url . '?' . http_build_query($transform_high_dpr) . ' 1.5x',
            ]),
            'media' => $breakpoint ? "(min-width: {$breakpoint}px)" : null,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ];
    }

    /**
     * Gets the width and height of a transformed image for use in tag attributes.
     *
     * @param  Asset $asset     The image asset.
     * @param  array $transform an array include at least one dimension
     * @return array An array containing the width and height of the transformed image, or null if no dimensions could be calculated.
     */
    private function calculateDimensions(Asset $asset, array $transform): array
    {
        $original_width = $asset->width ?? 0;
        $original_height = $asset->height ?? 0;

        if ($original_width <= 0 || $original_height <= 0) {
            return [
                'width' => null,
                'height' => null
            ];
        }

        $new_width = isset($transform['width']) ? intval($transform['width']) : 0;
        $new_height = isset($transform['height']) ? intval($transform['height']) : 0;
        $is_crop = (
            isset($transform['fit']) && in_array($transform['fit'], ['crop', 'clamp', 'facearea', 'fill', 'fillmax'])
        ) || (
            isset($transform['mode']) && $transform['mode'] === 'crop'
        );

        /* If cropping, return desired new width and new height. Otherwise, calculate it based on the image aspect ratio */
        if ($is_crop && $new_width > 0 && $new_height > 0) {
            return [
                'width' => $new_width,
                'height' => $new_height,
            ];
        }

        /** If we are fitting to max and both a width and height are applied, go with the largest of the two. */
        if ($new_width > 0 && $new_height > 0) {
            if ($new_width > $new_height) {
                $new_height = 0;
            } else {
                $new_width = 0;
            }
        }

        if ($new_width > 0 && $new_height === 0) {
            return [
                'width' => $new_width,
                'height' => round(($new_width / $original_width) * $original_height),
            ];
        }
        if ($new_width === 0 && $new_height > 0) {
            return [
                'width' => round(($new_height / $original_height) * $original_width),
                'height' => $new_height,
            ];
        }

        return [
            'width' => null,
            'height' => null
        ];
    }

    /**
     * Merges the default parameters with a transform's parameters, the transform params override the defaults.
     *
     * @param Asset $asset The asset object.
     * @param array $transform The transform parameters.
     * @return array The merged parameters.
     */
    private function inheritImgixDefaults(Asset $asset, array $transform): array
    {
        $default_params = [
            'auto' => 'format,compress',
            'q' => 35,
            'fit' => 'max',
        ];

        $config_params = Plugin::getInstance()->settings->defaultParameters;
        if (is_array($config_params) && count($config_params) > 0) {
            $default_params = array_merge($default_params, $config_params);
        }

        if (isset($transform['fit']) && $transform['fit'] === 'crop') {
            $focal_point_defaults = $asset->hasFocalPoint
                ? [
                    'crop' => 'focalpoint',
                    'fp-x' => $asset->focalPoint['x'],
                    'fp-y' => $asset->focalPoint['y'],
                ]
                : [
                    'crop' => 'faces,center',
                ];

            $default_params = array_merge($default_params, $focal_point_defaults);
        }

        $result = array_merge($default_params, $transform);

        return $result;
    }

    /**
     * Validates the provided transforms parameter is an array of arrays.
     *
     * @param  array $transforms The array of transforms to validate.
     * @return bool  true if the provided array is valid, false otherwise.
     */
    private function validateTransforms(array $transforms): bool
    {
        return array_reduce($transforms, function ($carry, $item) {
            return $carry && is_array($item);
        }, true);
    }

    /**
     * Whether to use native Craft transforms.
     *
     * @return bool true if the required IMGIX config var does not exist
     */
    private function useNative(): bool
    {
        $settings = Plugin::getInstance()->settings;

        return $settings->useNativeTransforms || $settings->getImgixUrl() === '';
    }

    /**
     * Formats the given transform to contain only valid keys for a Craft CMS native Transform.
     *
     * @param array $transform The transform array to format.
     * @return array The formatted transform array.
     */
    private function formatTransformForNative(array $transform): array
    {
        $validKeys = [
            'width',
            'height',
            'mode',
            'quality',
            'format',
            'position',
        ];

        $validFormats = [
            'jpg',
            'gif',
            'png',
            'webp',
            'avif',
        ];

        $formatted = $transform;

        if (isset($formatted['w'])) {
            $formatted['width'] = $formatted['w'];
        }

        if (isset($formatted['h'])) {
            $formatted['height'] = $formatted['h'];
        }

        if (isset($formatted['fit']) && $formatted['fit'] === 'crop') {
            $formatted['mode'] = 'crop';
        } else {
            $formatted['mode'] = 'fit';
        }

        if (isset($formatted['format']) && !in_array($formatted['format'], $validFormats)) {
            unset($formatted['format']);
        }

        $formatted = array_filter($formatted, function ($key) use ($validKeys) {
            return in_array($key, $validKeys);
        }, ARRAY_FILTER_USE_KEY);

        return $formatted;
    }
}
