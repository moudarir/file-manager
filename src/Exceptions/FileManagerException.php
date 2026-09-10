<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Exceptions;

use Exception;
use Throwable;

class FileManagerException extends Exception
{

    public static function uploadRequestMandatory(): self
    {
        return new self("The `upload()` method is mandatory to perform...");
    }

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

    public static function invalidPropertyValue(string $property): static
    {
        return new static("The value of the property `$property` is invalid.");
    }

    public static function unableReadPictureSource(): self
    {
        return new self("Unable to read the image source.");
    }

    public static function errorCroppingImage(): self
    {
        return new self("Error occurred while cropping the image.");
    }

    public static function errorSavingImage(): self
    {
        return new self("Error occurred while saving the image.");
    }

    public static function executablePathNotFound(string $executable, ?string $fallback = null): self
    {
        $command = "`$executable`".($fallback === null ? '' : " or `$fallback`");
        $path = $fallback === null ? 'path is' : "paths are";
        return new self("The $command executable $path not found.");
    }

    public static function imageResizeFailed(): self
    {
        return new self("Image resizing failed. Please make sure that your server supports the `command-line` execution.");
    }

    public static function imageConvertFailed(): self
    {
        return new self("Image conversion failed. Please make sure that your server supports the `command-line` execution.");
    }

    public static function fileNotExists(string $filepath): self
    {
        return new self(sprintf("The file `%s` does not exist.", $filepath));
    }

    public static function gdLibRequired(): self
    {
        return new self("Your server must support the GD image library in order to determine the image properties.");
    }

    public static function invalidImage(): self
    {
        return new self("The provided image is invalid.");
    }

    public static function unsupportedImageType(string $type): self
    {
        return new self(sprintf("Images of type `%s` are not supported.", $type));
    }

    public static function unsupportedImageCreate(?string $type = null): self
    {
        $message = "Your server does not support the GD functions required to process this type of image.";

        if ($type !== null) {
            $type = strtoupper($type);
            $message .= " Images of type `$type` are not supported.";
        }

        return new self($message);
    }

    public static function failToSaveImage(): self
    {
        return new self("Unable to save the image. Please ensure that the image and the directory are writable.");
    }

    public static function generic(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
