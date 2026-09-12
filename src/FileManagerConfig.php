<?php

declare(strict_types=1);

namespace Moudarir\FileManager;

use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Image\ImageConvertConfig;
use Moudarir\FileManager\Image\ImageCropConfig;
use Moudarir\FileManager\Image\ImageResizeConfig;
use Moudarir\FileManager\Image\ImageWatermarkConfig;
use Moudarir\FileManager\Upload\UploadConfig;

final class FileManagerConfig
{

    private const array DEFAULT_CONFIG = [
        // Upload params
        'field' => '',
        'uploadPath' => '',
        'dateFormat' => null,
        'customDate' => null,
        'maxFilesize' => 0,
        'maxImageWidth' => 0,
        'maxImageHeight' => 0,
        'minImageWidth' => 0,
        'minImageHeight' => 0,
        'maxUploadedFiles' => null,
        'allowedMimeTypes' => [],
        'overwrite' => false,
        'encryptName' => true,
        // Cropping params
        'cropRatioWidth' => 400,
        'cropRatioHeight' => 400,
        // Resize params
        'resizePath' => '',
        'thumbs' => [],
        'resizeQuality' => 85,
        'removeAfterResize' => false,
        // Watermark params
        'watermarks' => [],
        // Conversion params
        'removeAfterConvert' => false,
    ];

    private ?UploadConfig $uploadConfig = null;

    private ?ImageCropConfig $imageCropConfig = null;

    private ?ImageResizeConfig $imageResizeConfig = null;

    private ?ImageConvertConfig $imageConvertConfig = null;

    private ?ImageWatermarkConfig $imageWatermarkConfig = null;

    private function __construct(private readonly array $config, private readonly array $providedConfig)
    {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(array $config): self
    {
        if ($config === []) {
            throw FileManagerException::missingConfig();
        }

        return new self(self::prepareConfig($config), $config);
    }

    /**
     * @throws FileManagerException
     */
    public function uploadConfig(): UploadConfig
    {
        if (
            is_string($this->config['field']) === false ||
            trim($this->config['field']) === ''
        ) {
            throw FileManagerException::invalidParam('field');
        }

        if (
            is_string($this->config['uploadPath']) === false ||
            trim($this->config['uploadPath']) === ''
        ) {
            throw FileManagerException::invalidParam('uploadPath');
        }

        return $this->uploadConfig ??= UploadConfig::create($this->config);
    }

    /**
     * @throws FileManagerException
     */
    public function imageCropConfig(): ImageCropConfig
    {
        return $this->imageCropConfig ??= ImageCropConfig::create($this->config);
    }

    /**
     * @throws FileManagerException
     */
    public function imageResizeConfig(): ImageResizeConfig
    {
        if (!function_exists('getimagesize')) {
            throw FileManagerException::gdLibRequired();
        }

        if (
            is_string($this->config['resizePath']) === false ||
            trim($this->config['resizePath']) === ''
        ) {
            throw FileManagerException::invalidParam('resizePath');
        }

        if (
            is_array($this->config['thumbs']) === false ||
            $this->config['thumbs'] === []
        ) {
            throw FileManagerException::missingParam('thumbs');
        }

        return $this->imageResizeConfig ??= ImageResizeConfig::create($this->config);
    }

    /**
     * @throws FileManagerException
     */
    public function imageConvertConfig(): ImageConvertConfig
    {
        return $this->imageConvertConfig ??= ImageConvertConfig::create($this->config);
    }

    /**
     * @throws FileManagerException
     */
    public function imageWatermarkConfig(): ImageWatermarkConfig
    {
        if (!function_exists('getimagesize')) {
            throw FileManagerException::gdLibRequired();
        }

        if (
            is_string($this->config['resizePath']) === false ||
            trim($this->config['resizePath']) === ''
        ) {
            throw FileManagerException::invalidParam('resizePath');
        }

        if (
            is_array($this->config['thumbs']) === false ||
            $this->config['thumbs'] === []
        ) {
            throw FileManagerException::missingParam('thumbs');
        }

        if (
            is_array($this->config['watermarks']) === false ||
            $this->config['watermarks'] === []
        ) {
            throw FileManagerException::missingParam('watermarks');
        }

        return $this->imageWatermarkConfig ??= ImageWatermarkConfig::create($this->config);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getProvidedConfig(): array
    {
        return $this->providedConfig;
    }

    private static function prepareConfig(array $provided): array
    {
        $defaults = self::DEFAULT_CONFIG;

        foreach ($defaults as $key => $value) {
            if (array_key_exists($key, $provided)) {
                $defaults[$key] = $provided[$key];
            }
        }

        return $defaults;
    }
}
