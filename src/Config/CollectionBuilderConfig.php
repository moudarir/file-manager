<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Config;

use DateTimeInterface;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\ValidateParam;

final readonly class CollectionBuilderConfig
{

    private function __construct(public string $field, public ?DateTimeInterface $customDate = null)
    {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(array $config): self
    {
        $customDate = $config['customDate'] ?? null;
        ValidateParam::customDate($customDate);

        return new self($config['field'], $customDate);
    }
}
