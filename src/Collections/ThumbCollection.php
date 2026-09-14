<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Collections;

use Countable;
use IteratorAggregate;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Upload\UploadedFile;
use Traversable;

final readonly class ThumbCollection implements Countable, IteratorAggregate, CollectionInterface
{

    /**
     * @param array<string, UploadedFile> $files
     */
    public function __construct(private array $files)
    {
    }

    /**
     * @throws FileManagerException
     */
    public function __get(string $name): UploadedFile
    {
        return $this->get($name);
    }

    /**
     * @throws FileManagerException
     */
    public function __call(string $name, array $arguments): UploadedFile
    {
        return $this->get($name);
    }

    /**
     * @throws FileManagerException
     */
    public function get(string $name): UploadedFile
    {
        if (array_key_exists($name, $this->files) === false) {
            throw FileManagerException::generic("The `$name` format is not found.");
        }

        return $this->files[$name];
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->files);
    }

    public function count(): int
    {
        return count($this->files);
    }

    /**
     * @return array<string, UploadedFile>
     */
    public function all(): array
    {
        return $this->files;
    }

    public function first(): ?UploadedFile
    {
        return $this->files[array_key_first($this->files)] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    /**
     * @return Traversable<string, UploadedFile>
     */
    public function getIterator(): Traversable
    {
        yield from $this->files;
    }
}
