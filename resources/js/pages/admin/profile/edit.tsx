import { useForm } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { UserAvatar } from "../../../components/ui/user-avatar";

export default function ProfileEdit({
    profile,
}: {
    profile: {
        id: number;
        name: string;
        email: string;
        avatar_url: string | null;
    };
}) {
    const form = useForm({ avatar: null as File | null, remove_avatar: false });
    return (
        <AdminLayout
            title="Profile photo"
            description="Optional photo for your internal account. Your name and access stay unchanged."
        >
            <form
                className="max-w-xl space-y-5 rounded-2xl border bg-white p-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({ ...data, _method: "put" }));
                    form.post("/admin/profile/avatar", { forceFormData: true });
                }}
            >
                <div className="flex items-center gap-4">
                    <UserAvatar
                        name={profile.name}
                        src={profile.avatar_url}
                        size="lg"
                    />
                    <div>
                        <h2 className="font-semibold">{profile.name}</h2>
                        <p className="text-sm text-slate-600">
                            {profile.email}
                        </p>
                    </div>
                </div>
                <Field
                    id="avatar"
                    label="Profile photo"
                    error={form.errors.avatar}
                >
                    <input
                        id="avatar"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(event) =>
                            form.setData(
                                "avatar",
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <p className="mt-1 text-xs text-slate-500">
                        JPEG, PNG or WebP, up to 5 MB and 3000 × 3000 pixels.
                    </p>
                </Field>
                {profile.avatar_url && (
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.remove_avatar}
                            onChange={(event) =>
                                form.setData(
                                    "remove_avatar",
                                    event.target.checked,
                                )
                            }
                        />
                        Remove my current photo
                    </label>
                )}
                <Button
                    disabled={
                        form.processing ||
                        (!form.data.avatar && !form.data.remove_avatar)
                    }
                >
                    Save profile photo
                </Button>
            </form>
        </AdminLayout>
    );
}
