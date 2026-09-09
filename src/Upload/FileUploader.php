<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Upload;

use DateTimeImmutable;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\Exceptions\FileResourceException;
use Moudarir\File\Exceptions\MimeDetectionException;
use Moudarir\File\File;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\Common;
use Moudarir\Helpers\EncryptionHelper;
use Moudarir\Helpers\FileHelper;
use Moudarir\Helpers\SanitizeHelper;
use Random\RandomException;

final readonly class FileUploader
{

    public function __construct(private UploadConfig $config)
    {
    }

    /**
     * @throws FileManagerException
     */
    public function upload(?string $filepath = null): UploadedFileCollection
    {
        $files = $this->normalize($filepath);

        $uploadedFiles = [];

        foreach ($files as $file) {
            $uploadedFiles[] = $this->process($file);
        }

        return new UploadedFileCollection($this->config->field, $uploadedFiles);
    }

    /**
     * @throws FileManagerException
     */
    private function process(array $file): UploadedFile
    {
        try {
            $this->validateUploadError($file);
            $this->validateFilesize($file);
            $this->validateFileSource($file);

            $date = new DateTimeImmutable();
            $directory = Common::makeDirectory($this->config->uploadPath, $date, $this->config->dateFormat);
            $filename = $this->getDestinationFilename($file['name']);

            $sourceFile = File::create($file['tmp_name']);
            $resource = $sourceFile->resource();
            $detection = $sourceFile->detection();
            $mimeType = $detection->mimeType();

            $basename = $this->getDestinationBasename(
                $directory,
                $filename,
                $resource->extension()
            );

            if ($this->isAllowedMimeType($mimeType) === false) {
                throw FileManagerException::invalidFiletype();
            }

            $dimensions = $this->validateImageDimensions($file['tmp_name'], $mimeType);

            $destinationFilepath = $directory . $basename;

            $this->saveToDestination($file, $destinationFilepath);

            $destinationFile = File::create($destinationFilepath, $mimeType);

            return UploadedFile::create(
                $destinationFile->resource(),
                $mimeType,
                $file['name'],
                $date,
                $dimensions,
            );
        } catch (FileResourceException|MimeDetectionException $exception) {
            throw FileManagerException::generic($exception->getMessage(), $exception);
        }
    }

    /**
     * @param MimeType $mimeType
     * @return bool
     */
    private function isAllowedMimeType(MimeType $mimeType): bool
    {
        if ($this->config->allowedMimeTypes === []) {
            return true;
        }

        return in_array($mimeType, $this->config->allowedMimeTypes, true);
    }

    /**
     * @throws FileManagerException
     */
    private function normalize(?string $filepath = null): array
    {
        if ($filepath !== null) {
            if ($filepath === '') {
                throw FileManagerException::missingCopyFilepath();
            }

            if (is_file($filepath) === false) {
                throw FileManagerException::copyFilepathNotFound();
            }

            return [
                [
                    'name' => basename($filepath),
                    'full_path' => $filepath,
                    'type' => '',
                    'tmp_name' => $filepath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => @filesize($filepath) ?: 0,
                    'onlyCopy' => true,
                ],
            ];
        }

        $file = $this->getUploadedFile();

        if ($file === null) {
            throw FileManagerException::noFileSelected();
        }

        if (is_array($file['name'] ?? null) === true) {
            return $this->normalizeMultiple($file, $this->config->maxUploadedFiles);
        }

        return [$this->normalizeSingle($file)];
    }

    private function normalizeSingle(array $file): array
    {
        return [
            'name' => $file['name'] ?? '',
            'full_path' => $file['full_path'] ?? '',
            'type' => $file['type'] ?? '',
            'tmp_name' => $file['tmp_name'] ?? '',
            'error' => $file['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $file['size'] ?? 0,
            'onlyCopy' => false,
        ];
    }

    private function normalizeMultiple(array $files, ?int $maxUploadedFiles): array
    {
        $normalized = [];
        $count = 0;

        foreach ($files['name'] ?? [] as $index => $name) {
            if ($maxUploadedFiles !== null && $count >= $maxUploadedFiles) {
                break;
            }

            $normalized[] = [
                'name' => $name,
                'full_path' => $files['full_path'][$index] ?? '',
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
                'onlyCopy' => false,
            ];

            $count++;
        }

        return $normalized;
    }

    private function getUploadedFile(): ?array
    {
        if (array_key_exists($this->config->field, $_FILES) === true) {
            return $_FILES[$this->config->field];
        }

        $matches = [];

        if (preg_match_all('/(?:^[^\[]+)|\[[^]]*]/', $this->config->field, $matches) <= 1) {
            return null;
        }

        $file = $_FILES;

        foreach ($matches[0] as $match) {
            $field = trim($match, '[]');

            if ($field === '' || array_key_exists($field, $file) === false) {
                return null;
            }

            $file = $file[$field];
        }

        return is_array($file) === true ? $file : null;
    }

    /**
     * @throws FileManagerException
     */
    private function validateUploadError(array $file): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        switch ($error) {
            case UPLOAD_ERR_OK:
                return;
            case UPLOAD_ERR_INI_SIZE:
                throw FileManagerException::fileExceedsLimit();
            case UPLOAD_ERR_FORM_SIZE:
                throw FileManagerException::fileExceedsFormLimit();
            case UPLOAD_ERR_PARTIAL:
                throw FileManagerException::filePartiallySent();
            case UPLOAD_ERR_NO_FILE:
                throw FileManagerException::noFileSelected();
            case UPLOAD_ERR_NO_TMP_DIR:
                throw FileManagerException::noTempDirectory();
            case UPLOAD_ERR_CANT_WRITE:
                throw FileManagerException::uploadFailed();
            case UPLOAD_ERR_EXTENSION:
                throw FileManagerException::stoppedByExtension();
            default:
                throw FileManagerException::uploadFailed();
        }
    }

    /**
     * @throws FileManagerException
     */
    private function validateFilesize(array $file): void
    {
        if ($this->config->maxFilesize > 0 && (int) $file['size'] > $this->config->maxFilesize) {
            throw FileManagerException::fileExceedsLimit();
        }
    }

    /**
     * @throws FileManagerException
     */
    private function validateImageDimensions(string $filepath, MimeType $mimeType): ?array
    {
        if ($mimeType->isImage() === false) {
            return null;
        }

        if (function_exists('getimagesize') === false) {
            return null;
        }

        if (($dimensions = getimagesize($filepath)) === false) {
            throw FileManagerException::invalidDimensions();
        }

        if ($this->config->maxImageWidth > 0 && $dimensions[0] > $this->config->maxImageWidth) {
            throw FileManagerException::invalidDimensions();
        }

        if ($this->config->maxImageHeight > 0 && $dimensions[1] > $this->config->maxImageHeight) {
            throw FileManagerException::invalidDimensions();
        }

        if ($this->config->minImageWidth > 0 && $dimensions[0] < $this->config->minImageWidth) {
            throw FileManagerException::invalidDimensions();
        }

        if ($this->config->minImageHeight > 0 && $dimensions[1] < $this->config->minImageHeight) {
            throw FileManagerException::invalidDimensions();
        }

        return [
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'htmlAttributes' => $dimensions[3],
        ];
    }

    /**
     * @throws FileManagerException
     */
    private function validateFileSource(array $file): void
    {
        if ($file['onlyCopy'] === true) {
            return;
        }

        if (is_uploaded_file($file['tmp_name']) === false) {
            throw FileManagerException::uploadFailed();
        }
    }

    /**
     * @throws FileManagerException
     */
    private function getDestinationFilename(string $basename): string
    {
        if ($this->config->encryptName === false) {
            if (($filename = pathinfo($basename, PATHINFO_FILENAME)) === '') {
                return '';
            }

            return SanitizeHelper::urlTitle($filename);
        }

        try {
            return EncryptionHelper::generateToken(40);
        } catch (RandomException $exception) {
            throw FileManagerException::generic($exception->getMessage(), $exception);
        }
    }

    /**
     * @throws FileManagerException
     */
    private function getDestinationBasename(string $directory, string $filename, string $extension): string
    {
        $basename = $filename . ($extension !== '' ? '.'.$extension : '');

        if ($this->config->overwrite === true || is_file($directory . $basename) === false) {
            return $basename;
        }

        try {
            return FileHelper::newFilename(
                $directory.$basename,
                40,
                $this->config->encryptName
            )['file_name'];
        } catch (RandomException $exception) {
            throw FileManagerException::generic($exception->getMessage(), $exception);
        }
    }

    /**
     * @throws FileManagerException
     */
    private function saveToDestination(array $file, string $destinationFilepath): void
    {
        if ($file['onlyCopy'] === true) {
            $success = copy($file['tmp_name'], $destinationFilepath);
        } else {
            $success = move_uploaded_file($file['tmp_name'], $destinationFilepath);
        }

        if ($success === false) {
            throw FileManagerException::errorOnSavingToDestination($file['onlyCopy']);
        }
    }
}
