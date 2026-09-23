import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

export default function Login(): ReactNode {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent): void {
        event.preventDefault();
        form.post('/login');
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-zinc-50 px-4">
            <Head title="ورود" />
            <form
                onSubmit={submit}
                className="w-full max-w-md space-y-4 border border-zinc-200 bg-white p-6"
            >
                <div>
                    <h1 className="text-xl font-semibold">ورود فروشنده</h1>
                    <p className="mt-1 text-sm text-zinc-500">
                        برای مدیریت کلید API و هزینه‌ها وارد شوید.
                    </p>
                </div>
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
                    <span className="mb-1 block">رمز عبور</span>
                    <input
                        type="password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                        className="w-full border border-zinc-300 px-3 py-2"
                        autoComplete="current-password"
                        required
                    />
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.remember}
                        onChange={(e) =>
                            form.setData('remember', e.target.checked)
                        }
                    />
                    مرا به خاطر بسپار
                </label>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full bg-zinc-900 px-4 py-2 text-white disabled:opacity-50"
                >
                    ورود
                </button>
                <p className="text-sm text-zinc-600">
                    حساب ندارید؟{' '}
                    <Link href="/register" className="underline">
                        ثبت‌نام
                    </Link>
                </p>
            </form>
        </div>
    );
}
