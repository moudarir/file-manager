# Changelog

All notable changes to this project will be documented in this file.

## [1.3.0] - 2026-09-15

### Added

* Add `CollectionInterface` as the common interface for file collections.
* Add `CollectionBuilder` to build an `UploadedFileCollection` from existing files on the filesystem.
* Add `FileManager::buildUploadedFileCollection()` to process existing files without requiring an upload.
* Add support for optional file metadata when building an uploaded file collection:

    * MIME type
    * creation date
    * image dimensions
* Add silent mode to `buildUploadedFileCollection()` to ignore files that cannot be built.
* Allow image processing modules to operate on collections built from existing files.
* Add tests covering `CollectionBuilder` and `buildUploadedFileCollection()`.


## [1.2.0] - 2026-09-14

Introduce `ThumbCollection` to represent the thumbnails generated from an `UploadedFile` and make it available to the image processing modules.

* add `ThumbCollection` as a named collection of `UploadedFile` instances
* add `thumbCollection()` and `setThumbCollection()` to `UploadedFile`
* update `ImageResizer` to create and attach a `ThumbCollection` containing the generated thumbnails
* remove the dependency on a predefined thumbnail name such as `large`
* update `ImageWatermarker` to process the original `UploadedFile` and/or thumbnails from `ThumbCollection` according to the watermark configuration
* reserve the `original` watermark configuration key for the source image
* update `FileManagerConfig` and `ImageWatermarkConfig` to support the new thumbnail and watermark processing model
* update `FileManager` and image-processing tests accordingly
* add dedicated tests for `ThumbCollection` and `ImageWatermarker`
* update `FileManagerTest` to cover watermarking of originals and thumbnails
* update `ImageConverter` to process the original `UploadedFile` and/or thumbnails from `ThumbCollection`
* update conversion tests for original and thumbnail targets

This establishes `ThumbCollection` as the common representation of generated thumbnails for subsequent image-processing modules.

[1.2.0]: https://github.com/moudarir/file-manager/releases/tag/1.2.0

## [1.0.0] - 2026-09-10

Initial stable release of `moudarir/file-manager`.

### File management

* Add the initial file manager architecture with separate upload and image processing components.
* Add `FileManager` and `FileManagerConfig`.
* Add file upload and direct file copying.
* Handle PHP upload errors.
* Validate uploaded file sources.
* Validate MIME types with configurable allowlists.
* Validate file sizes and image dimensions.
* Create upload destination directories automatically.
* Support date-based directory organization.
* Support encrypted or sanitized filenames.
* Handle filename collisions with optional overwrite support.
* Support multiple uploaded files.
* Support a maximum number of uploaded files.
* Add `UploadedFile` and `UploadedFileCollection`.
* Add mutable uploaded-file metadata required by image processing.
* Track file conversion state.

### Image cropping

* Add `ImageCropper` and `ImageCropConfig`.
* Support `GIF`, `JPEG`, `PNG` and `WEBP` sources.
* Support image rotation during cropping.
* Support arbitrary crop coordinates, including positions outside the source image.
* Preserve transparency for `PNG` and `WEBP`.
* Update file size and image dimension metadata after cropping.
* Ignore non-image files during cropping.
* Handle empty uploaded file collections.
* Validate crop configuration and JSON input.
* Handle GD failures while reading, rotating, cropping and saving images.

### Image resizing

* Add `ImageResizer` and `ImageResizeConfig`.
* Support configurable thumbnail sizes.
* Support configurable image quality.
* Support date-based resize directories.
* Support optional removal of source files after resizing.
* Detect the `ImageMagick` executable automatically.

### Image watermarking

* Add `ImageWatermarker` and `ImageWatermarkConfig`.
* Support configurable watermark images.
* Support targeting individual thumbnails.
* Support vertical and horizontal alignment.
* Support watermark opacity.
* Support horizontal and vertical transparency offsets.
* Apply watermarks after resized thumbnails have been generated.

### Image conversion

* Add `ImageConverter` and `ImageConvertConfig`.
* Convert images to WebP using `ImageMagick`.
* Support conversion of resized thumbnails.
* Mark existing WebP images as converted without reprocessing them.
* Update uploaded-file metadata after successful conversion.
* Support optional removal of source files after conversion.
* Roll back generated files when conversion fails.
* Derive conversion thumbnail targets from the resize configuration.

### Command-line execution

* Add a generic `CommandLineHelper`.
* Automatically detect available executables.
* Support `convert` with fallback to `magick`.
* Make executable lookup failures explicit.
* Use the shared command-line helper for image resizing and conversion.
* Support `nice` on Unix-like systems.

### Configuration and error handling

* Add dedicated configuration classes for upload, crop, resize, watermark and conversion operations.
* Add dedicated exceptions for upload and image processing failures.
* Require the `upload()` operation before calling `files()`.

### Testing

* Add comprehensive `PHPUnit` coverage for file uploading and copying.
* Add tests for uploaded-file collections and metadata.
* Add comprehensive crop tests.
* Add comprehensive resize tests.
* Add comprehensive watermark tests.
* Add comprehensive WebP conversion tests.
* Add `FileManager` integration tests covering the processing pipeline.

[1.0.0]: https://github.com/moudarir/file-manager/releases/tag/1.0.0
