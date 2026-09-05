<template>
    <DashboardLayout :auth="auth">
        <div class="p-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">マスターデータインポート</h1>
                <a
                    v-if="folder_id"
                    :href="`https://drive.google.com/drive/folders/${folder_id}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-800 transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4" viewBox="0 0 87.3 78" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3L27.5 53H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066DA"/>
                        <path d="M43.65 25L29.9 1.2C28.55.4 27 0 25.45 0c-1.55 0-3.1.4-4.45 1.2L6.6 25h37.05z" fill="#00AC47"/>
                        <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75L86.1 57.5c.8-1.4 1.2-2.95 1.2-4.5H59.8L73.55 76.8z" fill="#EA4335"/>
                        <path d="M43.65 25L57.4 1.2C56.05.4 54.5 0 52.95 0H34.35c-1.55 0-3.1.4-4.45 1.2L43.65 25z" fill="#00832D"/>
                        <path d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.45 1.2h50.9c1.55 0 3.1-.4 4.45-1.2L59.8 53z" fill="#2684FC"/>
                        <path d="M73.4 26.5l-7.35-12.75c-1.35-2.35-3.5-4.1-6-5.05L43.65 25 59.8 53h26.25c0-1.55-.4-3.1-1.2-4.5L73.4 26.5z" fill="#FFBA00"/>
                    </svg>
                    Google Drive を開く
                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            </div>

            <!-- 未設定の警告 -->
            <div v-if="!is_configured" class="mb-6 bg-yellow-50 border border-yellow-300 rounded-lg p-4 flex items-start gap-3">
                <span class="text-yellow-500 text-xl">⚠️</span>
                <div>
                    <p class="font-semibold text-yellow-800">Google スプレッドシート連携が設定されていません</p>
                    <p class="text-sm text-yellow-700 mt-1">
                        <code class="bg-yellow-100 px-1 rounded">TOL_GOOGLE_SPREADSHEET_DIR</code> と
                        <code class="bg-yellow-100 px-1 rounded">TOL_GOOGLE_SERVICE_ACCOUNT_JSON_CONTENT</code>
                        を <code class="bg-yellow-100 px-1 rounded">.env</code> に設定してください。
                    </p>
                </div>
            </div>

            <div v-else class="space-y-6">

                <!-- シート一覧取得ボタン -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-700">取り込み対象シートを選択</h2>
                            <p class="text-sm text-gray-500 mt-1">
                                フォルダ内の全スプレッドシートからシート一覧を読み込みます。<br>
                                左側のシートを右側にダブルクリックで移動すると取り込み対象になります。
                            </p>
                            <!-- キャッシュ情報 -->
                            <p v-if="cachedAt" class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                キャッシュ: {{ cachedAt }} 取得
                                <span class="text-purple-400">（再取得するにはボタンを押してください）</span>
                            </p>
                        </div>
                        <button
                            @click="loadAllSheets"
                            :disabled="loading"
                            class="px-5 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm font-medium whitespace-nowrap"
                        >
                            {{ loading ? '読み込み中...' : (cachedAt ? '🔄 再取得' : '📥 シート一覧を取得') }}
                        </button>
                    </div>
                </div>

                <!-- デュアルリスト -->
                <div v-if="availableSheets.length > 0 || selectedSheets.length > 0" class="bg-white rounded-lg shadow p-6">
                    <div class="flex gap-4 items-stretch">

                        <!-- 左: 取り込み対象外 -->
                        <div class="flex-1 flex flex-col">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-gray-600">
                                    対象外シート
                                    <span class="ml-1 text-xs text-gray-400 font-normal">（{{ filteredAvailable.length }}件）</span>
                                </h3>
                                <button
                                    @click="addAll"
                                    class="text-xs text-purple-600 hover:text-purple-800 font-medium"
                                >全て追加 »»</button>
                            </div>
                            <!-- 検索 -->
                            <input
                                v-model="searchLeft"
                                type="text"
                                placeholder="シート名で絞り込み..."
                                class="mb-2 px-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            />
                            <div
                                class="border border-gray-200 rounded-lg overflow-y-auto bg-gray-50"
                                style="height: 400px;"
                            >
                                <div
                                    v-for="item in filteredAvailable"
                                    :key="item.key"
                                    @dblclick="addSheet(item)"
                                    :class="[
                                        'flex items-center gap-2 px-3 py-2 border-b border-gray-100 cursor-pointer select-none transition-colors',
                                        item.is_mst
                                            ? 'hover:bg-purple-50'
                                            : 'opacity-50 hover:bg-gray-100'
                                    ]"
                                    :title="item.is_mst ? 'ダブルクリックで追加' : 'mst_プレフィックスなし（インポート不可）'"
                                >
                                    <span class="text-xs">{{ item.is_mst ? '📄' : '⚠️' }}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-gray-800 truncate">{{ item.sheet_title }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ item.spreadsheet_name }}</p>
                                    </div>
                                </div>
                                <div v-if="filteredAvailable.length === 0" class="p-4 text-center text-xs text-gray-400">
                                    {{ searchLeft ? '一致するシートがありません' : 'すべて選択済みです' }}
                                </div>
                            </div>
                        </div>

                        <!-- 中央: 矢印ボタン -->
                        <div class="flex flex-col items-center justify-center gap-3 pt-16">
                            <button
                                @click="addSheet(hoveredLeft)"
                                :disabled="!hoveredLeft"
                                class="px-3 py-1.5 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 disabled:opacity-30 disabled:cursor-not-allowed text-sm font-bold transition-colors"
                                title="右へ移動"
                            >›</button>
                            <button
                                @click="removeSheet(hoveredRight)"
                                :disabled="!hoveredRight"
                                class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed text-sm font-bold transition-colors"
                                title="左へ戻す"
                            >‹</button>
                        </div>

                        <!-- 右: 取り込み対象 -->
                        <div class="flex-1 flex flex-col">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-gray-600">
                                    取り込み対象
                                    <span class="ml-1 text-xs text-gray-400 font-normal">（{{ selectedSheets.length }}件）</span>
                                </h3>
                                <button
                                    @click="removeAll"
                                    class="text-xs text-gray-500 hover:text-gray-700 font-medium"
                                >«« 全て戻す</button>
                            </div>
                            <!-- 検索 -->
                            <input
                                v-model="searchRight"
                                type="text"
                                placeholder="シート名で絞り込み..."
                                class="mb-2 px-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            />
                            <div
                                class="border-2 border-purple-200 rounded-lg overflow-y-auto bg-purple-50"
                                style="height: 400px;"
                            >
                                <div
                                    v-for="item in filteredSelected"
                                    :key="item.key"
                                    @dblclick="removeSheet(item)"
                                    class="flex items-center gap-2 px-3 py-2 border-b border-purple-100 cursor-pointer select-none hover:bg-purple-100 transition-colors"
                                    title="ダブルクリックで対象外に戻す"
                                >
                                    <span class="text-xs">📄</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-gray-800 truncate">{{ item.sheet_title }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ item.spreadsheet_name }}</p>
                                    </div>
                                    <span class="text-purple-400 text-xs">✓</span>
                                </div>
                                <div v-if="filteredSelected.length === 0" class="p-4 text-center text-xs text-gray-400">
                                    {{ searchRight ? '一致するシートがありません' : 'シートをダブルクリックで追加' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- インポート実行 -->
                <div v-if="selectedSheets.length > 0" class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-700">インポート実行</h2>
                        <span class="text-sm text-gray-500">{{ selectedSheets.length }} シートを対象</span>
                    </div>

                    <div class="mb-4 flex flex-wrap gap-2">
                        <span
                            v-for="item in selectedSheets"
                            :key="item.key"
                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-100 text-purple-800 text-xs rounded-full"
                        >
                            <span>{{ item.sheet_title }}</span>
                            <span class="text-purple-500 text-xs">/ {{ item.spreadsheet_name }}</span>
                        </span>
                    </div>

                    <button
                        @click="executeImport"
                        :disabled="importing"
                        class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-semibold"
                    >
                        {{ importing ? 'インポート中...' : `▶ ${selectedSheets.length}件のシートをインポート` }}
                    </button>
                </div>

                <!-- 実行結果 -->
                <div v-if="importResults.length > 0" class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-700">実行結果</h2>
                        <!-- インポート成功後にSQLiteエクスポートボタンを表示 -->
                        <button
                            v-if="importSuccessCount > 0"
                            @click="executeExport"
                            :disabled="exporting"
                            class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm font-semibold"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            {{ exporting ? 'SQLite生成中...' : 'SQLiteエクスポート & デプロイ登録' }}
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="(result, i) in importResults"
                            :key="i"
                            :class="[
                                'flex items-start gap-3 p-3 rounded-lg border text-sm',
                                result.status === 'success' ? 'bg-green-50 border-green-200' :
                                result.status === 'warning' ? 'bg-yellow-50 border-yellow-200' :
                                                              'bg-red-50 border-red-200'
                            ]"
                        >
                            <span>{{ result.status === 'success' ? '✅' : result.status === 'warning' ? '⚠️' : '❌' }}</span>
                            <div class="flex-1">
                                <p class="font-medium text-gray-800">{{ result.sheet_title }}</p>
                                <p class="text-xs text-gray-500">{{ result.spreadsheet_name }}</p>
                                <p class="mt-1 text-xs">{{ result.message }}</p>
                                <div v-if="result.details" class="mt-1 space-y-0.5">
                                    <p v-for="(d, j) in result.details" :key="j" class="text-xs text-gray-600">
                                        　{{ d.table }}: {{ d.inserted }}件取込 / {{ d.skipped_count }}件スキップ
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- エクスポート結果 -->
            <div v-if="exportResult" :class="[
                'rounded-lg p-5 border',
                exportResult.status === 'success' ? 'bg-blue-50 border-blue-300' : 'bg-red-50 border-red-300'
            ]">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">{{ exportResult.status === 'success' ? '📦' : '❌' }}</span>
                    <div class="flex-1">
                        <p class="font-semibold" :class="exportResult.status === 'success' ? 'text-blue-800' : 'text-red-800'">
                            {{ exportResult.message }}
                        </p>
                        <div v-if="exportResult.status === 'success'" class="mt-3 space-y-1 text-sm">
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-gray-700">
                                <p><span class="font-medium">ファイル名:</span> {{ exportResult.export.file_name }}</p>
                                <p><span class="font-medium">ハッシュ:</span> <code class="text-xs bg-gray-100 px-1 rounded">{{ exportResult.export.hash.slice(0, 16) }}...</code></p>
                                <p><span class="font-medium">ファイルサイズ:</span> {{ (exportResult.export.file_size / 1024).toFixed(1) }} KB</p>
                                <p><span class="font-medium">テーブル数:</span> {{ exportResult.export.table_count }}</p>
                                <p><span class="font-medium">deploy_key:</span> {{ exportResult.deploy.deploy_key }}</p>
                                <p><span class="font-medium">DLパス:</span>
                                    <a :href="exportResult.export.public_url" target="_blank" class="text-blue-600 underline text-xs">
                                        {{ exportResult.export.public_url }}
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
    auth: Object,
    is_configured: Boolean,
    folder_id: String,
    cached_sheets: Array,   // サーバー側のキャッシュファイルから渡される
    cached_at: String,      // キャッシュの作成日時
});

