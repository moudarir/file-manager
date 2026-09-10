<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Helpers;

use DateTimeImmutable;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\Helpers\DirectoryHelper;

final class Common
{

    /**
     * @throws FileManagerException
     */
    public static function makeDirectory(
        string $path,
        DateTimeImmutable $date,
        ?string $dateFormat = null,
        ?string $extraPath = null,
    ): string
    {
        $directory = self::buildDirectoryPath($path, $date, $dateFormat, $extraPath);

        if (DirectoryHelper::create($directory) === false) {
            throw FileManagerException::unableCreateFilepath();
        }

        if (is_dir($directory) === false) {
            throw FileManagerException::invalidDestinationPath();
        }

        return $directory;
    }

    public static function buildDirectoryPath(
        string $path,
        DateTimeImmutable $date,
        ?string $dateFormat = null,
        ?string $extraPath = null,
    ): string
    {
        $directory = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($dateFormat !== null && $dateFormat !== '') {
            $directory .= $date->format(
                rtrim($dateFormat, DIRECTORY_SEPARATOR)
            ) . DIRECTORY_SEPARATOR;
        }

        if ($extraPath !== null && $extraPath !== '') {
            $directory .= rtrim($extraPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return $directory;
    }
}
