<?php

namespace Morcen\Passage\Support;

use Illuminate\Http\UploadedFile;

/**
 * Flattens the structure returned by Request::allFiles() into a flat map of
 * bracket-notation key => UploadedFile.
 *
 * Symfony's FileBag::convertFileInformation() recursively nests arrays for
 * bracket-style multipart field names (e.g. `docs[0][avatar]` produces
 * ['docs' => [0 => ['avatar' => UploadedFile]]]) to an arbitrary depth, not
 * just one level. Shared by HasHmacAuth (to sign each uploaded file) and
 * PassageService (to attach each uploaded file to the outbound request),
 * since both need to walk that structure to whatever depth it actually is.
 */
class NestedFileResolver
{
    public static function flatten(array $files): array
    {
        $flattened = [];

        foreach ($files as $key => $file) {
            self::walk((string) $key, $file, $flattened);
        }

        return $flattened;
    }

    private static function walk(string $key, mixed $file, array &$flattened): void
    {
        if ($file instanceof UploadedFile) {
            $flattened[$key] = $file;

            return;
        }

        foreach ($file as $index => $nested) {
            self::walk("{$key}[{$index}]", $nested, $flattened);
        }
    }
}
