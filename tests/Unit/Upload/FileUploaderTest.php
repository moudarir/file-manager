<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Upload;

use DateTimeImmutable;
use DateTimeInterface;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\File;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Upload\FileUploader;
use Moudarir\FileManager\Upload\UploadConfig;
use Moudarir\FileManager\Upload\UploadedFile;
use PHPUnit\Framework\TestCase;

final class FileUploaderTest extends TestCase
{
    private string $uploadPath;

    protected function setUp(): void
    {
        $this->uploadPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'file-manager-tests-'
            . bin2hex(random_bytes(8));

        self::assertTrue(mkdir($this->uploadPath, 0777, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->uploadPath);
    }

    public function testThrowsExceptionWhenCopyFilepathIsEmpty(): void
    {
        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The filepath of the file to copy is missing.');

        $uploader->upload('');
    }

    public function testThrowsExceptionWhenCopyFilepathDoesNotExist(): void
    {
        $uploader = new FileUploader($this->createUploadConfig());

        $filepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'missing.png';

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The filepath of the file to copy does not exist.');

        $uploader->upload($filepath);
    }

    public function testCopiesFileFromFilepath(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $config = $this->createUploadConfig($destinationPath, false);

        $uploader = new FileUploader($config);

        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);
        self::assertSame('file', $files->field());

        $file = $files->first();

        self::assertNotNull($file);
        self::assertTrue($file->isImage());
        self::assertFalse($file->converted());
        self::assertFileExists($file->filepath());
        self::assertSame(
            [
                'source.png',
                MimeType::PNG,
                'png',
                1,
                1,
                $sourceContent,
            ],
            [
                $file->originalName(),
                $file->mimeType(),
                $file->extension(),
                $file->imageWidth(),
                $file->imageHeight(),
                file_get_contents($file->filepath()),
            ]
        );
    }

