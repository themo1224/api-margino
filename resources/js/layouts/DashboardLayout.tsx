import { Head, Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';

type AuthUser = {
    name: string;
    email: string;
};

type PageProps = {
    auth: {
        user: AuthUser | null;
    };
};

const nav = [
    { href: '/dashboard', label: 'خلاصه' },
    { href: '/dashboard/api-keys', label: 'کلید API' },
    { href: '/dashboard/costs', label: 'هزینه‌ها' },
    { href: '/dashboard/products', label: 'محصولات' },
    { href: '/dashboard/reports', label: 'گزارش‌ها' },
];

export default function DashboardLayout({
    title,
    children,
}: PropsWithChildren<{ title: string }>): ReactNode {
    const { auth } = usePage<PageProps>().props;

    return (
        <div className="min-h-screen bg-zinc-50 text-zinc-900">
            <Head title={title} />
            <header className="border-b border-zinc-200 bg-white">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-4">
                    <div>
                        <p className="text-lg font-semibold">ایران SaaS</p>
                        <p className="text-sm text-zinc-500">
                            {auth.user?.name} · {auth.user?.email}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="text-sm text-zinc-600 underline-offset-4 hover:underline"
                    >
                        خروج
                    </button>
                </div>
                <nav className="mx-auto flex max-w-5xl gap-4 overflow-x-auto px-4 pb-3 text-sm">
                    {nav.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className="whitespace-nowrap text-zinc-700 hover:text-zinc-950"
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            </header>
            <main className="mx-auto max-w-5xl px-4 py-8">
                <h1 className="mb-6 text-2xl font-semibold">{title}</h1>
                {children}
            </main>
        </div>
    );
}
