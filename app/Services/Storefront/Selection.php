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

    /**
     * Replaces only the active Main Catering choice. Optional add-ons remain
     * in the inquiry and are revalidated before request submission.
     *
     * @param  list<int>  $mainProductIds
     */
    public function replaceMain(Request $request, string $key, array $item, array $mainProductIds): void
    {
        $items = $this->all($request);
        foreach ($items as $storedKey => $stored) {
            if (($stored['type'] ?? null) === 'package' && in_array($stored['product_id'] ?? null, $mainProductIds, true)) {
                unset($items[$storedKey]);
            }
        }
        $items[$key] = $item;
        $request->session()->put(self::KEY, $items);
    }

    public function forget(Request $request, string $key): void
    {
        $items = $this->all($request);
        unset($items[$key]);
        $request->session()->put(self::KEY, $items);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }
}
