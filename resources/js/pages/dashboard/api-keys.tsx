import { router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';

type ApiKeyRow = {
    id: number;
    name: string | null;
    prefix: string;
    revoked_at: string | null;
    created_at: string | null;
    is_active: boolean;
};

type Props = {
    keys: ApiKeyRow[];
    revealedKey: string | null;
};

export default function ApiKeysPage({
    keys,
    revealedKey,
}: Props): ReactNode {
    const form = useForm({
        name: '',
    });

    function issue(event: FormEvent): void {
        event.preventDefault();
        form.post('/dashboard/api-keys', { preserveScroll: true });
    }

    return (
        <DashboardLayout title="کلید API">
            <p className="mb-4 text-sm text-zinc-600">
                کلید را فقط یک‌بار پس از صدور می‌بینید. آن را در افزونه وردپرس
                جای‌گذاری کنید. صدور/باطل‌سازی اینجا انجام می‌شود؛ seeder فقط
                برای تست است.
            </p>

            {revealedKey && (
                <div className="mb-6 border border-emerald-300 bg-emerald-50 p-4 text-sm">
                    <p className="mb-2 font-medium">
                        کلید جدید (فقط همین یک‌بار نمایش داده می‌شود):
                    </p>
                    <code className="block break-all rounded bg-white p-3 text-xs">
                        {revealedKey}
                    </code>
                </div>
            )}

            <form onSubmit={issue} className="mb-8 flex flex-wrap gap-3">
                <input
                    type="text"
                    placeholder="نام کلید (اختیاری)"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    className="min-w-48 flex-1 border border-zinc-300 px-3 py-2 text-sm"
                />
                <button
                    type="submit"
                    disabled={form.processing}
                    className="bg-zinc-900 px-4 py-2 text-sm text-white disabled:opacity-50"
                >
                    صدور کلید جدید
                </button>
            </form>

            {keys.length === 0 ? (
                <p className="text-sm text-zinc-600">
                    هنوز کلیدی صادر نشده است.
                </p>
            ) : (
                <div className="overflow-x-auto border border-zinc-200 bg-white">
                    <table className="w-full min-w-[32rem] text-right text-sm">
                        <thead className="bg-zinc-100 text-zinc-600">
                            <tr>
                                <th className="px-3 py-2 font-medium">نام</th>
                                <th className="px-3 py-2 font-medium">پیشوند</th>
                                <th className="px-3 py-2 font-medium">وضعیت</th>
                                <th className="px-3 py-2 font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {keys.map((key) => (
                                <tr
                                    key={key.id}
                                    className="border-t border-zinc-100"
                                >
                                    <td className="px-3 py-2">
                                        {key.name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {key.prefix}…
                                    </td>
                                    <td className="px-3 py-2">
                                        {key.is_active ? 'فعال' : 'باطل‌شده'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {key.is_active && (
                                            <button
                                                type="button"
                                                className="text-red-700 underline-offset-2 hover:underline"
                                                onClick={() =>
                                                    router.delete(
                                                        `/dashboard/api-keys/${key.id}`,
                                                    )
                                                }
                                            >
                                                باطل کردن
                                            </button>
                                        )}
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
