<?php

declare(strict_types=1);

function create_compressed_thumbnail(
    string $sourcePath,
    string $sourceMime,
    string $destinationPath,
    int $maxDimension,
    int $jpegQuality,
): void {
    if (!extension_loaded("gd")) {
        throw new ApiException(
            500,
            "Thumbnail processing is unavailable",
            "THUMBNAIL_PROCESSOR_UNAVAILABLE",
        );
    }

    $dimensions = getimagesize($sourcePath);
    $width = (int) ($dimensions[0] ?? 0);
    $height = (int) ($dimensions[1] ?? 0);
    if ($width < 1 || $height < 1 || $width * $height > 40000000) {
        throw new ApiException(
            422,
            "The uploaded image dimensions are invalid or too large",
            "INVALID_IMAGE_DIMENSIONS",
        );
    }

    $loader = match ($sourceMime) {
        "image/jpeg" => "imagecreatefromjpeg",
        "image/png" => "imagecreatefrompng",
        "image/gif" => "imagecreatefromgif",
        "image/webp" => "imagecreatefromwebp",
        default => null,
    };
    if (!$loader || !function_exists($loader)) {
        throw new ApiException(
            422,
            "This image format cannot be processed for thumbnails",
            "THUMBNAIL_FORMAT_UNSUPPORTED",
        );
    }

    $source = @$loader($sourcePath);
    if (!$source) {
        throw new ApiException(
            422,
            "The uploaded image could not be decoded",
            "IMAGE_DECODE_FAILED",
        );
    }

    $scale = min(1, $maxDimension / max($width, $height));
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));
    $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
    $background = imagecolorallocate($thumbnail, 255, 255, 255);
    imagefill($thumbnail, 0, 0, $background);
    imagecopyresampled(
        $thumbnail,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $width,
        $height,
    );
    imageinterlace($thumbnail, true);
    $written = imagejpeg(
        $thumbnail,
        $destinationPath,
        min(95, max(40, $jpegQuality)),
    );
    imagedestroy($thumbnail);
    imagedestroy($source);

    if (!$written) {
        throw new ApiException(
            500,
            "Unable to write the generated thumbnail",
            "THUMBNAIL_WRITE_FAILED",
        );
    }
}
