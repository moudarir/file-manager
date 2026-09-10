<?php

declare(strict_types=1);

namespace Moudarir\FileManager;

use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Image\ImageConverter;
use Moudarir\FileManager\Image\ImageCropper;
use Moudarir\FileManager\Image\ImageResizer;
use Moudarir\FileManager\Image\ImageWatermarker;
use Moudarir\FileManager\Upload\FileUploader;
use Moudarir\FileManager\Upload\UploadedFileCollection;

final class FileManager
{

    private bool $uploadRequested = false;

    private ?string $filepath = null;

    private bool $cropRequested = false;

    private ?string $croppingConfig = null;

    private bool $resizeRequested = false;

    private bool $convertRequested = false;

    private bool $watermarkRequested = false;

    public function __construct(private readonly FileManagerConfig $config)
    {
    }

    /**
     * @throws FileManagerException
     */
    public function files(): UploadedFileCollection
    {
        if ($this->uploadRequested === false) {
            throw FileManagerException::uploadRequestMandatory();
        }

        $collection = new FileUploader($this->config->uploadConfig())
            ->upload($this->filepath);

        if ($collection->isEmpty()) {
            return $collection;
        }

        if ($this->cropRequested === true && empty($this->croppingConfig) === false) {
            ImageCropper::create(
                $collection,
                $this->config->imageCropConfig(),
                $this->croppingConfig
            )->crop();
        }

        if ($this->resizeRequested === true) {
            ImageResizer::create($collection, $this->config->imageResizeConfig());

            if ($this->watermarkRequested === true) {
                ImageWatermarker::create($collection, $this->config->imageWatermarkConfig());
            }
        }

        if ($this->convertRequested === true) {
            ImageConverter::create($collection, $this->config->imageConvertConfig());
        }

        return $collection;
    }

    public function upload(?string $filepath = null): self
    {
        $this->uploadRequested = true;
        $this->filepath = $filepath;

        return $this;
    }

    public function crop(string $croppingConfig): self
    {
        $this->cropRequested = true;
        $this->croppingConfig = $croppingConfig;

        return $this;
    }

    public function resize(): self
    {
        $this->resizeRequested = true;

        return $this;
    }

    public function watermark(): self
    {
        $this->watermarkRequested = true;

        return $this;
    }

    public function convert(): self
    {
        $this->convertRequested = true;

        return $this;
    }
}
