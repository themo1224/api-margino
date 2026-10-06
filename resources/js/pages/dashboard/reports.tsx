import { Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';

type ProductRow = {
    id: number;
    external_id: string;
    name: string;
    price: string;
    floor_price: string | null;
    recommended_price: string | null;
    below_floor: boolean;
    rival_cheapest?: string | null;
};

type Props = {
    report: {
        days: number;
        from: string;
        to: string;
        below_floor: { count: number; products: ProductRow[] };
        above_rival: { count: number; products: ProductRow[] };
        apply_rate: {
            recommendations: number;
            applies: number;
            percent: number | null;
        };
    };
};

export default function DashboardReports({ report }: Props): ReactNode {
    const setDays = (days: number): void => {
        router.get('/dashboard/reports', { days }, { preserveState: true });
    };

    return (
        <DashboardLayout title="گزارش‌ها">
            <div className="mb-6 flex flex-wrap items-center gap-3 text-sm">
                <span className="text-zinc-600">بازه:</span>
                {[7, 30].map((d) => (
                    <button
                        key={d}
                        type="button"
                        onClick={() => setDays(d)}
                        className={
                            report.days === d
                                ? 'border border-zinc-900 bg-zinc-900 px-3 py-1 text-white'
                                : 'border border-zinc-300 bg-white px-3 py-1 text-zinc-800'
                        }
                    >
                        {d} روز
                    </button>
                ))}
                <span className="text-zinc-400">
                    از {new Date(report.from).toLocaleDateString('fa-IR')} تا{' '}
                    {new Date(report.to).toLocaleDateString('fa-IR')}
                </span>
            </div>

            <dl className="mb-8 grid gap-4 sm:grid-cols-3">
                <div className="border border-zinc-200 bg-white p-4">
                    <dt className="text-sm text-zinc-500">زیر کف هزینه</dt>
                    <dd className="mt-1 text-2xl font-semibold">
                        {report.below_floor.count}
                    </dd>
                </div>
                <div className="border border-zinc-200 bg-white p-4">
                    <dt className="text-sm text-zinc-500">بالاتر از رقیب</dt>
                    <dd className="mt-1 text-2xl font-semibold">
                        {report.above_rival.count}
                    </dd>
                </div>
                <div className="border border-zinc-200 bg-white p-4">
                    <dt className="text-sm text-zinc-500">نرخ اعمال پیشنهاد</dt>
                    <dd className="mt-1 text-2xl font-semibold">
                        {report.apply_rate.percent === null
                            ? '—'
                            : `${report.apply_rate.percent}%`}
                    </dd>
                    <p className="mt-1 text-xs text-zinc-500">
                        {report.apply_rate.applies} اعمال /{' '}
                        {report.apply_rate.recommendations} پیشنهاد
                    </p>
                </div>
            </dl>

            <Section
                title="محصولات زیر کف"
                empty="در این بازه محصولی زیر کف نیست."
                products={report.below_floor.products}
            />
            <Section
                title="محصولات بالاتر از ارزان‌ترین رقیب"
                empty="رقیب تازه‌ای برای مقایسه نیست، یا قیمت‌ها رقابتی‌اند."
                products={report.above_rival.products}
                showRival
            />

            <p className="mt-6 text-sm text-zinc-500">
                اعمال قیمت فقط از افزونه ووکامرس ثبت می‌شود؛ داشبورد فقط گزارش
                می‌دهد.{' '}
                <Link href="/dashboard/products" className="underline">
                    محصولات
                </Link>
            </p>
        </DashboardLayout>
    );
}

function Section({
    title,
    empty,
    products,
    showRival = false,
}: {
    title: string;
    empty: string;
    products: ProductRow[];
    showRival?: boolean;
}): ReactNode {
    return (
        <section className="mb-8">
            <h2 className="mb-3 text-lg font-medium">{title}</h2>
            {products.length === 0 ? (
                <p className="text-sm text-zinc-500">{empty}</p>
            ) : (
                <div className="overflow-x-auto border border-zinc-200 bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-zinc-50 text-right text-zinc-500">
                            <tr>
                                <th className="px-3 py-2 font-medium">نام</th>
                                <th className="px-3 py-2 font-medium">قیمت</th>
                                <th className="px-3 py-2 font-medium">کف</th>
                                <th className="px-3 py-2 font-medium">پیشنهاد</th>
                                {showRival && (
                                    <th className="px-3 py-2 font-medium">
                                        ارزان‌ترین رقیب
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {products.map((p) => (
                                <tr
                                    key={p.id}
                                    className="border-t border-zinc-100"
                                >
                                    <td className="px-3 py-2">{p.name}</td>
                                    <td className="px-3 py-2">{p.price}</td>
                                    <td className="px-3 py-2">
                                        {p.floor_price ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {p.recommended_price ?? '—'}
                                    </td>
                                    {showRival && (
                                        <td className="px-3 py-2">
                                            {p.rival_cheapest ?? '—'}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
