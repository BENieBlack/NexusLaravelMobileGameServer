<template>
    <GuestLayout>
        <div class="w-full max-w-md">
            <div class="bg-white rounded-lg shadow-xl p-8">
                <h1 class="text-3xl font-bold text-center text-gray-800 mb-8">ログイン</h1>

                <!-- エラーメッセージ -->
                <div v-if="errors.email || errors.password" class="mb-6 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg text-sm">
                    <p v-if="errors.email">{{ errors.email }}</p>
                    <p v-if="errors.password">{{ errors.password }}</p>
                </div>

                <form @submit.prevent="submit">
                    <!-- メールアドレス -->
                    <div class="mb-5">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            メールアドレス
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                            required
                        />
                    </div>

                    <!-- パスワード -->
                    <div class="mb-5">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            パスワード
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                            required
                        />
                    </div>

                    <!-- ログイン状態を保持 -->
                    <div class="mb-6 flex items-center">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
                        />
                        <label for="remember" class="ml-2 text-sm text-gray-600">
                            ログイン状態を保持する
                        </label>
                    </div>

                    <!-- ログインボタン -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-semibold py-3 rounded-lg hover:from-purple-600 hover:to-indigo-700 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'ログイン中...' : 'ログイン' }}
                    </button>
                </form>
            </div>
        </div>
    </GuestLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    errors: Object,
});

const form = reactive({
    email: '',
    password: '',
    remember: false,
    processing: false,
});

const submit = () => {
    form.processing = true;
    router.post('/login', {
        email: form.email,
        password: form.password,
        remember: form.remember,
    }, {
        onFinish: () => {
            form.processing = false;
        },
    });
};
</script>
