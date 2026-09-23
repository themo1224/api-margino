import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';

type CostFields = {
    staff_cost: string;
    rent_cost: string;
    utilities_cost: string;
    other_overhead: string;
    min_margin_percent: string;
};

type Profile = CostFields & {
    allocation_method: string;
};

type Props = {
    profile: Profile | null;
    hasProfile: boolean;
};

const fields: Array<{ key: keyof CostFields; label: string }> = [
    { key: 'staff_cost', label: 'هزینه پرسنل' },
    { key: 'rent_cost', label: 'اجاره' },
    { key: 'utilities_cost', label: 'قبوض / آب و برق' },
    { key: 'other_overhead', label: 'سایر سربار' },
    { key: 'min_margin_percent', label: 'حداقل حاشیه سود (%)' },
];

export default function CostsPage({ profile, hasProfile }: Props): ReactNode {
    const form = useForm({
        staff_cost: profile?.staff_cost ?? '0',
        rent_cost: profile?.rent_cost ?? '0',
        utilities_cost: profile?.utilities_cost ?? '0',
        other_overhead: profile?.other_overhead ?? '0',
        min_margin_percent: profile?.min_margin_percent ?? '0',
    });

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.put('/dashboard/costs');
    }

    return (
        <DashboardLayout title="پروفایل هزینه">
            {!hasProfile && (
                <p className="mb-4 border border-amber-200 bg-amber-50 p-3 text-sm">
                    هنوز هزینه‌ای ثبت نشده. پس از ذخیره، توصیه قیمت‌ها بر اساس
                    کف هزینه دوباره محاسبه می‌شوند.
                </p>
            )}

            <form onSubmit={submit} className="max-w-lg space-y-4">
                {fields.map((field) => (
                    <label key={field.key} className="block text-sm">
                        <span className="mb-1 block">{field.label}</span>
                        <input
                            type="text"
                            inputMode="decimal"
                            value={form.data[field.key]}
                            onChange={(e) =>
                                form.setData(field.key, e.target.value)
                            }
                            className="w-full border border-zinc-300 px-3 py-2"
                            required
                        />
                        {form.errors[field.key] && (
                            <span className="mt-1 block text-sm text-red-600">
                                {form.errors[field.key]}
                            </span>
                        )}
                    </label>
                ))}
                <p className="text-xs text-zinc-500">
                    روش تخصیص سربار: تقسیم مساوی بین SKUها (equal_split)
                </p>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="bg-zinc-900 px-4 py-2 text-sm text-white disabled:opacity-50"
                >
                    ذخیره و محاسبه مجدد
                </button>
            </form>
        </DashboardLayout>
    );
}
