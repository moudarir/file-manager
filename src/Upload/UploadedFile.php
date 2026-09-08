<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Upload;

use DateTimeImmutable;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\FileResource;

final class UploadedFile
{

    private function __construct(
        private readonly FileResource      $fileResource,
        private readonly MimeType          $mimeType,
        private readonly string            $originalName,
        private readonly DateTimeImmutable $createdAt,
        private int                        $filesize,
        private ?array                     $imageDimensions = null,
        private bool                       $converted = false,
    ) {
    }

    public static function create(
        FileResource $fileResource,
        MimeType     $mimeType,
        string       $originalName,
        DateTimeImmutable $createdAt,
        int          $filesize,
        ?array       $imageDimensions = null,
    ): self
    {
        return new self(
            $fileResource,
            $mimeType,
            $originalName,
            $createdAt,
            $filesize,
            $imageDimensions,
        );
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function dirname(): string
    {
        return $this->fileResource->dirname();
    }

    public function filepath(): string
    {
        return $this->fileResource->filepath();
    }

    public function filesize(): int
    {
        return $this->filesize;
    }

    public function changeFilesize(int $filesize): self
    {
        $this->filesize = $filesize;
        return $this;
    }

    public function filename(): string
    {
        return $this->fileResource->filename();
    }

    public function basename(): string
    {
        return $this->fileResource->basename();
    }

    public function extension(): string
    {
        return $this->fileResource->extension();
    }

    public function mimeType(): MimeType
    {
        return $this->mimeType;
    }

    public function mimeTypeValue(): string
    {
        return $this->mimeType->value;
    }

    public function isImage(): bool
    {
        return $this->mimeType->isImage();
    }

    public function imageDimensions(): ?array
    {
        return $this->imageDimensions;
    }

    public function imageWidth(): ?int
    {
        return $this->imageDimensions['width'] ?? null;
    }

    public function imageHeight(): ?int
    {
        return $this->imageDimensions['height'] ?? null;
    }

    public function changeImageDimensions(array $newDimensions): self
    {
        $this->imageDimensions = $newDimensions;
        return $this;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function converted(): bool
    {
        return $this->converted;
    }

    public function markAsConverted(): self
    {
        $this->converted = true;
        return $this;
    }
}
