<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Welcome to evol">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
        <header class="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
            <nav class="flex items-center justify-end gap-4">
                <Link
                    v-if="$page.props?.auth?.user"
                    :href="dashboard()"
                    class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>
        <div class="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
            <main class="flex w-full max-w-[335px] flex-col-reverse overflow-hidden rounded-lg lg:max-w-4xl lg:flex-row">
                <div
                    class="flex-1 rounded-br-lg rounded-bl-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-tl-lg lg:rounded-br-none lg:p-20 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"
                >
                    <h1 class="mb-1 font-medium text-2xl flex items-center gap-2">
                        <span>Welcome to</span>
                        <span class="inline-block align-middle">
                            <svg width="60" height="32" viewBox="0 0 60 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="evol3d" x1="0" y1="0" x2="0" y2="32" gradientUnits="userSpaceOnUse">
                                        <stop stop-color="#F8B803"/>
                                        <stop offset="1" stop-color="#F53003"/>
                                    </linearGradient>
                                    <filter id="shadow" x="0" y="0" width="60" height="32" filterUnits="userSpaceOnUse">
                                        <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#000" flood-opacity="0.25"/>
                                    </filter>
                                </defs>
                                <text x="0" y="24" font-family="Inter, Arial, sans-serif" font-size="28" font-weight="bold" fill="url(#evol3d)" filter="url(#shadow)">evol</text>
                            </svg>
                        </span>
                    </h1>
                    <p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">
                        eVol lets organizations post volunteer opportunities that users can sign up for.<br />
                        Connect, contribute, and make a difference in your community!
                    </p>
                    <ul class="mb-4 flex flex-col lg:mb-6">
                        <li class="relative flex items-center gap-4 py-2">
                            <span class="relative bg-white py-1 dark:bg-[#161615]">
                                <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full border border-[#e3e3e0] bg-[#FDFDFC] shadow dark:border-[#3E3E3A] dark:bg-[#161615]">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A]" />
                                </span>
                            </span>
                            <span>
                                Learn more about eVol in the <a href="#" class="ml-1 font-medium text-[#f53003] underline underline-offset-4 dark:text-[#FF4433]">documentation</a>.
                            </span>
                        </li>
                        <li class="relative flex items-center gap-4 py-2">
                            <span class="relative bg-white py-1 dark:bg-[#161615]">
                                <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full border border-[#e3e3e0] bg-[#FDFDFC] shadow dark:border-[#3E3E3A] dark:bg-[#161615]">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A]" />
                                </span>
                            </span>
                            <span>
                                Ready to get started? <a href="#" class="ml-1 font-medium text-[#f53003] underline underline-offset-4 dark:text-[#FF4433]">Sign up or log in</a> to find or post opportunities.
                            </span>
                        </li>
                    </ul>
                </div>
            </main>
        </div>
        <div class="hidden h-14.5 lg:block"></div>
    </div>
</template>
