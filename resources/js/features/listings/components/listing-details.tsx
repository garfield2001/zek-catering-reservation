import { formatPesos } from "../../packages/money";
import { FoodImageViewer } from "../../catalog/components/food-image-viewer";
import type { ListingSnapshot } from "../types";

export function ListingDetails({
    snapshot,
    imageUrl,
    thumbnailUrl,
}: {
    snapshot: ListingSnapshot;
    imageUrl: string | null;
    thumbnailUrl: string | null;
}) {
    return (
        <section className="space-y-4 rounded-2xl border bg-white p-6">
            <h2 className="text-xl font-semibold">{snapshot.name}</h2>
            <p className="text-sm text-stone-500">
                {snapshot.service.name} · {snapshot.category.name}
            </p>
            <FoodImageViewer
                name={snapshot.food.name}
                imageUrl={imageUrl}
                thumbnailUrl={thumbnailUrl}
            />
            <p>{snapshot.description}</p>
            <p>Food Item: {snapshot.food.name}</p>
            {snapshot.selling_options?.length ? (
                <ul className="space-y-2" aria-label="Selling option prices">
                    {snapshot.selling_options.map((option) => (
                        <li
                            key={option.id}
                            className="flex justify-between gap-3 rounded-lg border p-3"
                        >
                            <span>{option.label}</span>
                            <strong>
                                {formatPesos(option.price_minor)} per{" "}
                                {snapshot.selling_unit}
                            </strong>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="font-semibold">
                    {formatPesos(snapshot.price_minor)} per{" "}
                    {snapshot.selling_unit}
                </p>
            )}
            <p>
                Minimum {snapshot.minimum_quantity}; increments of{" "}
                {snapshot.quantity_step}; maximum{" "}
                {snapshot.maximum_quantity ?? "not set"}.
            </p>
            {snapshot.serving_option && (
                <div className="rounded-lg bg-stone-50 p-4 text-sm">
                    <p>
                        {snapshot.serving_option.label}:{" "}
                        {snapshot.serving_option.quantity}{" "}
                        {snapshot.serving_option.unit}
                    </p>
                    {snapshot.serving_option.serving_pax && (
                        <p>
                            Estimated capacity:{" "}
                            {snapshot.serving_option.serving_pax} people.
                        </p>
                    )}
                    <p>{snapshot.serving_option.serving_claim}</p>
                </div>
            )}
            <p className="text-sm text-stone-500">
                Sold individually, without a package menu. Customer inquiries
                and purchasing are not available in this milestone.
            </p>
        </section>
    );
}
