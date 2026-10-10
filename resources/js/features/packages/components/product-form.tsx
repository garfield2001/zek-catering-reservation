import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { Family } from "../../catalog/types";
import { packagePaths } from "../paths";
import type { PackageProduct } from "../types";

export function ProductForm({
    product,
    families,
}: {
    product: PackageProduct | null;
    families: Family[];
}) {
    const form = useForm({
        family_id: product?.family_id ?? families[0]?.id ?? 0,
        code: product?.code ?? "",
        name: product?.name ?? "",
        description: product?.description ?? "",
        sort_order: product?.sort_order ?? 0,
        active: product?.active ?? true,
    });
    return (
        <form
            className="max-w-xl space-y-5 rounded-xl border border-stone-200 bg-white p-6"
            onSubmit={(event) => {
                event.preventDefault();
                if (product) form.put(packagePaths.product(product.id));
                else form.post(packagePaths.index);
            }}
        >
            <Field
                id="family_id"
                label="Package family"
                error={form.errors.family_id}
            >
                {product ? (
                    <input
                        id="family_id"
                        readOnly
                        value={product.family?.name ?? ""}
                    />
                ) : (
                    <select
                        id="family_id"
                        value={form.data.family_id}
                        onChange={(event) =>
                            form.setData(
                                "family_id",
                                Number(event.target.value),
                            )
                        }
                    >
                        {families.map((family) => (
                            <option key={family.id} value={family.id}>
                                {family.name}
                            </option>
                        ))}
                    </select>
                )}
            </Field>
            <Field
                id="code"
                label="Stable product code"
                error={form.errors.code}
            >
                <input
                    id="code"
                    required
                    readOnly={!!product}
                    value={form.data.code}
                    onChange={(event) =>
                        form.setData("code", event.target.value)
                    }
                    placeholder="main_catering"
                />
            </Field>
            <Field id="name" label="Product name" error={form.errors.name}>
                <input
                    id="name"
                    required
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData("name", event.target.value)
                    }
                />
            </Field>
            <Field
                id="description"
                label="Description"
                error={form.errors.description}
            >
                <textarea
                    id="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData("description", event.target.value)
                    }
                />
            </Field>
            <Field
                id="sort_order"
                label="Display order"
                error={form.errors.sort_order}
            >
                <input
                    id="sort_order"
                    type="number"
                    value={form.data.sort_order}
                    onChange={(event) =>
                        form.setData("sort_order", Number(event.target.value))
                    }
                />
            </Field>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    onChange={(event) =>
                        form.setData("active", event.target.checked)
                    }
                />
                Active product
            </label>
            <Button
                disabled={
                    form.processing || (!product && families.length === 0)
                }
            >
                {product ? "Save product" : "Create product"}
            </Button>
        </form>
    );
}
