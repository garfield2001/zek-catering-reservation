import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { Collection } from "../../catalog/types";
import { packagePaths } from "../paths";
import type { PackageProduct } from "../types";

export function ProductForm({
    product,
    families,
    selectedFamilyId,
}: {
    product: PackageProduct | null;
    families: Collection[];
    selectedFamilyId?: number | null;
}) {
    const form = useForm({
        family_id:
            product?.family_id ?? selectedFamilyId ?? families[0]?.id ?? 0,
        name: product?.name ?? "",
        description: product?.description ?? "",
        active: product?.active ?? true,
        ...(!product ? { creation_token: crypto.randomUUID() } : {}),
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
                label="Collection"
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
