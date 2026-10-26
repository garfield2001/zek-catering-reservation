<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileAvatarController extends Controller
{
    public function __invoke(Request $request, User $user): StreamedResponse
    {
        abort_unless($request->user()->is($user) || $request->user()->can('viewAny', User::class), 404);
        $path = $user->getAttribute('avatar_thumbnail_path');
        abort_unless(is_string($path) && preg_match('/^avatars\/[a-z0-9]+-thumb\.jpg$/D', $path), 404);
        $disk = Storage::disk($user->getAttribute('avatar_disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
