<?php

namespace mostlyserious\craftimgixpicture;

use Craft;
use yii\base\Event;
use craft\base\Model;
use craft\helpers\App;
use GuzzleHttp\Client;
use craft\elements\Asset;
use craft\services\Assets;
use craft\services\Elements;
use craft\events\ElementEvent;
use craft\events\ReplaceAssetEvent;
use craft\base\Plugin as BasePlugin;
use GuzzleHttp\Exception\ClientException;
use mostlyserious\craftimgixpicture\models\Settings;
use mostlyserious\craftimgixpicture\services\UrlService;
use mostlyserious\craftimgixpicture\twigextensions\ImgixTwigExtension;

/**
 * imgix Picture plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Mostly Serious
 * @copyright Mostly Serious
 * @license https://craftcms.github.io/license/ Craft License
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;

    public string $imgixUrl = '';
    public string $imgixApiKey = '';

    public static function config(): array
    {
        return [
            'components' => [
                'urlService' => [
                    'class' => UrlService::class,
                ]
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->imgixUrl = $this->setting->getImgixUrl();
        $this->imgixApiKey = $this->setting->getImgixApiKey();

        /** Add the picture() Twig function */
        Craft::$app->view->registerTwigExtension(ImgixTwigExtension::instance());

        /** Purge IMGIX for certain Asset events */
        if ($this->imgixApiKey !== '') {
            Event::on(
                Elements::class,
                Elements::EVENT_BEFORE_SAVE_ELEMENT,
                function (ElementEvent $event) {
                    $element = $event->element;
                    $isNewElement = $event->isNew;
                    $isAsset = $element instanceof Asset;
                    if ($isAsset && !$isNewElement) {
                        $this->purgeCache($element);
                    }
                }
            );
            Event::on(
                Asset::class,
                Asset::EVENT_AFTER_DELETE,
                function (Event $event): void {
                    /** @var Asset $asset */
                    $asset = $event->sender;

                    $this->purgeCache($asset);
                }
            );
            Event::on(
                Assets::class,
                Assets::EVENT_BEFORE_REPLACE_ASSET,
                function (ReplaceAssetEvent $event) {
                    $this->purgeCache($event->asset);
                }
            );
        }
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('imgix-picture/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    protected function purgeCache(Asset $asset): void
    {
        if ($asset->supportsImageEditor) {
            $purgeUrl = $this->urlService->sourceUrl($asset);
            $guzzleClient = new Client();

            try {
                $guzzleClient->post('https://api.imgix.com/api/v1/purge', [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', App::env('IMGIX_API_KEY')),
                        'Content-Type' => 'application/vnd.api+json',
                    ],
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'url' => $purgeUrl
                            ],
                            'type' => 'purges',
                        ],
                    ],
                ]);
            } catch (ClientException $e) {
                $response = $e->getResponse();
                $message = [
                    'Failed to purge IMGIX cache.',
                    'Error Code: ' . $e->getResponse()->getStatusCode(),
                    'Reason: ' . $response->getReasonPhrase(),
                ];
                $message = implode(' ', $message);

                Craft::error($message, __METHOD__);
            }
        }
    }
}
