import { Link } from "@inertiajs/react";
import { EmptyState } from "./empty-state";
import { StatusBadge } from "./status-badge";
import { catalogPaths } from "../paths";
import type { ServingOption } from "../types";
export function OptionList({
    dishId,
    offeringId,
    options,
    canManage,
}: {
    dishId: number;
    offeringId: number;
    options: ServingOption[];
    canManage: boolean;
}) {
    if (options.length === 0)
        return (
            <EmptyState
                title="No serving options yet"
                description="Define a unit and size for this offering before it can be used later."
                action={
                    canManage && (
                        <Link
                            className="underline text-emerald-800"
                            href={
                                catalogPaths.offering(dishId, offeringId) +
                                "/options/create"
                            }
                        >
                            Add serving option
                        </Link>
                    )
                }
            />
        );
    return (
        <div className="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table className="w-full text-left text-sm">
                <thead className="bg-stone-100">
                    <tr>
                        {[
                            "Option",
                            "Size",
                            "Approx. capacity",
                            "Status",
                            "",
                        ].map((label) => (
                            <th key={label} className="px-5 py-3">
                                {label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {options.map((option) => (
                        <tr
                            key={option.id}
                            className="border-t border-stone-100"
                        >
                            <td className="px-5 py-4">
                                <div className="font-medium">
                                    {option.label}
                                </div>
                                <div className="text-xs text-stone-500">
                                    {option.option_code}
                                </div>
                            </td>
                            <td className="px-5 py-4">
                                {option.quantity} {option.unit}
                            </td>
                            <td className="px-5 py-4">
                                {option.serving_claim ??
                                    (option.serving_pax
                                        ? "Approx. " +
                                          option.serving_pax +
                                          " guests"
                                        : "Not specified")}
                            </td>
                            <td className="px-5 py-4">
                                <StatusBadge active={option.active} />
                            </td>
                            <td className="px-5 py-4">
                                {canManage && (
                                    <Link
                                        className="underline"
                                        href={
                                            catalogPaths.option(
                                                dishId,
                                                offeringId,
                                                option.id,
                                            ) + "/edit"
                                        }
                                    >
                                        Edit
                                    </Link>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
