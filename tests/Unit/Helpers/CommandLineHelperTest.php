<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Helpers;

use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\CommandLineHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandLineHelperTest extends TestCase
{

    private ?string $temporaryDirectory = null;

    protected function tearDown(): void
    {
        if ($this->temporaryDirectory !== null) {
            $this->removeDirectory($this->temporaryDirectory);
            $this->temporaryDirectory = null;
        }

        parent::tearDown();
    }

    #[Test]
    public function itBuildsAnImageMagickCommand(): void
    {
        $source = $this->temporaryPath('source image.jpg');
        $destination = $this->temporaryPath('destination image.webp');

        $command = CommandLineHelper::buildImageMagickCommand(
            $source,
            'webp:'.$destination,
            [
                "-quality '75'",
                '-strip',
                '-define webp:alpha-quality=90',
                '-define webp:method=5',
            ],
            false
        );

        $executable = CommandLineHelper::executablePath(
            PHP_OS_FAMILY === 'Windows' ? 'magick.exe' : 'convert',
            PHP_OS_FAMILY === 'Windows' ? 'convert.exe' : 'magick'
        );

        $expectedExecutable = escapeshellarg($executable);
        $expectedSource = escapeshellarg($source);
        $expectedDestination = escapeshellarg('webp:'.$destination);

        if (strtolower(pathinfo($executable, PATHINFO_FILENAME)) === 'magick') {
            $expected = $expectedExecutable
                . ' '.$expectedSource
                . " -quality '75'"
                . ' -strip'
                . ' -define webp:alpha-quality=90'
                . ' -define webp:method=5'
                . ' '.$expectedDestination
                . ' 2>&1';
        } else {
            $expected = $expectedExecutable
                . " -quality '75'"
                . ' -strip'
                . ' -define webp:alpha-quality=90'
                . ' -define webp:method=5'
                . ' '.$expectedSource
                . ' '.$expectedDestination
                . ' 2>&1';
        }

        self::assertSame($expected, $command);
    }

    #[Test]
    public function itBuildsAnImageMagickCommandWithNicePrefix(): void
    {
        $source = $this->temporaryPath('source.jpg');
        $destination = $this->temporaryPath('destination.webp');

        $command = CommandLineHelper::buildImageMagickCommand(
            $source,
            $destination,
            [],
            true
        );

        if (PHP_OS_FAMILY === 'Windows') {
            self::assertStringStartsWith(
                escapeshellarg(
                    CommandLineHelper::executablePath('magick.exe', 'convert.exe')
                ),
                $command
            );

            return;
        }

        self::assertStringStartsWith('nice ', $command);
    }

    #[Test]
    public function itExecutesACommand(): void
    {
        $command = PHP_OS_FAMILY === 'Windows'
            ? 'echo test'
            : 'printf test';

        self::assertTrue(CommandLineHelper::executeCommand($command));
    }

    #[Test]
    public function itReturnsFalseWhenExecutingAnEmptyCommand(): void
    {
        self::assertFalse(CommandLineHelper::executeCommand(''));
        self::assertFalse(CommandLineHelper::executeCommand('   '));
    }

    #[Test]
    public function itReturnsFalseWhenCommandExecutionFails(): void
    {
        self::assertFalse(
            CommandLineHelper::executeCommand(
                PHP_OS_FAMILY === 'Windows'
                    ? 'exit 1'
                    : 'false'
            )
        );
    }

    #[Test]
    public function itFindsAnExecutableFromPath(): void
    {
        $executable = PHP_OS_FAMILY === 'Windows'
            ? 'php.exe'
            : 'php';

        $path = CommandLineHelper::executablePath($executable);

        self::assertFileExists($path);
        self::assertTrue(is_executable($path));
    }

    #[Test]
    public function itUsesTheFallbackExecutable(): void
    {
        $executable = PHP_OS_FAMILY === 'Windows'
            ? 'php.exe'
            : 'php';

        $path = CommandLineHelper::executablePath(
            'moudarir-file-manager-non-existent-executable',
            $executable
        );

        self::assertFileExists($path);
        self::assertTrue(is_executable($path));
    }

    #[Test]
    public function itThrowsAnExceptionWhenExecutableIsNotFound(): void
    {
        $this->expectException(FileManagerException::class);

        CommandLineHelper::executablePath('moudarir-file-manager-non-existent-executable');
    }

    #[Test]
    public function itExecutesAnImageMagickConversionWithPathsContainingSpaces(): void
    {
        $directory = $this->createTemporaryDirectory('ImageMagick Test');

        $source = $directory.DIRECTORY_SEPARATOR.'source image.ppm';
        $destination = $directory.DIRECTORY_SEPARATOR.'destination image.webp';

        $ppm = implode("\n", [
            'P3',
            '2 2',
            '255',
            '255 0 0 0 255 0',
            '0 0 255 255 255 255',
            '',
        ]);

        file_put_contents($source, $ppm);

        $command = CommandLineHelper::buildImageMagickCommand(
            $source,
            'webp:'.$destination,
            [
                "-quality '75'",
                '-strip',
                '-define webp:alpha-quality=90',
                '-define webp:method=5',
            ]
        );

        self::assertTrue(
            CommandLineHelper::executeCommand($command),
            'ImageMagick command failed: '.$command
        );

        self::assertFileExists($destination);
        self::assertGreaterThan(0, filesize($destination));
    }

    #[Test]
    public function itExecutesAnImageMagickConversionWithOnlyStripArg(): void
    {
        $directory = $this->createTemporaryDirectory('ImageMagick Test');

        $source = $directory.DIRECTORY_SEPARATOR.'source image.ppm';
        $destination = $directory.DIRECTORY_SEPARATOR.'destination image.webp';

        $ppm = implode("\n", [
            'P3',
            '2 2',
            '255',
            '255 0 0 0 255 0',
            '0 0 255 255 255 255',
            '',
        ]);

        file_put_contents($source, $ppm);

        $command = CommandLineHelper::buildImageMagickCommand(
            $source,
            'webp:'.$destination,
            [
                '-strip',
            ]
        );

        self::assertTrue(
            CommandLineHelper::executeCommand($command),
            'ImageMagick command failed: '.$command
        );

        self::assertFileExists($destination);
        self::assertGreaterThan(0, filesize($destination));
    }

    #[Test]
    public function itExecutesAnImageMagickConversionWithQualityArg(): void
    {
        $directory = $this->createTemporaryDirectory('ImageMagick Test');

        $source = $directory.DIRECTORY_SEPARATOR.'source image.ppm';
        $destination = $directory.DIRECTORY_SEPARATOR.'destination image.webp';

        $ppm = implode("\n", [
            'P3',
            '2 2',
            '255',
            '255 0 0 0 255 0',
            '0 0 255 255 255 255',
            '',
        ]);

        file_put_contents($source, $ppm);

        $command = CommandLineHelper::buildImageMagickCommand(
            $source,
            'webp:'.$destination,
            [
                "-quality '75'",
            ]
        );

        self::assertTrue(
            CommandLineHelper::executeCommand($command),
            'ImageMagick command failed: '.$command
        );

        self::assertFileExists($destination);
        self::assertGreaterThan(0, filesize($destination));
    }

    private function temporaryPath(string $filename): string
    {
        return $this->createTemporaryDirectory().DIRECTORY_SEPARATOR.$filename;
    }

    private function createTemporaryDirectory(string $suffix = ''): string
    {
        if ($this->temporaryDirectory === null) {
            $this->temporaryDirectory = sys_get_temp_dir()
                .DIRECTORY_SEPARATOR
                .'moudarir-file-manager-'
                .uniqid('', true);
        }

        $directory = $this->temporaryDirectory;

        if ($suffix !== '') {
            $directory .= DIRECTORY_SEPARATOR.$suffix;
        }

        if (is_dir($directory) === false) {
            mkdir($directory, 0777, true);
        }

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (is_dir($directory) === false) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path) === true) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
