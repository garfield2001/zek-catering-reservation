<?php

namespace App\Services\Catalog;

use App\Rules\FoodImageRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Bounded JPEG re-encoding strips uploaded metadata and executable/polyglot payloads. */
class FoodImages
{
    public function store(UploadedFile $upload): array
    {
        Validator::make(['image' => $upload], ['image' => FoodImageRules::upload()])->validate();
        if (! extension_loaded('gd')) {
            throw ValidationException::withMessages(['image' => 'Image processing is unavailable. Ask the Administrator to enable the PHP GD extension.']);
        }
        $source = @imagecreatefromstring($upload->getContent());
        if ($source === false) {
            throw ValidationException::withMessages(['image' => 'The image could not be decoded. Upload a valid JPEG, PNG or WebP image.']);
        }
        $disk = 'catalog';
        $id = strtolower((string) Str::ulid());
        $paths = ['image_disk' => $disk, 'image_path' => "foods/{$id}.jpg", 'image_thumbnail_path' => "foods/{$id}-thumb.jpg"];
        try {
            [$full, $width, $height] = $this->jpeg($source, 1600);
            [$thumbnail] = $this->jpeg($source, 360);
            Storage::disk($disk)->put($paths['image_path'], $full);
            Storage::disk($disk)->put($paths['image_thumbnail_path'], $thumbnail);

            return [...$paths, 'image_width' => $width, 'image_height' => $height];
        } catch (\Throwable $exception) {
            $this->delete($paths);
            throw ValidationException::withMessages(['image' => 'The image could not be stored. Please try again.']);
        } finally {
            imagedestroy($source);
        }
    }

    private function jpeg(\GdImage $source, int $limit): array
    {
        $scale = min(1, $limit / max(imagesx($source), imagesy($source)));
        $width = max(1, (int) round(imagesx($source) * $scale));
        $height = max(1, (int) round(imagesy($source) * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        try {
            if (! imagejpeg($canvas, null, 85)) {
                throw new \RuntimeException('Image encoding failed.');
            }
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($canvas);
        }

        return [$bytes, $width, $height];
    }

    public function delete(array $metadata): void
    {
        if (! ($metadata['image_disk'] ?? null)) {
            return;
        }
        try {
            $paths = array_values(array_filter([$metadata['image_path'] ?? null, $metadata['image_thumbnail_path'] ?? null]));
            foreach ($paths as $path) {
                if (! preg_match('/^foods\/[a-z0-9]+(?:-thumb)?\.jpg$/D', $path)) {
                    throw new \RuntimeException('Unexpected image reference.');
                }
            }
            if ($paths && ! Storage::disk($metadata['image_disk'])->delete($paths)) {
                throw new \RuntimeException('Image cleanup failed.');
            }
        } catch (\Throwable $exception) {
            // The committed catalog save must not be reported as a failure after commit.
            Log::warning('Catalog image cleanup requires Administrator attention.');
        }
    }
}
