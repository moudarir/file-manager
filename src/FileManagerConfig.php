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

    private const array DEFAULT_UPLOAD_CONFIG = [
        'field' => '',
        'uploadPath' => '',
        'dateFormat' => null,
        'maxFilesize' => 0,
        'maxImageWidth' => 0,
        'maxImageHeight' => 0,
        'minImageWidth' => 0,
        'minImageHeight' => 0,
        'maxUploadedFiles' => null,
        'allowedMimeTypes' => [],
        'overwrite' => false,
        'encryptName' => true,
    ];

    private const array DEFAULT_CROP_CONFIG = [
        'cropRatioWidth' => 400,
        'cropRatioHeight' => 400,
    ];

    private ?ImageCropConfig $imageCropConfig = null;

    private const array DEFAULT_RESIZE_CONFIG = [
        // Default Image resize params
    ];

    private ?ImageResizeConfig $imageResizeConfig = null;

    private const array DEFAULT_CONVERT_CONFIG = [
        // Default Image convert params
    ];

    private ?ImageConvertConfig $imageConvertConfig = null;

    private const array DEFAULT_WATERMARK_CONFIG = [
        // Default Image watermark params
    ];

    private ?ImageWatermarkConfig $imageWatermarkConfig = null;

    private function __construct(
        private readonly array        $providedConfig,
        private readonly UploadConfig $uploadConfig,
    ) {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(array $config): self
    {
        if ($config === []) {
            throw FileManagerException::missingConfig();
        }

        if (array_key_exists('field', $config) === false) {
            throw FileManagerException::missingParam('field');
        }

        if (array_key_exists('uploadPath', $config) === false) {
            throw FileManagerException::missingParam('uploadPath');
        }

        return new self(
            $config,
            UploadConfig::create(
                self::prepareConfig(self::DEFAULT_UPLOAD_CONFIG, $config)
            ),
        );
    }

    public function uploadConfig(): UploadConfig
    {
        return $this->uploadConfig;
    }

    public function imageCropConfig(): ImageCropConfig
    {
        return $this->imageCropConfig ??= ImageCropConfig::create(
            self::prepareConfig(self::DEFAULT_CROP_CONFIG, $this->providedConfig)
        );
    }

    public function imageResizeConfig(): ImageResizeConfig
    {
        return $this->imageResizeConfig ??= ImageResizeConfig::create(
            self::prepareConfig(self::DEFAULT_RESIZE_CONFIG, $this->providedConfig)
        );
    }

    public function imageConvertConfig(): ImageConvertConfig
    {
        return $this->imageConvertConfig ??= ImageConvertConfig::create(
            self::prepareConfig(self::DEFAULT_CONVERT_CONFIG, $this->providedConfig)
        );
    }

    public function imageWatermarkConfig(): ImageWatermarkConfig
    {
        return $this->imageWatermarkConfig ??= ImageWatermarkConfig::create(
            self::prepareConfig(self::DEFAULT_WATERMARK_CONFIG, $this->providedConfig)
        );
    }

    private static function prepareConfig(array $defaults, array $provided): array
    {
        foreach ($defaults as $key => $value) {
            if (array_key_exists($key, $provided)) {
                $defaults[$key] = $provided[$key];
            }
        }

        return $defaults;
    }
}
