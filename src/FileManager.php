<?php

declare(strict_types=1);

namespace Moudarir\FileManager;

use Moudarir\FileManager\Upload\UploadedFileCollection;

final class FileManager
{

    private ?UploadedFileCollection $files = null;

    public function __construct(private readonly FileManagerConfig $config)
    {
    }

    public function upload(?string $filepath = null): self
    {
        return $this;
    }

    public function resize(): self
    {
        return $this;
    }

    public function crop(): self
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

    public function files(): UploadedFileCollection
    {
        return $this->files ?? new UploadedFileCollection(
            $this->config->uploadConfig()->field,
            [],
        );
    }
}
