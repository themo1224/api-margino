import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

export default function Register(): ReactNode {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        shop_name: '',
    });

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.post('/register');
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-zinc-50 px-4 py-8">
            <Head title="ثبت‌نام" />
            <form
                onSubmit={submit}
                className="w-full max-w-md space-y-4 border border-zinc-200 bg-white p-6"
            >
                <div>
                    <h1 className="text-xl font-semibold">ثبت‌نام فروشنده</h1>
                    <p className="mt-1 text-sm text-zinc-500">
                        یک فروشگاه Starter برای شما ساخته می‌شود.
                    </p>
                </div>
                <label className="block text-sm">
                    <span className="mb-1 block">نام</span>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        className="w-full border border-zinc-300 px-3 py-2"
                        required
                    />
                    {form.errors.name && (
                        <span className="mt-1 block text-sm text-red-600">
                            {form.errors.name}
                        </span>
                    )}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block">ایمیل</span>
                    <input
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        className="w-full border border-zinc-300 px-3 py-2"
                        autoComplete="username"
                        required
                    />
                    {form.errors.email && (
                        <span className="mt-1 block text-sm text-red-600">
                            {form.errors.email}
                        </span>
                    )}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block">نام فروشگاه (اختیاری)</span>
                    <input
                        type="text"
                        value={form.data.shop_name}
                        onChange={(e) =>
                            form.setData('shop_name', e.target.value)
                        }
                        className="w-full border border-zinc-300 px-3 py-2"
                    />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block">رمز عبور</span>
                    <input
                        type="password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                        className="w-full border border-zinc-300 px-3 py-2"
                        autoComplete="new-password"
                        required
                    />
                    {form.errors.password && (
                        <span className="mt-1 block text-sm text-red-600">
                            {form.errors.password}
                        </span>
                    )}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block">تکرار رمز عبور</span>
                    <input
                        type="password"
                        value={form.data.password_confirmation}
                        onChange={(e) =>
                            form.setData(
                                'password_confirmation',
                                e.target.value,
                            )
                        }
                        className="w-full border border-zinc-300 px-3 py-2"
                        autoComplete="new-password"
                        required
                    />
                </label>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full bg-zinc-900 px-4 py-2 text-white disabled:opacity-50"
                >
                    ایجاد حساب
                </button>
                <p className="text-sm text-zinc-600">
                    قبلاً ثبت‌نام کرده‌اید؟{' '}
                    <Link href="/login" className="underline">
                        ورود
                    </Link>
                </p>
            </form>
        </div>
    );
}
