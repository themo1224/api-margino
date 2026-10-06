import { router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';

type RivalRow = {
    source: string;
    cheapest_price: string;
    median_price: string | null;
    competitor_count: number;
    captured_at: string;
    listing_title: string | null;
};

type PendingMatch = {
    id: number;
    source: string;
    listing_title: string | null;
    confidence: string;
};

type ProductRow = {
    id: number;
    external_id: string;
    sku: string | null;
    name: string;
    brand: string | null;
    barcode: string | null;
    price: string;
    currency: string;
    floor_price: string | null;
    recommended_price: string | null;
    below_floor: boolean;
    rivals_stale: boolean;
    cannot_match_profitably: boolean;
    direct_cost: string | null;
    min_margin_percent: string | null;
    max_price: string | null;
    last_synced_at: string | null;
    rival: RivalRow | null;
    pending_rival_match: PendingMatch | null;
};

type Props = {
    products: ProductRow[];
    hasCostProfile: boolean;
    isEmpty: boolean;
};

function ProductCostForm({ product }: { product: ProductRow }): ReactNode {
    const [open, setOpen] = useState(false);
    const form = useForm({
        direct_cost: product.direct_cost ?? '',
        min_margin_percent: product.min_margin_percent ?? '',
        max_price: product.max_price ?? '',
    });

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.put(`/dashboard/products/${product.id}/cost`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    if (!open) {
        return (
            <button
                type="button"
                className="text-xs underline"
                onClick={() => setOpen(true)}
            >
                ویرایش هزینه
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="mt-2 space-y-2 text-xs">
            <input
                type="text"
                placeholder="هزینه مستقیم"
                value={form.data.direct_cost}
                onChange={(e) => form.setData('direct_cost', e.target.value)}
                className="w-full border border-zinc-300 px-2 py-1"
            />
            <input
                type="text"
                placeholder="حاشیه % (اختیاری)"
                value={form.data.min_margin_percent}
                onChange={(e) =>
                    form.setData('min_margin_percent', e.target.value)
                }
                className="w-full border border-zinc-300 px-2 py-1"
            />
            <input
                type="text"
                placeholder="سقف قیمت (اختیاری)"
                value={form.data.max_price}
                onChange={(e) => form.setData('max_price', e.target.value)}
                className="w-full border border-zinc-300 px-2 py-1"
            />
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="bg-zinc-900 px-2 py-1 text-white disabled:opacity-50"
                >
                    ذخیره
                </button>
                <button
                    type="button"
                    className="px-2 py-1 underline"
                    onClick={() => setOpen(false)}
                >
                    انصراف
                </button>
            </div>
        </form>
    );
}

export default function ProductsPage({
    products,
    hasCostProfile,
    isEmpty,
}: Props): ReactNode {
    return (
        <DashboardLayout title="محصولات همگام‌شده">
            <p className="mb-4 text-sm text-zinc-600">
                قیمت فروشگاه، کف هزینه، رقبا و توصیه — اعمال قیمت فقط در
                وردپرس.
            </p>

            {!hasCostProfile && (
                <p className="mb-4 border border-amber-200 bg-amber-50 p-3 text-sm">
                    بدون پروفایل هزینه، توصیه کف‌آگاه در دسترس نیست.
                </p>
            )}

            {isEmpty ? (
                <p className="text-sm text-zinc-600">
                    هنوز محصولی همگام نشده است. پس از اتصال افزونه و sync، اینجا
                    ظاهر می‌شوند.
                </p>
            ) : (
                <div className="overflow-x-auto border border-zinc-200 bg-white">
                    <table className="w-full min-w-[56rem] text-right text-sm">
                        <thead className="bg-zinc-100 text-zinc-600">
                            <tr>
                                <th className="px-3 py-2 font-medium">نام</th>
                                <th className="px-3 py-2 font-medium">SKU</th>
                                <th className="px-3 py-2 font-medium">
                                    قیمت فروشگاه
                                </th>
                                <th className="px-3 py-2 font-medium">رقبا</th>
                                <th className="px-3 py-2 font-medium">کف</th>
                                <th className="px-3 py-2 font-medium">توصیه</th>
                                <th className="px-3 py-2 font-medium">هزینه</th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.map((product) => (
                                <tr
                                    key={product.id}
                                    className="border-t border-zinc-100 align-top"
                                >
                                    <td className="px-3 py-2">
                                        <div>{product.name}</div>
                                        <div className="text-xs text-zinc-500">
                                            #{product.external_id}
                                            {product.below_floor && (
                                                <span className="mr-2 text-red-700">
                                                    زیر کف
                                                </span>
                                            )}
                                            {product.rivals_stale && (
                                                <span className="mr-2 text-amber-700">
                                                    رقیب کهنه/نیست
                                                </span>
                                            )}
                                            {product.cannot_match_profitably && (
                                                <span className="mr-2 text-red-700">
                                                    رقابت زیان‌ده
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {product.sku ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {product.price}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {product.rival ? (
                                            <div className="space-y-1 font-mono">
                                                <div>
                                                    ارزان:{' '}
                                                    {
                                                        product.rival
                                                            .cheapest_price
                                                    }
                                                </div>
                                                <div>
                                                    میانه:{' '}
                                                    {product.rival
                                                        .median_price ?? '—'}
                                                </div>
                                                <div>
                                                    تعداد:{' '}
                                                    {
                                                        product.rival
                                                            .competitor_count
                                                    }
                                                </div>
                                                <div className="font-sans text-zinc-500">
                                                    {product.rival.source}
                                                </div>
                                            </div>
                                        ) : product.pending_rival_match ? (
                                            <div className="space-y-2">
                                                <p className="font-sans text-zinc-600">
                                                    تأیید تطبیق:{' '}
                                                    {product.pending_rival_match
                                                        .listing_title ?? '—'}
                                                </p>
                                                <button
                                                    type="button"
                                                    className="bg-zinc-900 px-2 py-1 text-white"
                                                    onClick={() =>
                                                        router.post(
                                                            `/dashboard/products/${product.id}/rivals/${product.pending_rival_match!.id}/confirm`,
                                                        )
                                                    }
                                                >
                                                    تأیید یک‌ضرب
                                                </button>
                                            </div>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {product.floor_price ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {product.recommended_price ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="text-xs text-zinc-500">
                                            مستقیم:{' '}
                                            {product.direct_cost ?? '—'}
                                        </div>
                                        <ProductCostForm product={product} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </DashboardLayout>
    );
}