// ---- ステート ----
const allSheets      = ref([]);
const selectedSheets = ref([]);
const loading        = ref(false);
const importing      = ref(false);
const exporting      = ref(false);
const importResults  = ref([]);
const exportResult   = ref(null);
const searchLeft     = ref('');
const searchRight    = ref('');
const hoveredLeft    = ref(null);
const hoveredRight   = ref(null);
const cachedAt       = ref(props.cached_at ?? null);

// インポート成功件数（エクスポートボタン表示判定用）
const importSuccessCount = computed(() =>
    importResults.value.filter(r => r.status === 'success').length
);

// ---- キャッシュから初期ロード ----
onMounted(() => {
    if (props.cached_sheets && props.cached_sheets.length > 0) {
        allSheets.value = props.cached_sheets.map(s => ({
            ...s,
            key: `${s.spreadsheet_id}::${s.sheet_title}`,
        }));
    }
});

// 左リスト（選択済みを除いたもの）
const availableSheets = computed(() =>
    allSheets.value.filter(s => !selectedSheets.value.some(sel => sel.key === s.key))
);

const filteredAvailable = computed(() => {
    const q = searchLeft.value.toLowerCase();
    return q
        ? availableSheets.value.filter(s =>
            s.sheet_title.toLowerCase().includes(q) ||
            s.spreadsheet_name.toLowerCase().includes(q)
          )
        : availableSheets.value;
});

