<template>
    <DashboardLayout :auth="auth">
        <div class="p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">マスターデータインポート</h1>

            <!-- 未設定の警告 -->
            <div v-if="!is_configured" class="mb-6 bg-yellow-50 border border-yellow-300 rounded-lg p-4 flex items-start gap-3">
                <span class="text-yellow-500 text-xl">⚠️</span>
                <div>
                    <p class="font-semibold text-yellow-800">Google スプレッドシート連携が設定されていません</p>
                    <p class="text-sm text-yellow-700 mt-1">
                        <code class="bg-yellow-100 px-1 rounded">TOL_GOOGLE_SPREADSHEET_DIR</code> と
                        <code class="bg-yellow-100 px-1 rounded">TOL_GOOGLE_SERVICE_ACCOUNT_JSON</code>
                        を <code class="bg-yellow-100 px-1 rounded">.env</code> に設定してください。
                    </p>
                </div>
            </div>

            <div v-else class="space-y-6">

                <!-- STEP 1: スプレッドシート選択 -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-700 mb-4">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-700 text-sm font-bold mr-2">1</span>
                        スプレッドシートを選択
                    </h2>

                    <button
                        @click="loadSpreadsheets"
                        :disabled="loading.spreadsheets"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm font-medium"
                    >
                        {{ loading.spreadsheets ? '読み込み中...' : 'スプレッドシート一覧を取得' }}
                    </button>

                    <div v-if="spreadsheets.length > 0" class="mt-4">
                        <label class="block text-sm font-medium text-gray-600 mb-2">スプレッドシート</label>
                        <select
                            v-model="selectedSpreadsheetId"
                            @change="onSpreadsheetChange"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                            <option value="">-- 選択してください --</option>
                            <option v-for="ss in spreadsheets" :key="ss.id" :value="ss.id">
                                {{ ss.name }}
                                <span class="text-gray-400 text-xs">（更新: {{ formatDate(ss.modified_at) }}）</span>
                            </option>
                        </select>
                    </div>
                </div>

                <!-- STEP 2: シート選択 -->
                <div v-if="selectedSpreadsheetId" class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-700 mb-4">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-700 text-sm font-bold mr-2">2</span>
                        シートを選択
                        <span class="text-xs text-gray-400 font-normal ml-2">（シート名がテーブル名になります）</span>
                    </h2>

                    <div v-if="loading.sheets" class="text-sm text-gray-500">シート一覧を読み込み中...</div>

                    <div v-else-if="sheets.length > 0" class="space-y-2">
                        <div
                            v-for="sheet in sheets"
                            :key="sheet.sheet_id"
                            @click="selectSheet(sheet)"
                            :class="[
                                'flex items-center gap-3 px-4 py-3 rounded-lg border cursor-pointer transition-colors',
                                selectedSheet?.title === sheet.title
                                    ? 'border-purple-500 bg-purple-50'
                                    : 'border-gray-200 hover:border-purple-300 hover:bg-gray-50'
                            ]"
                        >
                            <span class="text-lg">📄</span>
                            <div class="flex-1">
                                <span class="font-medium text-gray-800">{{ sheet.title }}</span>
                                <span
                                    v-if="isMstTable(sheet.title)"
                                    class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"
                                >
                                    インポート可
                                </span>
                                <span
                                    v-else
                                    class="ml-2 text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full"
                                >
                                    mst_プレフィックスなし
                                </span>
                            </div>
                            <span v-if="selectedSheet?.title === sheet.title" class="text-purple-500">✓</span>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: プレビュー＆インポート実行 -->
                <div v-if="selectedSheet" class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-700 mb-4">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-700 text-sm font-bold mr-2">3</span>
                        プレビュー・実行
                    </h2>

                    <!-- プレビュー読み込み -->
                    <div v-if="loading.preview" class="text-sm text-gray-500 mb-4">プレビューを読み込み中...</div>

                    <!-- プレビューテーブル -->
                    <div v-else-if="preview" class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">
                                先頭 <strong>{{ preview.preview_rows }}</strong> 行を表示
                                （全 <strong>{{ preview.total_rows }}</strong> 行）
                            </p>
                            <span class="text-xs text-gray-400">テーブル: {{ selectedSheet.title }}</span>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            v-for="header in preview.headers"
                                            :key="header"
                                            class="px-3 py-2 text-left font-semibold text-gray-600 border-b border-gray-200 whitespace-nowrap"
                                        >
                                            {{ header }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, i) in preview.rows"
                                        :key="i"
                                        class="border-b border-gray-100 hover:bg-gray-50"
                                    >
                                        <td
                                            v-for="header in preview.headers"
                                            :key="header"
                                            class="px-3 py-2 text-gray-700 whitespace-nowrap max-w-xs truncate"
                                            :title="row[header]"
                                        >
                                            {{ row[header] || '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- インポート実行ボタン -->
                    <div class="flex items-center gap-4">
                        <button
                            @click="executeImport"
                            :disabled="loading.importing || !isMstTable(selectedSheet.title)"
                            class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-semibold"
                        >
                            {{ loading.importing ? 'インポート中...' : `「${selectedSheet.title}」をインポート` }}
                        </button>

                        <p v-if="!isMstTable(selectedSheet.title)" class="text-sm text-red-500">
                            ⚠️ シート名が <code class="bg-red-50 px-1 rounded">mst_</code> で始まらないためインポートできません
                        </p>
                    </div>
                </div>

                <!-- 実行結果 -->
                <div v-if="importResult" :class="[
                    'rounded-lg p-5 border',
                    importResult.status === 'success' ? 'bg-green-50 border-green-300' :
                    importResult.status === 'warning' ? 'bg-yellow-50 border-yellow-300' :
                                                        'bg-red-50 border-red-300'
                ]">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">
                            {{ importResult.status === 'success' ? '✅' : importResult.status === 'warning' ? '⚠️' : '❌' }}
                        </span>
                        <div class="flex-1">
                            <p :class="[
                                'font-semibold',
                                importResult.status === 'success' ? 'text-green-800' :
                                importResult.status === 'warning' ? 'text-yellow-800' : 'text-red-800'
                            ]">
                                {{ importResult.message }}
                            </p>

                            <div v-if="importResult.result" class="mt-3 text-sm space-y-1">
                                <p class="text-gray-700">
                                    インポート件数: <strong>{{ importResult.result.inserted }}</strong> 件
                                </p>
                                <p v-if="importResult.result.skipped > 0" class="text-yellow-700">
                                    スキップ: {{ importResult.result.skipped }} 件
                                </p>
                                <div v-if="importResult.result.errors?.length > 0" class="mt-2">
                                    <p class="font-medium text-red-700 mb-1">エラー詳細:</p>
                                    <ul class="list-disc list-inside space-y-0.5">
                                        <li v-for="(err, i) in importResult.result.errors" :key="i" class="text-red-600 text-xs">
                                            {{ err }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </DashboardLayout>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
    auth: Object,
    is_configured: Boolean,
});

