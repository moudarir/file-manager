<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Exceptions;

use Exception;
use Throwable;

class FileManagerException extends Exception
{

    public static function missingConfig(?string $module = null): self
    {
        if ($module === null) {
            return new self("Configuration is mandatory.");
        }

        return new self(sprintf("The configuration of `%s` module is missing.", $module));
    }

    public static function missingParam(string $param): self
    {
        return new self(sprintf("The parameter `%s` is mandatory.", $param));
    }

    public static function missingCopyFilepath(): self
    {
        return new self("The filepath of the file to copy is missing.");
    }

    public static function copyFilepathNotFound(): self
    {
        return new self("The filepath of the file to copy does not exist.");
    }

    public static function noFileSelected(): self
    {
        return new self("You have not selected a file to send.");
    }

    public static function fileExceedsLimit(): self
    {
        return new self("The uploaded file exceeds the maximum allowed size.");
    }

    public static function fileExceedsFormLimit(): self
    {
        return new self("The uploaded file exceeds the maximum size allowed by the form.");
    }

    public static function filePartiallySent(): self
    {
        return new self("The uploaded file was only partially uploaded.");
    }

    public static function noTempDirectory(): self
    {
        return new self("The server temporary directory is missing.");
    }

    public static function stoppedByExtension(): self
    {
        return new self("The file upload was stopped by a PHP extension.");
    }

    public static function uploadFailed(): self
    {
        return new self("An error occurred while uploading the file.");
    }

    public static function invalidFiletype(): self
    {
        return new self("The type of file you are trying to send is not allowed.");
    }

    public static function invalidDimensions(): self
    {
        return new self("The image dimensions are invalid.");
    }

    public static function errorOnSavingToDestination(bool $onlyCopy = false): self
    {
        $message = $onlyCopy === false
            ? "An error occurred while moving the uploaded file to its final destination."
            : "An error occurred while copying the file to its final destination.";
        return new self($message);
    }

    public static function unableCreateFilepath(): self
    {
        return new self("Unable to create the upload directory path.");
    }

    public static function invalidDestinationPath(): self
    {
        return new self("The destination path appears to be invalid.");
    }

    public static function generic(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }

    public static function unableReadPictureSource(): static
    {
        return new static("Unable to read the image source.");
    }

    public static function errorCroppingImage(): static
    {
        return new static("Error occurred while cropping the image.");
    }

    public static function errorSavingImage(): static
    {
        return new static("Error occurred while saving the image.");
    }
}