const filteredSelected = computed(() => {
    const q = searchRight.value.toLowerCase();
    return q
        ? selectedSheets.value.filter(s =>
            s.sheet_title.toLowerCase().includes(q) ||
            s.spreadsheet_name.toLowerCase().includes(q)
          )
        : selectedSheets.value;
});

// ---- API ----
const loadAllSheets = async () => {
    loading.value        = true;
    allSheets.value      = [];
    selectedSheets.value = [];
    importResults.value  = [];
    searchLeft.value     = '';
    searchRight.value    = '';

    try {
        const res = await axios.get('/master-import/all-sheets');
        allSheets.value = res.data.sheets.map(s => ({
            ...s,
            key: `${s.spreadsheet_id}::${s.sheet_title}`,
        }));
        cachedAt.value = res.data.cached_at ?? null;
    } catch (e) {
        alert('シート一覧の取得に失敗しました: ' + (e.response?.data?.message ?? e.message));
    } finally {
        loading.value = false;
    }
};

// ---- デュアルリスト操作 ----
const addSheet = (item) => {
    if (!item || !item.is_mst) return;
    if (selectedSheets.value.some(s => s.key === item.key)) return;
    selectedSheets.value.push(item);
};

const removeSheet = (item) => {
    if (!item) return;
    selectedSheets.value = selectedSheets.value.filter(s => s.key !== item.key);
};

