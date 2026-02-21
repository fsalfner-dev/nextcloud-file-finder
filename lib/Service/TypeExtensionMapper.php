<?php

declare(strict_types=1);

namespace OCA\FileFinder\Service;

class TypeExtensionMapper  {

    /** @var array<string, string[]> */
    private const FILE_TYPE_EXTENSIONS = [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico', 'heic'],
        'music' => ['mp3', 'ogg', 'flac', 'wav', 'm4a', 'aac', 'wma'],
        'pdfs' => ['pdf'],
        'spreadsheets' => ['xls', 'xlsx', 'ods', 'csv', 'numbers'],
        'documents' => ['doc', 'docx', 'odt', 'txt', 'rtf', 'md', 'pages'],
        'presentations' => ['ppt', 'pptx', 'odp', 'keynote'],
        'videos' => ['mp4', 'webm', 'mkv', 'avi', 'mov', 'wmv'],
    ];



    /**
     * Map a list of file types (such as 'music') to a list of file extensions
     *
     * @param string[] | null $fileTypes array of type keys, or null/empty
     * @return string[] deduplicated, lowercase extensions
     */
    public static function getExtensionsForTypes($fileTypes): array {
        if (!is_array($fileTypes) || $fileTypes === []) {
            return [];
        }
        $merged = [];
        foreach ($fileTypes as $t) {
            if (!is_string($t)) {
                continue;
            }
            $extensionsForType = self::FILE_TYPE_EXTENSIONS[$t] ?? [];
            foreach ($extensionsForType as $ext) {
                $merged[$ext] = true;
            }
        }
        return array_keys($merged);
    }
}
