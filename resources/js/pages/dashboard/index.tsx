import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';

type Props = {
    shop: { id: string; name: string };
    plan: { code: string; label: string; status: string };
    license: {
        source: string;
        status: string;
        ends_at: string | null;
    } | null;
    empty: { no_key: boolean; no_products: boolean; no_cost: boolean };
    stats: {
        product_count: number;
        has_active_key: boolean;
        has_cost_profile: boolean;
    };
};

export default function DashboardIndex({
    shop,
    plan,
    license,
    empty,
    stats,
}: Props): ReactNode {
    return (
        <DashboardLayout title="داشبورد فروشنده">
            <div className="mb-6 space-y-1 text-sm text-zinc-600">
                <p>
                    فروشگاه: <strong>{shop.name}</strong> ({shop.id})
                </p>
                <p>
                    پلن: <strong>{plan.label}</strong> ({plan.status})
                </p>
                {license && (
                    <p>
                        لایسنس {license.source}:{' '}
                        <strong>{license.status}</strong>
                        {license.ends_at
                            ? ` تا ${new Date(license.ends_at).toLocaleDateString('fa-IR')}`
                            : ''}
                    </p>
                )}
            </div>

            {(empty.no_key || empty.no_cost || empty.no_products) && (
                <section className="mb-8 space-y-3 border border-amber-200 bg-amber-50 p-4 text-sm">
                    <p className="font-medium">برای شروع این موارد لازم است:</p>
                    {empty.no_key && (
                        <p>
                            هنوز کلید API فعالی ندارید.{' '}
                            <Link
                                href="/dashboard/api-keys"
                                className="underline"
                            >
                                صدور کلید
                            </Link>
                        </p>
                    )}
                    {empty.no_cost && (
                        <p>
                            پروفایل هزینه ثبت نشده؛ توصیه قیمت بدون کف هزینه
                            قابل اعتماد نیست.{' '}
                            <Link href="/dashboard/costs" className="underline">
                                ثبت هزینه‌ها
                            </Link>
                        </p>
                    )}
                    {empty.no_products && (
                        <p>
                            هنوز محصولی همگام نشده. کلید را در افزونه وردپرس
                            وارد کنید و همگام‌سازی را اجرا کنید.
                        </p>
                    )}
                </section>
            )}

            <dl className="grid gap-4 sm:grid-cols-3">
                <div className="border border-zinc-200 bg-white p-4">
                    <dt className="text-sm text-zinc-500">کلید فعال</dt>
                    <dd className="mt-1 text-lg font-medium">
                        {stats.has_active_key ? 'بله' : 'خیر'}
                    </dd>
                </div>
                <div className="border border-zinc-200 bg-white p-4">
                    <dt className="text-sm text-zinc-500">پروفایل هزینه</dt>
                    <dd className="mt-1 text-lg font-medium">
                        {stats.has_cost_profile ? 'ثبت شده' : 'ثبت نشده'}
                    </dd>
                </div>
                <div className="border border-zinc-200 bg-white p-4">
                    <dt className="text-sm text-zinc-500">تعداد محصولات</dt>
                    <dd className="mt-1 text-lg font-medium">
                        {stats.product_count}
                    </dd>
                </div>
            </dl>
        </DashboardLayout>
    );
}
