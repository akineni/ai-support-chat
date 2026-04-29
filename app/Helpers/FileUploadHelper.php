<?php

namespace App\Helpers;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

class FileUploadHelper
{
    private static function cloudinary(): Cloudinary
    {
        return new Cloudinary(
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => config('services.cloudinary.cloud_name'),
                    'api_key'    => config('services.cloudinary.api_key'),
                    'api_secret' => config('services.cloudinary.api_secret'),
                ],
                'url'   => [
                    'secure' => true,
                ],
            ])
        );
    }

    public static function multipleBinaryFileUpload(
        iterable $requestFiles,
        string $folder,
        ?string $publicIdPrefix = null
    ): array {
        $files = [];

        foreach ($requestFiles as $file) {
            $files[] = self::singleBinaryFileUpload($file, $folder, $publicIdPrefix);
        }

        return $files;
    }

    public static function singleBinaryFileUpload(
        UploadedFile $requestFile,
        string $folder,
        ?string $publicIdPrefix = null
    ): string {
        $publicId     = self::generatePublicId($requestFile, $publicIdPrefix);
        $resourceType = self::getResourceType($requestFile->getMimeType());

        $result = self::cloudinary()->uploadApi()->upload(
            $requestFile->getRealPath(),
            [
                'folder'        => $folder,
                'public_id'     => $publicId,
                'resource_type' => $resourceType,
            ]
        );

        return $result['secure_url'];
    }

    public static function singleStringFileUpload(
        string $requestFile,
        string $folder,
        ?string $publicIdPrefix = null
    ): string {
        $tmpFilePath = null;

        try {
            $fileData    = self::decodeBase64File($requestFile);
            $tmpFilePath = self::createTemporaryFile($fileData);
            $tmpFile     = new File($tmpFilePath);

            $file = new UploadedFile(
                $tmpFile->getPathname(),
                $tmpFile->getFilename(),
                $tmpFile->getMimeType(),
                0,
                true
            );

            $publicId     = self::generatePublicId($file, $publicIdPrefix);
            $resourceType = self::getResourceType($file->getMimeType());

            $result = self::cloudinary()->uploadApi()->upload(
                $tmpFilePath,
                [
                    'folder'        => $folder,
                    'public_id'     => $publicId,
                    'resource_type' => $resourceType,
                ]
            );

            return $result['secure_url'];
        } finally {
            if ($tmpFilePath && file_exists($tmpFilePath)) {
                @unlink($tmpFilePath);
            }
        }
    }

    public static function multipleStringFileUpload(
        iterable $requestFiles,
        string $folder,
        ?string $publicIdPrefix = null
    ): array {
        $files = [];

        foreach ($requestFiles as $file) {
            $files[] = self::singleStringFileUpload($file, $folder, $publicIdPrefix);
        }

        return $files;
    }

    public static function getFileExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    public static function deleteFromCloudinary(string $fileUrl): bool
    {
        if (!$fileUrl) {
            return false;
        }

        try {
            $publicId = self::extractCloudinaryPublicId($fileUrl);

            if (!$publicId) {
                Log::warning('Could not extract Cloudinary public ID from URL: ' . $fileUrl);
                return false;
            }

            self::cloudinary()->uploadApi()->destroy($publicId);

            Log::info('Cloudinary delete successful: ' . $publicId);

            return true;
        } catch (\Throwable $e) {
            Log::error('Cloudinary delete failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function getResourceType(?string $mimeType): string
    {
        if (is_null($mimeType)) {
            return 'raw';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'raw';
    }

    private static function generatePublicId(UploadedFile $file, ?string $prefix = null): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName     = Str::slug($originalName);
        $prefix       = $prefix ?? 'file_';

        return $prefix . Str::random(12) . ($safeName ? '_' . $safeName : '');
    }

    private static function decodeBase64File(string $file): string
    {
        $sanitized = preg_replace('#^data:[^;]+;base64,#i', '', $file);
        $decoded   = base64_decode($sanitized, true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 file payload.');
        }

        return $decoded;
    }

    private static function createTemporaryFile(string $fileData): string
    {
        $tmpFilePath = tempnam(sys_get_temp_dir(), 'upload_');

        if ($tmpFilePath === false) {
            throw new \RuntimeException('Failed to create temporary file.');
        }

        file_put_contents($tmpFilePath, $fileData);

        return $tmpFilePath;
    }

    private static function extractCloudinaryPublicId(string $fileUrl): ?string
    {
        $path = parse_url($fileUrl, PHP_URL_PATH);

        if (!$path) {
            return null;
        }

        $path           = ltrim($path, '/');
        $uploadPosition = strpos($path, 'upload/');

        if ($uploadPosition === false) {
            return null;
        }

        $publicPath = substr($path, $uploadPosition + strlen('upload/'));
        $segments   = explode('/', $publicPath);

        if (!empty($segments[0]) && preg_match('/^v\d+$/', $segments[0])) {
            array_shift($segments);
        }

        $publicId = implode('/', $segments);

        return preg_replace('/\.[^.]+$/', '', $publicId);
    }
}