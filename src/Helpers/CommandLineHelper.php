<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Helpers;

use Moudarir\FileManager\Exceptions\FileManagerException;

final class CommandLineHelper
{

    private static ?string $imageMagickExecutablePath = null;

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
        $path = self::findExecutable($executable);

        if ($path !== null) {
            return $path;
        }

        if ($fallback !== null && $fallback !== '') {
            $path = self::findExecutable($fallback);

            if ($path !== null) {
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
        if (self::$imageMagickExecutablePath !== null) {
            return self::$imageMagickExecutablePath;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return self::$imageMagickExecutablePath = self::executablePath(
                'magick.exe',
                'convert.exe'
            );
        }

        return self::$imageMagickExecutablePath = self::executablePath('convert', 'magick');
    }

    private static function nicePrefix(): string
    {
        return self::$nicePrefix ??= (PHP_OS_FAMILY === 'Windows' ? '' : 'nice ');
    }

    private static function findExecutable(string $executable): ?string
    {
        foreach (self::executableDirectories() as $directory) {
            $path = $directory.DIRECTORY_SEPARATOR.$executable;

            if (is_executable($path) === true) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function executableDirectories(): array
    {
        $directories = [];

        $path = getenv('PATH');

        if ($path !== false && $path !== '') {
            $directories = explode(PATH_SEPARATOR, $path);
        }

        $directories = array_merge(
            $directories,
            self::defaultExecutableDirectories()
        );

        return array_values(
            array_unique(
                array_filter(
                    $directories,
                    static fn (string $directory): bool => $directory !== ''
                )
            )
        );
    }

    /**
     * @return list<string>
     */
    private static function defaultExecutableDirectories(): array
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => [
                '/opt/homebrew/bin',
                '/usr/local/bin',
                '/opt/local/bin',
            ],
            'Linux' => [
                '/usr/local/bin',
                '/usr/bin',
                '/bin',
                '/snap/bin',
            ],
            //'Windows' => [],
            default => [],
        };
    }
}
