import { router } from "@inertiajs/react";
import { useState } from "react";
import { Button } from "../../../components/ui/button";
import { catalogPaths } from "../paths";
export function FoodItemSearch({ initial }: { initial: string }) {
    const [query, setQuery] = useState(initial);
    return (
        <form
            className="mb-5 flex max-w-lg gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                router.get(
                    catalogPaths.dishes,
                    { q: query },
                    { preserveState: true, replace: true },
                );
            }}
        >
            <label className="sr-only" htmlFor="dish-search">
                Search dishes
            </label>
            <input
                id="dish-search"
                type="search"
                maxLength={100}
                placeholder="Search by name or code"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
            />
            <Button type="submit">Search</Button>
        </form>
    );
}
