<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Collections;

use Countable;
use IteratorAggregate;
use Moudarir\FileManager\Upload\UploadedFile;
use Traversable;

final readonly class UploadedFileCollection implements Countable, IteratorAggregate, CollectionInterface
{

    /**
     * @param UploadedFile[] $files
     */
    public function __construct(private string $field, private array $files)
    {
    }

    public function field(): string
    {
        return $this->field;
    }

    public function count(): int
    {
        return count($this->files);
    }

    /**
     * @return UploadedFile[]
     */
    public function all(): array
    {
        return $this->files;
    }

    public function first(): ?UploadedFile
    {
        if ($this->files === []) {
            return null;
        }

        return $this->files[array_key_first($this->files)];
    }

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    /**
     * @return Traversable<int, UploadedFile>
     */
    public function getIterator(): Traversable
    {
        yield from $this->files;
    }
}
