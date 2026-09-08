<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Upload;

use Countable;
use IteratorAggregate;
use Traversable;

final readonly class UploadedFileCollection implements Countable, IteratorAggregate
{

    /**
     * @param UploadedFile[] $files
     */
    public function __construct(private string $field, private array  $files)
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
        return $this->files[0] ?? null;
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
