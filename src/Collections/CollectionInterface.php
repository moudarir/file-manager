<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Collections;

use Moudarir\FileManager\Upload\UploadedFile;
use Traversable;

interface CollectionInterface
{

    public function count(): int;

    public function isEmpty(): bool;

    /**
     * @return array<string, UploadedFile>
     */
    public function all(): array;

    public function first(): ?UploadedFile;

    /**
     * @return Traversable<string, UploadedFile>
     */
    public function getIterator(): Traversable;
}
