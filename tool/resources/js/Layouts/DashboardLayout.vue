<template>
    <div class="min-h-screen bg-gray-50 flex">
        <!-- サイドバー -->
        <aside class="w-64 bg-white shadow-lg flex flex-col">
            <!-- ロゴ/タイトル -->
            <div class="px-6 py-5 border-b border-gray-200">
                <h1 class="text-xl font-bold text-gray-800">運営ツール</h1>
            </div>

            <!-- ナビゲーション -->
            <nav class="flex-1 px-4 py-6 overflow-y-auto">
                <ul class="space-y-2">
                    <li>
                        <Link
                            href="/dashboard"
                            class="flex items-center px-4 py-3 text-gray-700 rounded-lg transition-colors duration-200"
                            :class="isCurrentPage('/dashboard') ? 'bg-purple-50 text-purple-700 font-medium' : 'hover:bg-gray-50'"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            ダッシュボード
                        </Link>
                    </li>
                    <li>
                        <Link
                            href="/master-import"
                            class="flex items-center px-4 py-3 text-gray-700 rounded-lg transition-colors duration-200"
                            :class="isCurrentPage('/master-import') ? 'bg-purple-50 text-purple-700 font-medium' : 'hover:bg-gray-50'"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            マスターインポート
                        </Link>
                    </li>
                </ul>
            </nav>

            <!-- ユーザー情報とログアウト -->
            <div class="px-4 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center min-w-0 flex-1">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-purple-700 font-medium text-sm">{{ auth.user.name.charAt(0) }}</span>
                        </div>
                        <div class="ml-3 min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-700 truncate">{{ auth.user.name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ auth.user.email }}</p>
                        </div>
                    </div>
                </div>
                <button
                    @click="logout"
                    class="w-full px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors duration-200"
                >
                    ログアウト
                </button>
            </div>
        </aside>

        <!-- メインコンテンツ -->
        <main class="flex-1 overflow-y-auto">
            <div class="max-w-7xl mx-auto px-6 py-8">
                <slot />
            </div>
        </main>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    auth: Object,
});

// 現在のページかどうかを判定
const isCurrentPage = (path) => {
    return window.location.pathname === path;
};

const logout = () => {
    router.post('/logout');
};
</script>
