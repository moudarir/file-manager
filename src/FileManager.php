<?php

declare(strict_types=1);

namespace Moudarir\FileManager;

use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Image\ImageCropper;
use Moudarir\FileManager\Upload\FileUploader;
use Moudarir\FileManager\Upload\UploadedFileCollection;

final class FileManager
{

    private ?UploadedFileCollection $uploadedFileCollection = null;

    public function __construct(private readonly FileManagerConfig $config)
    {
    }

    /**
     * @throws FileManagerException
     */
    public function upload(?string $filepath = null): self
    {
        $this->uploadedFileCollection = new FileUploader($this->config->uploadConfig())
            ->upload($filepath);

        return $this;
    }

    /**
     * @throws FileManagerException
     */
    public function crop(string $croppingConfig): self
    {
        ImageCropper::create(
            $this->uploadedFileCollection,
            $this->config->imageCropConfig(),
            $croppingConfig
        )->crop();

        return $this;
    }

    public function resize(): self
    {
        return $this;
    }

    public function convert(): self
    {
        return $this;
    }

    public function watermark(): self
    {
        return $this;
    }

    public function uploadedFileCollection(): UploadedFileCollection
    {
        return $this->uploadedFileCollection;
    }
}
