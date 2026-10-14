import { Link } from "@inertiajs/react";
import { FoodImageViewer } from "../../catalog/components/food-image-viewer";
import { formatPesos } from "../../packages/money";
import type { Listing, ListingVersion } from "../types";
export type ListingCard = Listing & {
    dish: {
        name: string;
        image_url?: string | null;
        thumbnail_url?: string | null;
    };
    current_version: ListingVersion | null;
    versions: ListingVersion[];
};
export function ListingCards({ listings }: { listings: ListingCard[] }) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {listings.map((listing) => {
                const version = listing.current_version ?? listing.versions[0];
                return (
                    <article
                        key={listing.id}
                        className="space-y-3 rounded-2xl border bg-white p-5"
                    >
                        <div className="flex items-center gap-3">
                            <FoodImageViewer
                                name={listing.dish.name}
                                imageUrl={listing.dish.image_url}
                                thumbnailUrl={listing.dish.thumbnail_url}
                            />
                            <h2 className="text-lg font-semibold">
                                {version?.name ?? listing.dish.name}
                            </h2>
                        </div>
                        <p className="text-sm">
                            {listing.archived_at
                                ? "Archived"
                                : !listing.active
                                  ? "Disabled"
                                  : listing.current_version
                                    ? "Published"
                                    : "Draft"}
                            {listing.current_version && listing.versions.length
                                ? " · Unpublished changes"
                                : ""}
                        </p>
                        {version && (
                            <p className="text-sm">
                                {formatPesos(version.price_minor)} per{" "}
                                {version.selling_unit} · Minimum{" "}
                                {version.minimum_quantity}
                            </p>
                        )}
                        <Link
                            href={`/admin/items/${listing.id}`}
                            className="inline-block text-sm text-emerald-800 underline"
                        >
                            View Details
                        </Link>
                    </article>
                );
            })}
        </div>
    );
}
