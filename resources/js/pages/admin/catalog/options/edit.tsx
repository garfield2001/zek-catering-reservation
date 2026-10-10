import AdminLayout from "../../../../layouts/admin-layout";
import { OptionForm } from "../../../../features/catalog/components/option-form";
import type {
    FoodItem,
    Offering,
    ServingOption,
} from "../../../../features/catalog/types";
export default function OptionEdit({
    dish,
    offering,
    option,
}: {
    dish: FoodItem;
    offering: Offering;
    option: ServingOption | null;
}) {
    return (
        <AdminLayout
            title={option ? "Edit serving option" : "Create serving option"}
            description={dish.name + " · " + offering.name}
        >
            <OptionForm dish={dish} offering={offering} option={option} />
        </AdminLayout>
    );
}
