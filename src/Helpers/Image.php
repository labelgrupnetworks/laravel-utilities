<?php

namespace Labelgrup\LaravelUtilities\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Image
{
    /**
     * @param string $url
     * @return string|null
     */
    public static function getExtensionImageFromUrl(string $url): ?string
    {
        $urlData = parse_url($url);
        if (!array_key_exists('path', $urlData)) {
            return null;
        }

        return pathinfo($urlData['path'], PATHINFO_EXTENSION);
    }

    /**
     * @param string $src
     * @return bool
     */
    public static function destroy(
        string $src
    ): bool
    {
        if (!Storage::exists($src)) {
            return false;
        }

        Storage::delete($src);
        return true;
    }

    /**
     * @param string $url
     * @param string $fileName
     * @return void
     */
    public static function downloadFromUrl(
        string $url,
        string $fileName
    ): void
    {
        $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        $contents = curl_exec($ch);

        Storage::put($fileName, $contents);
        curl_close($ch);
    }

    /**
     * @param UploadedFile $image
     * @param string $folder
     * @param string|null $disk
     * @param string|null $file_name
     * @return string
     */
    public static function store(
        UploadedFile $image,
        string $folder = '',
        string $disk = null,
        string $file_name = null
    ): string
    {
        return $image->storeAs(
            $folder,
            $file_name ?? Str::random(30) . '.' . $image->extension(),
            $disk
                ? ['disk' => $disk]
                : []
        );
    }
}