    public function testThrowsExceptionWhenUploadExceedsIniSize(): void
    {
        $_FILES['file'] = [
            'name' => 'file.png',
            'full_path' => 'file.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/file.png',
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 100,
        ];

        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The uploaded file exceeds the maximum allowed size.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testThrowsExceptionWhenUploadExceedsFormSize(): void
    {
        $_FILES['file'] = [
            'name' => 'file.png',
            'full_path' => 'file.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/file.png',
            'error' => UPLOAD_ERR_FORM_SIZE,
            'size' => 100,
        ];

        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The uploaded file exceeds the maximum size allowed by the form.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testThrowsExceptionWhenUploadIsPartial(): void
    {
        $_FILES['file'] = [
            'name' => 'file.png',
            'full_path' => 'file.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/file.png',
            'error' => UPLOAD_ERR_PARTIAL,
            'size' => 100,
        ];

        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The uploaded file was only partially uploaded.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testThrowsExceptionWhenNoFileWasUploaded(): void
    {
        $_FILES['file'] = [
            'name' => '',
            'full_path' => '',
            'type' => '',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ];

        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('You have not selected a file to send.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testThrowsExceptionWhenTemporaryDirectoryIsMissing(): void
    {
        $_FILES['file'] = [
            'name' => 'file.png',
            'full_path' => 'file.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/file.png',
            'error' => UPLOAD_ERR_NO_TMP_DIR,
            'size' => 100,
        ];

        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The server temporary directory is missing.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testThrowsExceptionWhenUploadCannotBeWritten(): void
    {
        $_FILES['file'] = [
            'name' => 'file.png',
            'full_path' => 'file.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/file.png',
            'error' => UPLOAD_ERR_CANT_WRITE,
            'size' => 100,
        ];

        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('An error occurred while uploading the file.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testThrowsExceptionWhenUploadWasStoppedByExtension(): void
    {
        $_FILES['file'] = [
            'name' => 'file.png',
            'full_path' => 'file.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/file.png',
            'error' => UPLOAD_ERR_EXTENSION,
            'size' => 100,
        ];

        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The file upload was stopped by a PHP extension.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testThrowsGenericUploadExceptionForUnknownUploadError(): void
    {
        $_FILES['file'] = [
            'name' => 'file.png',
            'full_path' => 'file.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/file.png',
            'error' => 999,
            'size' => 100,
        ];

        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('An error occurred while uploading the file.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testCopiesFileWhenFilesizeIsEqualToMaximumFilesize(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $filesize = filesize($sourceFilepath);

        self::assertNotFalse($filesize);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $config = $this->createUploadConfig($destinationPath, false, $filesize);
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);
        self::assertNotNull($files->first());
        self::assertSame($filesize, $files->first()->filesize());
    }

    public function testCopiesFileWhenMaximumFilesizeIsZero(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $config = $this->createUploadConfig($destinationPath, false);
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);
        self::assertNotNull($files->first());
    }

    public function testThrowsExceptionWhenFileExceedsMaximumFilesize(): void
    {
        $_FILES['file'] = [
            'name' => 'file.png',
            'full_path' => 'file.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/file.png',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ];

        $uploader = new FileUploader($this->createUploadConfig(maxFilesize: 512));

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The uploaded file exceeds the maximum allowed size.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testThrowsExceptionWhenUploadedFileIsNotValidUpload(): void
    {
        $_FILES['file'] = [
            'name' => 'file.png',
            'full_path' => 'file.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/file.png',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ];

        $uploader = new FileUploader($this->createUploadConfig());

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('An error occurred while uploading the file.');

        try {
            $uploader->upload();
        } finally {
            unset($_FILES['file']);
        }
    }

    public function testCreatesUploadDirectoryWithoutDateFormat(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        self::assertDirectoryDoesNotExist($destinationPath);

        $config = $this->createUploadConfig($destinationPath, false);
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);
        self::assertDirectoryExists($destinationPath);
        self::assertFileExists($files->first()?->filepath());
    }

    public function testCreatesDateFormattedUploadDirectory(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $dateDirectory = new DateTimeImmutable()->format('Y/m');

        $expectedDirectory = $destinationPath . DIRECTORY_SEPARATOR . $dateDirectory;

        self::assertDirectoryDoesNotExist($expectedDirectory);

        $config = $this->createUploadConfig($destinationPath, false, dateFormat: 'Y/m');
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);
        self::assertDirectoryExists($expectedDirectory);
        self::assertFileExists($files->first()?->filepath());
    }

    public function testUsesSanitizedOriginalFilenameWhenEncryptionIsDisabled(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'My test file.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $config = $this->createUploadConfig($destinationPath, false);
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertSame('My-test-file.png', $file->basename());
    }

    public function testGeneratesEncryptedFilenameWhenEncryptionIsEnabled(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $config = $this->createUploadConfig($destinationPath, true);
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertSame(40, strlen($file->filename()));
        self::assertSame('png', $file->extension());
        self::assertNotSame('source', $file->filename());
    }

    public function testUsesDestinationBasenameWhenFileDoesNotExist(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $config = $this->createUploadConfig($destinationPath, false, overwrite: false);
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertSame('source.png', $file->basename());
    }

    public function testUsesExistingDestinationBasenameWhenOverwriteIsEnabled(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $existingFilepath = $destinationPath . DIRECTORY_SEPARATOR . 'source.png';

        self::assertTrue(mkdir($destinationPath, 0777, true));
        self::assertTrue(file_put_contents($existingFilepath, 'old content') !== false);

        $config = $this->createUploadConfig($destinationPath, false, overwrite: true);
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertSame('source.png', $file->basename());
        self::assertSame($sourceContent, file_get_contents($file->filepath()));
    }

    public function testGeneratesNewDestinationBasenameWhenOverwriteIsDisabled(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $existingFilepath = $destinationPath . DIRECTORY_SEPARATOR . 'source.png';

        self::assertTrue(mkdir($destinationPath, 0777, true));
        self::assertTrue(file_put_contents($existingFilepath, 'old content') !== false);

        $config = $this->createUploadConfig($destinationPath, false, overwrite: false);
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertNotSame('source.png', $file->basename());
        self::assertSame('old content', file_get_contents($existingFilepath));
        self::assertSame($sourceContent, file_get_contents($file->filepath()));
    }

    public function testGeneratesEncryptedDestinationBasenameWhenOverwriteIsDisabled(): void
    {
        $sourceFilepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'source.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        self::assertNotFalse($sourceContent);
        self::assertTrue(file_put_contents($sourceFilepath, $sourceContent) !== false);

        $destinationPath = $this->uploadPath . DIRECTORY_SEPARATOR . 'destination';

        $existingFilepath = $destinationPath . DIRECTORY_SEPARATOR . 'source.png';

        self::assertTrue(mkdir($destinationPath, 0777, true));
        self::assertTrue(file_put_contents($existingFilepath, 'old content') !== false);

        $config = $this->createUploadConfig($destinationPath, true, overwrite: false);
        $uploader = new FileUploader($config);
        $files = $uploader->upload($sourceFilepath);

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertNotSame('source.png', $file->basename());
        self::assertSame(
            [
                40,
                'png',
                'old content',
                $sourceContent,
            ],
            [
                strlen($file->filename()),
                $file->extension(),
                file_get_contents($existingFilepath),
                file_get_contents($file->filepath()),
            ]
        );
    }

    public function testAcceptsFileWhenMimeTypeIsAllowed(): void
    {
        $filepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'image.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        file_put_contents($filepath, $sourceContent);

        $_FILES = [
            'file' => [
                'name' => 'image.png',
                'full_path' => 'image.png',
                'type' => 'image/png',
                'tmp_name' => $filepath,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($filepath),
            ],
        ];

        $config = $this->createUploadConfig(allowedMimeTypes: [MimeType::PNG]);
        $files = new FileUploader($config)->upload($filepath);

        self::assertCount(1, $files);
        self::assertSame(MimeType::PNG, $files->first()?->mimeType());
    }

    public function testThrowsExceptionWhenMimeTypeIsNotAllowed(): void
    {
        $filepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'image.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        file_put_contents($filepath, $sourceContent);

        $config = $this->createUploadConfig(allowedMimeTypes: [MimeType::JPEG]);

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The type of file you are trying to send is not allowed.');

        new FileUploader($config)->upload($filepath);
    }

    public function testAcceptsImageWhenDimensionsAreWithinLimits(): void
    {
        $filepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'image.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        file_put_contents($filepath, $sourceContent);

        $config = $this->createUploadConfig(
            maxImageWidth: 1,
            maxImageHeight: 1,
            minImageWidth: 1,
            minImageHeight: 1,
        );

        $files = new FileUploader($config)->upload($filepath);
        $file = $files->first();

        self::assertNotNull($file);
        self::assertSame([1, 1], [$file->imageWidth(), $file->imageHeight()]);
    }

    public function testThrowsExceptionWhenImageWidthIsBelowMinimum(): void
    {
        $filepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'image.png';

        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        file_put_contents($filepath, $sourceContent);

        $config = $this->createUploadConfig(minImageWidth: 2);

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The image dimensions are invalid.');

        new FileUploader($config)->upload($filepath);
    }

    public function testThrowsExceptionWhenImageWidthExceedsMaximum(): void
    {
        $filepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'image.png';

        $this->createPngFile($filepath, 2, 1);

        $config = $this->createUploadConfig(maxImageWidth: 1);

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The image dimensions are invalid.');

        new FileUploader($config)->upload($filepath);
    }

    public function testThrowsExceptionWhenImageHeightExceedsMaximum(): void
    {
        $filepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'image.png';

        $this->createPngFile($filepath, 1, 2);

        $config = $this->createUploadConfig(maxImageHeight: 1);

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The image dimensions are invalid.');

        new FileUploader($config)->upload($filepath);
    }

    public function testAcceptsNonImageFileWithoutImageDimensionValidation(): void
    {
        $filepath = $this->uploadPath . DIRECTORY_SEPARATOR . 'file.txt';

        file_put_contents($filepath, 'This is a text file.');

        $config = $this->createUploadConfig(encryptName: false);
        $files = new FileUploader($config)->upload($filepath);
        $file = $files->first();

        self::assertNotNull($file);
        self::assertFalse($file->isImage());
    }

    public function testUploadedFileExposesFileMetadata(): void
    {
        $sourceDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'file-manager-source-'
            . bin2hex(random_bytes(8));

        self::assertTrue(mkdir($sourceDirectory, 0777, true));

        $filepath = $sourceDirectory . DIRECTORY_SEPARATOR . 'image.png';

        self::assertNotFalse($filepath);

        try {
            $sourceContent = base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true,
            );

            file_put_contents($filepath, $sourceContent);

            $config = $this->createUploadConfig(encryptName: false);
            $files = new FileUploader($config)->upload($filepath);
            $file = $files->first();

            self::assertNotNull($file);
            self::assertTrue($file->isImage());
            self::assertSame(
                [
                    'image.png',
                    $this->uploadPath,
                    $this->uploadPath . DIRECTORY_SEPARATOR . 'image.png',
                    filesize($filepath),
                    'image',
                    'image.png',
                    'png',
                    MimeType::PNG,
                    MimeType::PNG->value,
                ],
                [
                    $file->originalName(),
                    $file->dirname(),
                    $file->filepath(),
                    $file->filesize(),
                    $file->filename(),
                    $file->basename(),
                    $file->extension(),
                    $file->mimeType(),
                    $file->mimeTypeValue(),
                ]
            );

            self::assertSame(
                [
                    'width' => 1,
                    'height' => 1,
                    'htmlAttributes' => 'width="1" height="1"',
                ],
                $file->imageDimensions(),
            );
            self::assertSame([1, 1], [$file->imageWidth(), $file->imageHeight()]);

            self::assertInstanceOf(DateTimeImmutable::class, $file->createdAt());
            //self::assertNotSame('', $file->createdAt());
            self::assertFalse($file->converted());
        } finally {
            self::assertTrue(unlink($filepath));
            self::assertTrue(rmdir($sourceDirectory));
        }
    }

    public function testCanMarkUploadedFileAsConverted(): void
    {
        $sourceDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'file-manager-source-'
            . bin2hex(random_bytes(8));

        self::assertTrue(mkdir($sourceDirectory, 0777, true));

        $filepath = $sourceDirectory . DIRECTORY_SEPARATOR . 'image.png';
        $sourceContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        file_put_contents($filepath, $sourceContent);

        $file = UploadedFile::create(
            File::create($filepath)->resource(),
            MimeType::PNG,
            'image.png',
            new DateTimeImmutable(),
            [
                'width' => 1,
                'height' => 1,
                'htmlAttributes' => 'width="1" height="1"',
            ],
        );

        self::assertFalse($file->converted());

        $file->markAsConverted();

        self::assertTrue($file->converted());

        self::assertTrue(unlink($filepath));
        self::assertTrue(rmdir($sourceDirectory));
    }

    private function createUploadConfig(
        ?string            $uploadPath = null,
        ?bool              $encryptName = null,
        int                $maxFilesize = 0,
        ?string            $dateFormat = null,
        ?DateTimeInterface $customDate = null,
        ?bool              $overwrite = null,
        array              $allowedMimeTypes = [],
        int                $maxImageWidth = 0,
        int                $maxImageHeight = 0,
        int                $minImageWidth = 0,
        int                $minImageHeight = 0,
    ): UploadConfig {
        return UploadConfig::create([
            'field' => 'file',
            'uploadPath' => $uploadPath ?? $this->uploadPath,
            'dateFormat' => $dateFormat,
            'customDate' => $customDate,
            'maxFilesize' => $maxFilesize,
            'maxImageWidth' => $maxImageWidth,
            'maxImageHeight' => $maxImageHeight,
            'minImageWidth' => $minImageWidth,
            'minImageHeight' => $minImageHeight,
            'maxUploadedFiles' => null,
            'allowedMimeTypes' => $allowedMimeTypes,
            'overwrite' => $overwrite ?? false,
            'encryptName' => $encryptName ?? true,
        ]);
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

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path) === true) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }

    private function createPngFile(string $filepath, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);

        self::assertNotFalse($image);
        self::assertTrue(imagepng($image, $filepath));

        imagedestroy($image);
    }
}