const addAll = () => {
    availableSheets.value
        .filter(s => s.is_mst)
        .forEach(s => {
            if (!selectedSheets.value.some(sel => sel.key === s.key)) {
                selectedSheets.value.push(s);
            }
        });
};

const removeAll = () => {
    selectedSheets.value = [];
};

// ---- インポート実行 ----
const executeImport = async () => {
    if (!confirm(`${selectedSheets.value.length} 件のシートをインポートします。\n既存データは全て削除されます。よろしいですか？`)) {
        return;
    }

    importing.value     = true;
    importResults.value = [];

    for (const sheet of selectedSheets.value) {
        try {
            const res = await axios.post('/master-import/execute', {
                spreadsheet_id: sheet.spreadsheet_id,
                sheet_title:    sheet.sheet_title,
            });

            const data = res.data;
            importResults.value.push({
                status:           data.status,
                sheet_title:      sheet.sheet_title,
                spreadsheet_name: sheet.spreadsheet_name,
                message:          data.message,
                details:          data.results ?? (data.result ? [data.result] : null),
            });
        } catch (e) {
            importResults.value.push({
                status:           'error',
                sheet_title:      sheet.sheet_title,
                spreadsheet_name: sheet.spreadsheet_name,
                message:          e.response?.data?.message ?? e.message,
                details:          null,
            });
        }
    }

    importing.value = false;
};

// ---- SQLiteエクスポート & デプロイ登録 ----
const executeExport = async () => {
    if (!confirm('mstデータベースのSQLiteファイルを生成し、sys_deployに登録します。よろしいですか？')) return;

    exporting.value  = true;
    exportResult.value = null;

    try {
        const res = await axios.post('/master-import/export');
        exportResult.value = res.data;
    } catch (e) {
        exportResult.value = {
            status:  'error',
            message: e.response?.data?.message ?? e.message,
        };
    } finally {
        exporting.value = false;
    }
};
</script>
