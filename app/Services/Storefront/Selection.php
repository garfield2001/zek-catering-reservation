<?php

namespace App\Services\Storefront;

use Illuminate\Http\Request;

/** Temporary anonymous browser selection. No customer, inquiry, or commercial record is created. */
class Selection
{
    private const KEY = 'storefront.selection';

    public function all(Request $request): array
    {
        return $request->session()->get(self::KEY, []);
    }

    public function put(Request $request, string $key, array $item): void
    {
        $items = $this->all($request);
        $items[$key] = $item;
        $request->session()->put(self::KEY, $items);
    }

    public function forget(Request $request, string $key): void
    {
        $items = $this->all($request);
        unset($items[$key]);
        $request->session()->put(self::KEY, $items);
    }
}
