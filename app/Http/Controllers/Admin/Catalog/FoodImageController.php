<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FoodImageController extends Controller
{
    public function __invoke(Request $request, Dish $dish, string $size): StreamedResponse
    {
        abort_unless(in_array($size, ['full', 'thumbnail'], true), 404);
        $catalogAccess = $request->user()?->can('view', $dish) ?? false;
        $publiclyVisible = $dish->active && $dish->category->active;
        abort_unless($catalogAccess || $publiclyVisible, 404);
        $path = $dish->getAttribute($size === 'full' ? 'image_path' : 'image_thumbnail_path');
        abort_unless(is_string($path) && preg_match('/^foods\/[a-z0-9]+(?:-[a-z0-9]+)*(?:-thumb)?\.jpg$/D', $path), 404);
        $disk = Storage::disk($dish->getAttribute('image_disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=60']);
    }
}