// ステート
const spreadsheets         = ref([]);
const sheets               = ref([]);
const selectedSpreadsheetId = ref('');
const selectedSheet        = ref(null);
const preview              = ref(null);
const importResult         = ref(null);

const loading = ref({
    spreadsheets: false,
    sheets:       false,
    preview:      false,
    importing:    false,
});

// スプレッドシート一覧を取得
const loadSpreadsheets = async () => {
    loading.value.spreadsheets = true;
    importResult.value = null;
    try {
        const res = await axios.get('/master-import/spreadsheets');
        spreadsheets.value = res.data.spreadsheets;
    } catch (e) {
        alert('スプレッドシート一覧の取得に失敗しました: ' + (e.response?.data?.message ?? e.message));
    } finally {
        loading.value.spreadsheets = false;
    }
};

// スプレッドシート変更時にシート一覧を取得
const onSpreadsheetChange = async () => {
    if (!selectedSpreadsheetId.value) return;
    sheets.value     = [];
    selectedSheet.value = null;
    preview.value    = null;
    importResult.value = null;

    loading.value.sheets = true;
    try {
        const res = await axios.get('/master-import/sheets', {
            params: { spreadsheet_id: selectedSpreadsheetId.value },
        });
        sheets.value = res.data.sheets;
    } catch (e) {
        alert('シート一覧の取得に失敗しました: ' + (e.response?.data?.message ?? e.message));
    } finally {
        loading.value.sheets = false;
    }
};

// シートを選択してプレビューを取得
const selectSheet = async (sheet) => {
    selectedSheet.value = sheet;
    preview.value       = null;
    importResult.value  = null;

    if (!isMstTable(sheet.title)) return;

    loading.value.preview = true;
    try {
        const res = await axios.get('/master-import/preview', {
            params: {
                spreadsheet_id: selectedSpreadsheetId.value,
                sheet_title:    sheet.title,
            },
        });
        preview.value = res.data.preview;
    } catch (e) {
        alert('プレビューの取得に失敗しました: ' + (e.response?.data?.message ?? e.message));
    } finally {
        loading.value.preview = false;
    }
};

// インポート実行
const executeImport = async () => {
    if (!confirm(`「${selectedSheet.value.title}」テーブルの既存データを全て削除してインポートします。よろしいですか？`)) {
        return;
    }

    loading.value.importing = true;
    importResult.value = null;

    try {
        const res = await axios.post('/master-import/execute', {
            spreadsheet_id: selectedSpreadsheetId.value,
            sheet_title:    selectedSheet.value.title,
        });
        importResult.value = res.data;
    } catch (e) {
        importResult.value = {
            status:  'error',
            message: e.response?.data?.message ?? e.message,
            result:  null,
        };
    } finally {
        loading.value.importing = false;
    }
};

// シート名が mst_ で始まるか確認
const isMstTable = (title) => title.startsWith('mst_');

// ISO日付を読みやすい形式に変換
const formatDate = (isoString) => {
    if (!isoString) return '';
    return new Date(isoString).toLocaleString('ja-JP', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
};
</script>
