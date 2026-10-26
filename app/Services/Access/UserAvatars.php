<?php

namespace App\Services\Access;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Stores opaque account-image identifiers; personal names never enter paths. */
class UserAvatars
{
    public function store(UploadedFile $upload): array
    {
        Validator::make(['avatar' => $upload], ['avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=3000,max_height=3000']])->validate();
        if (! extension_loaded('gd')) {
            throw ValidationException::withMessages(['avatar' => 'Image processing is unavailable. Ask the Administrator to enable the PHP GD extension.']);
        }
        $source = @imagecreatefromstring($upload->getContent());
        if ($source === false) {
            throw ValidationException::withMessages(['avatar' => 'The image could not be decoded. Upload a valid JPEG, PNG or WebP image.']);
        }
        $id = strtolower((string) Str::ulid());
        $metadata = ['avatar_disk' => 'public', 'avatar_path' => "avatars/{$id}.jpg", 'avatar_thumbnail_path' => "avatars/{$id}-thumb.jpg"];
        try {
            if (! Storage::disk('public')->put($metadata['avatar_path'], $this->jpeg($source, 800))
                || ! Storage::disk('public')->put($metadata['avatar_thumbnail_path'], $this->jpeg($source, 160))) {
                throw new \RuntimeException('Avatar storage failed.');
            }

            return $metadata;
        } catch (\Throwable) {
            $this->delete($metadata);
            throw ValidationException::withMessages(['avatar' => 'The profile photo could not be stored. Please try again.']);
        } finally {
            imagedestroy($source);
        }
    }

    public function delete(array $metadata): void
    {
        $paths = array_filter([$metadata['avatar_path'] ?? null, $metadata['avatar_thumbnail_path'] ?? null]);
        if (! $paths || ! ($metadata['avatar_disk'] ?? null)) {
            return;
        }
        if (collect($paths)->every(fn ($path) => is_string($path) && preg_match('/^avatars\/[a-z0-9]+(?:-thumb)?\.jpg$/D', $path))) {
            Storage::disk($metadata['avatar_disk'])->delete($paths);
        }
    }

    private function jpeg(\GdImage $source, int $limit): string
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
                throw new \RuntimeException('Avatar encoding failed.');
            }

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($canvas);
        }
    }
}
