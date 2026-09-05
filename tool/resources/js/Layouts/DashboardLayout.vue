<template>
    <div class="min-h-screen bg-gray-50 flex flex-col">

        <!-- ヘッダー -->
        <header class="bg-white border-b border-gray-200 shadow-sm flex-shrink-0 z-10 sticky top-0">
            <div class="flex items-center justify-between px-6 h-14">
                <!-- 左: ロゴ・タイトル -->
                <div class="flex items-center gap-3">
                    <Link href="/dashboard" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                        <div class="w-7 h-7 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <span class="text-base font-bold text-gray-800">{{ appTitle }}</span>
                    </Link>
                </div>

                <!-- 右: アカウントアイコン -->
                <div class="relative" ref="accountMenuRef">
                    <button
                        @click="toggleAccountMenu"
                        class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center hover:bg-purple-200 transition-colors focus:outline-none focus:ring-2 focus:ring-purple-400 focus:ring-offset-1"
                        :title="auth.user.name"
                    >
                        <span class="text-purple-700 font-semibold text-sm">{{ auth.user.name.charAt(0).toUpperCase() }}</span>
                    </button>

                    <!-- ポップアップメニュー -->
                    <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="transform scale-95 opacity-0"
                        enter-to-class="transform scale-100 opacity-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="transform scale-100 opacity-100"
                        leave-to-class="transform scale-95 opacity-0"
                    >
                        <div
                            v-if="accountMenuOpen"
                            class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50"
                        >
                            <!-- アカウント情報 -->
                            <div class="px-4 py-3 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-purple-700 font-semibold text-sm">{{ auth.user.name.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800 truncate">{{ auth.user.name }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ auth.user.email }}</p>
                                    </div>
                                </div>
                            </div>
                            <!-- ログアウト -->
                            <div class="py-1">
                                <button
                                    @click="logout"
                                    class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 hover:text-gray-800 transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    ログアウト
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </header>

        <!-- ボディ: サイドバー + コンテンツ -->
        <div class="flex flex-1 overflow-hidden">

            <!-- サイドバー -->
            <aside class="w-56 bg-white border-r border-gray-200 flex-shrink-0 overflow-y-auto">
                <nav class="px-3 py-4">
                    <ul class="space-y-1">
                        <li>
                            <Link
                                href="/master-import"
                                class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-gray-700 rounded-lg transition-colors"
                                :class="isCurrentPage('/master-import') ? 'bg-purple-50 text-purple-700 font-medium' : 'hover:bg-gray-50'"
                            >
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                マスターインポート
                            </Link>
                        </li>
                    </ul>
                </nav>
            </aside>

            <!-- メインコンテンツ -->
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-7xl mx-auto px-6 py-8">
                    <slot />
                </div>
            </main>

        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

defineProps({
    auth: Object,
});

const page = usePage();
const appName = page.props.app?.name ?? 'Nexus';
const appEnv  = page.props.app?.env  ?? 'local';
const appTitle = `${appName}-${appEnv}`;

const accountMenuOpen = ref(false);
const accountMenuRef  = ref(null);

const toggleAccountMenu = () => {
    accountMenuOpen.value = !accountMenuOpen.value;
};

// メニュー外クリックで閉じる
const handleClickOutside = (e) => {
    if (accountMenuRef.value && !accountMenuRef.value.contains(e.target)) {
        accountMenuOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});

const isCurrentPage = (path) => {
    return window.location.pathname === path;
};

const logout = () => {
    accountMenuOpen.value = false;
    router.post('/logout');
};
</script>

