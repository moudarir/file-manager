<?php

declare(strict_types=1);

namespace Moudarir\FileManager;

use Moudarir\FileManager\Collections\CollectionBuilder;
use Moudarir\FileManager\Collections\CollectionInterface;
use Moudarir\FileManager\Config\FileManagerConfig;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Image\ImageConverter;
use Moudarir\FileManager\Image\ImageCropper;
use Moudarir\FileManager\Image\ImageResizer;
use Moudarir\FileManager\Image\ImageWatermarker;
use Moudarir\FileManager\Upload\FileUploader;

final class FileManager
{

    private bool $uploadRequested = false;

    private ?string $filepath = null;

    private bool $cropRequested = false;

    private ?string $croppingConfig = null;

    private bool $resizeRequested = false;

    private bool $convertRequested = false;

    private bool $watermarkRequested = false;

    private ?CollectionInterface $collection = null;

    public function __construct(private readonly FileManagerConfig $config)
    {
    }

    /**
     * @throws FileManagerException
     */
    public function files(): CollectionInterface
    {
        if (isset($this->collection) === false) {
            if ($this->uploadRequested === false) {
                throw FileManagerException::uploadRequestMandatory();
            }

            $this->collection = new FileUploader($this->config->uploadConfig())
                ->upload($this->filepath);
        }

        if ($this->collection->isEmpty()) {
            return $this->collection;
        }

        if ($this->cropRequested === true && empty($this->croppingConfig) === false) {
            ImageCropper::create(
                $this->collection,
                $this->config->imageCropConfig(),
                $this->croppingConfig
            )->crop();
        }

        if ($this->resizeRequested === true) {
            ImageResizer::create($this->collection, $this->config->imageResizeConfig());
        }

        if ($this->watermarkRequested === true) {
            ImageWatermarker::create($this->collection, $this->config->imageWatermarkConfig());
        }

        if ($this->convertRequested === true) {
            ImageConverter::create($this->collection, $this->config->imageConvertConfig());
        }

        return $this->collection;
    }

    public function upload(?string $filepath = null): self
    {
        $this->uploadRequested = true;
        $this->filepath = $filepath;

        return $this;
    }

    /**
     * @throws FileManagerException
     */
    public function buildUploadedFileCollection(array $files, bool $ignoreInvalidFiles = false): self
    {
        $this->collection = CollectionBuilder::create(
            $files,
            $this->config->collectionBuilderConfig(),
            $ignoreInvalidFiles
        );

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
