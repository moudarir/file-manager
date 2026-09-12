<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Upload;

use DateTimeInterface;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\FileResource;

final class UploadedFile
{

    private function __construct(
        private readonly string            $originalName,
        private readonly DateTimeInterface $createdAt,
        private string                     $dirname,
        private string                     $filepath,
        private string                     $basename,
        private string                     $filename,
        private string                     $extension,
        private int                        $filesize,
        private MimeType                   $mimeType,
        private ?array                     $imageDimensions = null,
        private bool                       $converted = false,
    ) {
    }

    public static function create(
        FileResource $fileResource,
        MimeType     $mimeType,
        string       $originalName,
        DateTimeInterface $createdAt,
        ?array       $imageDimensions = null,
    ): self
    {
        return new self(
            $originalName,
            $createdAt,
            $fileResource->dirname(),
            $fileResource->filepath(),
            $fileResource->basename(),
            $fileResource->filename(),
            $fileResource->extension(),
            $fileResource->filesize(),
            $mimeType,
            $imageDimensions,
        );
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function createdAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function dirname(): string
    {
        return $this->dirname;
    }

    public function changeDirname(string $value): self
    {
        $this->dirname = $value;

        return $this;
    }

    public function filepath(): string
    {
        return $this->filepath;
    }

    public function changeFilepath(string $value): self
    {
        $this->filepath = $value;

        return $this;
    }

    public function basename(): string
    {
        return $this->basename;
    }

    public function changeBasename(string $value): self
    {
        $this->basename = $value;

        return $this;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function changeFilename(string $value): self
    {
        $this->filename = $value;

        return $this;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function changeExtension(string $value): self
    {
        $this->extension = $value;

        return $this;
    }

    public function filesize(): int
    {
        return $this->filesize;
    }

    public function changeFilesize(int $value): self
    {
        $this->filesize = $value;
        return $this;
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

    public function changeMimeType(MimeType $value): self
    {
        $this->mimeType = $value;
        return $this;
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
