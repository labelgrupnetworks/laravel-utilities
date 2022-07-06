<?php

namespace Labelgrup\LaravelUtilities\Helpers;

use ZipArchive;

class Zip
{
    /**
     * @param string $zipFile
     * @param string $sourcePath
     * @return string|null
     */
    public static function create(
        string $zipFile,
        string $sourcePath
    ): ?string
    {
        $pathInfo = pathInfo($sourcePath);
        $parentPath = $pathInfo['dirname'];

        $zip = new ZipArchive();
        $zip->open($zipFile, ZIPARCHIVE::CREATE);
        self::folderToZip($sourcePath, $zip, strlen("$parentPath/"));
        $zip->close();

        return $zipFile;
    }

    /**
     * @param string $folder
     * @param ZipArchive $zipFile
     * @param int $exclusiveLength
     * @return void
     */
    private static function folderToZip(
        string $folder,
        ZipArchive &$zipFile,
        int $exclusiveLength
    )
    {
        $handle = opendir($folder);
        while (false !== $item = readdir($handle)) {
            if ($item !== '.' && $item !== '..') {
                $filePath = realpath("$folder/$item");
                // Remove prefix from file path before add to zip.
                $localPath = substr($filePath, $exclusiveLength);
                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $zipFile->addEmptyDir($localPath);
                    self::folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }
}
