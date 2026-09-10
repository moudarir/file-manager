<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Helpers;

use Moudarir\FileManager\Exceptions\FileManagerException;

final class CommandLineHelper
{

    private static ?string $imageMagickExecutablePath = null;

    private static ?string $executableSearchCommand = null;

    private static ?string $nicePrefix = null;

    /**
     * @throws FileManagerException
     */
    public static function buildImageMagickCommand(
        string $source,
        string $destination,
        array $args = [],
        bool $withNicePrefix = true,
    ): string
    {
        $executable = self::imageMagickExecutablePath();
        $executableName = basename($executable);
        $source = escapeshellarg($source);

        if ($executableName === 'magick') {
            $sourceAtBeginning = " " . $source;
            $sourceAtEnd = "";
        } else {
            $sourceAtBeginning = "";
            $sourceAtEnd = " " . $source;
        }

        $command = ($withNicePrefix === true ? self::nicePrefix() : '')
            . escapeshellarg($executable)
            . $sourceAtBeginning;

        if ($args !== []) {
            $command .= " " . implode(" ", $args);
        }

        $command .= $sourceAtEnd
            . " " . escapeshellarg($destination)
            . ' 2>&1';

        return $command;
    }

    public static function executeCommand(string $command): bool
    {
        $command = trim($command);
        if ($command === '') {
            return false;
        }

        $code = 1;

        // exec() might be disabled
        if (function_exists('exec')) {
            exec($command, $output, $code);
        }

        // Did it work?
        return !($code !== 0);
    }

    /**
     * @throws FileManagerException
     */
    public static function executablePath(string $executable, ?string $fallback = null): string
    {
        $searchCommand = self::executableSearchCommand();
        $path = trim((string)shell_exec($searchCommand.$executable));

        if ($path !== '' && is_executable($path) === true) {
            return $path;
        }

        if ($fallback !== null && $fallback !== '') {
            $path = trim((string)shell_exec($searchCommand.$fallback));

            if ($path !== '' && is_executable($path) === true) {
                return $path;
            }
        }

        throw FileManagerException::executablePathNotFound($executable, $fallback);
    }

    /**
     * @throws FileManagerException
     */
    private static function imageMagickExecutablePath(): string
    {
        return self::$imageMagickExecutablePath ??= self::executablePath('convert', 'magick');
    }

    private static function executableSearchCommand(): string
    {
        return self::$executableSearchCommand ??= (PHP_OS_FAMILY === 'Windows' ? 'where ' : 'command -v ');
    }

    private static function nicePrefix(): string
    {
        return self::$nicePrefix ??= (PHP_OS_FAMILY === 'Windows' ? '' : 'nice ');
    }
}
