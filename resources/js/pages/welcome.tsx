import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function Welcome(): ReactNode {
    return (
        <>
            <Head title="ایران SaaS" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-zinc-50 px-4 text-zinc-900">
                <main className="w-full max-w-lg space-y-6 text-center">
                    <h1 className="text-3xl font-semibold">ایران SaaS</h1>
                    <p className="text-zinc-600">
                        داشبورد فروشنده برای صدور کلید API، ثبت هزینه‌ها و مشاهده
                        توصیه قیمت. وردپرس فقط کانکتور است.
                    </p>
                    <div className="flex flex-wrap items-center justify-center gap-3">
                        <Link
                            href="/register"
                            className="bg-zinc-900 px-5 py-2 text-white"
                        >
                            ثبت‌نام
                        </Link>
                        <Link
                            href="/login"
                            className="border border-zinc-300 bg-white px-5 py-2"
                        >
                            ورود
                        </Link>
                        <Link
                            href="/dashboard"
                            className="text-sm underline underline-offset-4"
                        >
                            داشبورد
                        </Link>
                    </div>
                </main>
            </div>
        </>
    );
}
