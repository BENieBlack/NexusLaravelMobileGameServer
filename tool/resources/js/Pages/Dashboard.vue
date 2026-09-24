<template>
    <DashboardLayout :auth="auth">
        <div class="grid grid-cols-2 gap-6">
            <!-- アクセス統計グラフ -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xl font-semibold text-gray-800">API アクセス統計</h3>
                        <span v-if="isAccessCalculating" class="flex items-center gap-1 text-xs text-amber-600 bg-amber-50 px-2 py-1 rounded-full">
                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            集計中
                        </span>
                    </div>

                    <!-- 期間設定ポップアップ -->
                    <div class="relative" ref="periodMenuRef">
                        <button
                            @click="togglePeriodMenu"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ periodOptions.find(o => o.value === selectedPeriod)?.label ?? '期間設定' }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <Transition
                            enter-active-class="transition duration-100 ease-out"
                            enter-from-class="transform scale-95 opacity-0"
                            enter-to-class="transform scale-100 opacity-100"
                            leave-active-class="transition duration-75 ease-in"
                            leave-from-class="transform scale-100 opacity-100"
                            leave-to-class="transform scale-95 opacity-0"
                        >
                            <div v-if="periodMenuOpen" class="absolute right-0 mt-1 w-32 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                                <button
                                    v-for="option in periodOptions"
                                    :key="option.value"
                                    @click="changePeriod(option.value)"
                                    :class="[
                                        'w-full text-left px-4 py-2 text-sm transition-colors',
                                        selectedPeriod === option.value
                                            ? 'text-purple-700 font-semibold bg-purple-50'
                                            : 'text-gray-700 hover:bg-gray-50'
                                    ]"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>

                <div class="relative h-80">
                    <canvas ref="chartCanvas"></canvas>
                </div>
            </div>

            <!-- 売上統計グラフ -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">売上統計（通貨別）</h3>

                    <!-- 期間設定ポップアップ -->
                    <div class="relative" ref="revenuePeriodMenuRef">
                        <button
                            @click="toggleRevenuePeriodMenu"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ revenuePeriodOptions.find(o => o.value === selectedRevenuePeriod)?.label ?? '期間設定' }}
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <Transition
                            enter-active-class="transition duration-100 ease-out"
                            enter-from-class="transform scale-95 opacity-0"
                            enter-to-class="transform scale-100 opacity-100"
                            leave-active-class="transition duration-75 ease-in"
                            leave-from-class="transform scale-100 opacity-100"
                            leave-to-class="transform scale-95 opacity-0"
                        >
                            <div v-if="revenuePeriodMenuOpen" class="absolute right-0 mt-1 w-32 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                                <button
                                    v-for="option in revenuePeriodOptions"
                                    :key="option.value"
                                    @click="changeRevenuePeriod(option.value)"
                                    :class="[
                                        'w-full text-left px-4 py-2 text-sm transition-colors',
                                        selectedRevenuePeriod === option.value
                                            ? 'text-purple-700 font-semibold bg-purple-50'
                                            : 'text-gray-700 hover:bg-gray-50'
                                    ]"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>

                <div class="relative h-80">
                    <canvas ref="revenueChartCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- 継続率テーブル -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold text-gray-800">継続率（日次コホート）</h3>
                <span v-if="isRetentionCalculating" class="flex items-center gap-1.5 text-xs text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full">
                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    集計中（自動更新）
                </span>
                <span v-else class="text-xs text-gray-400">キャッシュ済み</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 px-3 font-semibold text-gray-600 whitespace-nowrap">コホート日</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-600 whitespace-nowrap">新規</th>
                            <th v-for="d in retentionDays" :key="d" class="text-right py-2 px-3 font-semibold text-gray-600 whitespace-nowrap">D{{ d }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="retentionRows.length > 0">
                            <tr
                                v-for="row in retentionRows"
                                :key="row.cohort_date"
                                class="border-b border-gray-100 hover:bg-gray-50"
                            >
                                <td class="py-2 px-3 text-gray-700 whitespace-nowrap">{{ row.cohort_date }}</td>
                                <td class="py-2 px-3 text-right text-gray-700">{{ row.new_users.toLocaleString() }}</td>
                                <td v-for="d in retentionDays" :key="d" class="py-2 px-3 text-right">
                                    <RetentionCell :value="row[`d${d}`]" />
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td :colspan="2 + retentionDays.length" class="py-8 text-center">
                                <span v-if="isRetentionCalculating" class="text-amber-500 text-sm">集計中です。しばらくお待ちください...</span>
                                <span v-else class="text-gray-400 text-sm">データがありません</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-400">
                各日に初めてアクセスしたユーザーのうち、N日後に再訪した割合。24時間ごとにキャッシュ更新。
            </p>
        </div>
    </DashboardLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, defineComponent, h } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

// 継続率セル: 値に応じて色付け
const RetentionCell = defineComponent({
    props: { value: { type: Number, default: null } },
    setup(props) {
        return () => {
            if (props.value === null || props.value === undefined) {
                return h('span', { class: 'text-gray-300 text-xs' }, '—');
            }
            const v = props.value;
            const color = v >= 40 ? 'text-green-600 bg-green-50'
                        : v >= 20 ? 'text-yellow-600 bg-yellow-50'
                        :           'text-red-500 bg-red-50';
            return h('span', {
                class: `inline-block px-2 py-0.5 rounded text-xs font-semibold ${color}`
            }, `${v}%`);
        };
    },
});
import {
    Chart,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    LineController,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';

// Chart.jsのコンポーネントを登録
Chart.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    LineController,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    auth: Object,
    accessStats: Object,
    accessCalculating: { type: Boolean, default: false },
    revenueStats: Object,
    retentionStats: { type: Array, default: () => [] },
    retentionCalculating: { type: Boolean, default: false },
    currentPeriod: { type: String, default: '1month' },
    currentRevenuePeriod: { type: String, default: '1month' },
});

const chartCanvas = ref(null);
const revenueChartCanvas = ref(null);
let chartInstance = null;
let revenueChartInstance = null;

const selectedPeriod = ref(props.currentPeriod);
const selectedRevenuePeriod = ref(props.currentRevenuePeriod);

// 継続率テーブルの日数定義
const periodMenuOpen        = ref(false);
const revenuePeriodMenuOpen = ref(false);
const periodMenuRef         = ref(null);
const revenuePeriodMenuRef  = ref(null);

// ---- アクセス統計ポーリング ----
const accessStatsData         = ref(props.accessStats);
const isAccessCalculating     = ref(props.accessCalculating);
let accessPollTimer           = null;

const pollAccessStatus = async () => {
    try {
        const res = await axios.get('/dashboard/access-status', {
            params: { period: selectedPeriod.value }
        });
        accessStatsData.value      = { labels: res.data.labels, data: res.data.data };
        isAccessCalculating.value  = res.data.is_calculating;
        if (!res.data.is_calculating) {
            clearInterval(accessPollTimer);
            accessPollTimer = null;
            // グラフを再描画
            createChart();
        }
    } catch { /* ignore */ }
};

const startAccessPolling = () => {
    if (accessPollTimer) return;
    accessPollTimer = setInterval(pollAccessStatus, 5000);
};

// 期間変更時はキャッシュサービス経由で取得（Inertia ではなく直接API）
const changePeriod = (period) => {
    selectedPeriod.value = period;
    periodMenuOpen.value = false;
    isAccessCalculating.value = true;
    accessStatsData.value = { labels: [], data: [] };
    startAccessPolling();
    // Inertia でページ遷移して新しい期間のデータを取得
    router.get('/dashboard', { period, revenuePeriod: selectedRevenuePeriod.value }, {
        preserveState: true,
        preserveScroll: true,
        only: ['accessStats', 'accessCalculating', 'currentPeriod'],
        onSuccess: (page) => {
            accessStatsData.value     = page.props.accessStats;
            isAccessCalculating.value = page.props.accessCalculating;
            if (!page.props.accessCalculating) {
                if (accessPollTimer) { clearInterval(accessPollTimer); accessPollTimer = null; }
                createChart();
            }
        },
    });
};
const retentionDays = [1, 2, 3, 4, 5, 6, 7, 14, 30, 60, 90];

// ---- 継続率ポーリング ----
const retentionRows        = ref(props.retentionStats);
const isRetentionCalculating = ref(props.retentionCalculating);
let retentionPollTimer     = null;

const pollRetentionStatus = async () => {
    try {
        const res = await axios.get('/dashboard/retention-status');
        retentionRows.value          = res.data.rows;
        isRetentionCalculating.value = res.data.is_calculating;

        if (!res.data.is_calculating) {
            clearInterval(retentionPollTimer);
            retentionPollTimer = null;
        }
    } catch {
        // ポーリング失敗は無視
    }
};

const startPolling = () => {
    if (retentionPollTimer) return;
    retentionPollTimer = setInterval(pollRetentionStatus, 5000);
};

const togglePeriodMenu = () => {
    periodMenuOpen.value = !periodMenuOpen.value;
    revenuePeriodMenuOpen.value = false;
};

const toggleRevenuePeriodMenu = () => {
    revenuePeriodMenuOpen.value = !revenuePeriodMenuOpen.value;
    periodMenuOpen.value = false;
};

const handleClickOutside = (e) => {
    if (periodMenuRef.value && !periodMenuRef.value.contains(e.target)) {
        periodMenuOpen.value = false;
    }
    if (revenuePeriodMenuRef.value && !revenuePeriodMenuRef.value.contains(e.target)) {
        revenuePeriodMenuOpen.value = false;
    }
};

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    if (retentionPollTimer) clearInterval(retentionPollTimer);
    if (accessPollTimer)    clearInterval(accessPollTimer);
});

const periodOptions = [
    { value: '1day', label: '1日' },
    { value: '1week', label: '1週間' },
    { value: '2weeks', label: '2週間' },
    { value: '1month', label: '1ヶ月' },
    { value: '6months', label: '半年' },
    { value: '1year', label: '1年' },
    { value: 'all', label: '全期間' }
];

const revenuePeriodOptions = [
    { value: '1day', label: '1日' },
    { value: '1week', label: '1週間' },
    { value: '2weeks', label: '2週間' },
    { value: '1month', label: '1ヶ月' },
    { value: '6months', label: '半年' },
    { value: '1year', label: '1年' },
    { value: 'all', label: '全期間' }
];

const changeRevenuePeriod = (revenuePeriod) => {
    selectedRevenuePeriod.value = revenuePeriod;
    revenuePeriodMenuOpen.value = false;
    router.get('/dashboard', { revenuePeriod }, {
        preserveState: true,
        preserveScroll: true,
        only: ['revenueStats', 'currentRevenuePeriod']
    });
};

const createChart = () => {
    if (chartInstance) {
        chartInstance.destroy();
    }
    
    if (chartCanvas.value && accessStatsData.value) {
        chartInstance = new Chart(chartCanvas.value, {
            type: 'line',
            data: {
                labels: accessStatsData.value.labels,
                datasets: [{
                    label: 'アクセス数',
                    data: accessStatsData.value.data,
                    borderColor: 'rgb(139, 92, 246)',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: 'rgb(139, 92, 246)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgb(139, 92, 246)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            title: (context) => {
                                return `時刻: ${context[0].label}`;
                            },
                            label: (context) => {
                                return `アクセス数: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 12,
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                        },
                        ticks: {
                            precision: 0,
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }
};

const createRevenueChart = () => {
    if (revenueChartInstance) {
        revenueChartInstance.destroy();
    }
    
    if (revenueChartCanvas.value && props.revenueStats && props.revenueStats.datasets) {
        // 通貨別の色を定義
        const currencyColors = {
            'JPY': { border: 'rgb(239, 68, 68)', bg: 'rgba(239, 68, 68, 0.1)' },  // 赤
            'USD': { border: 'rgb(34, 197, 94)', bg: 'rgba(34, 197, 94, 0.1)' },  // 緑
            'EUR': { border: 'rgb(59, 130, 246)', bg: 'rgba(59, 130, 246, 0.1)' }  // 青
        };
        
        // データセットを作成
        const datasets = props.revenueStats.datasets.map(dataset => {
            const colors = currencyColors[dataset.currency] || { 
                border: 'rgb(107, 114, 128)', 
                bg: 'rgba(107, 114, 128, 0.1)' 
            };
            
            return {
                label: dataset.currency,
                data: dataset.data,
                borderColor: colors.border,
                backgroundColor: colors.bg,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: colors.border,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            };
        });
        
        revenueChartInstance = new Chart(revenueChartCanvas.value, {
            type: 'line',
            data: {
                labels: props.revenueStats.labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgb(139, 92, 246)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            title: (context) => {
                                return `時刻: ${context[0].label}`;
                            },
                            label: (context) => {
                                const currency = context.dataset.label;
                                const value = context.parsed.y;
                                return `${currency}: ${value.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 12,
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }
};

onMounted(() => {
    createChart();
    createRevenueChart();
    document.addEventListener('click', handleClickOutside);
    if (isAccessCalculating.value)    startAccessPolling();
    if (isRetentionCalculating.value) startPolling();
});

// accessStatsが変更されたらグラフを再作成
watch(() => props.accessStats, () => {
    createChart();
}, { deep: true });

// revenueStatsが変更されたらグラフを再作成
watch(() => props.revenueStats, () => {
    createRevenueChart();
}, { deep: true });

// currentPeriodが変更されたらselectedPeriodを更新
watch(() => props.currentPeriod, (newPeriod) => {
    selectedPeriod.value = newPeriod;
});

// currentRevenuePeriodが変更されたらselectedRevenuePeriodを更新
watch(() => props.currentRevenuePeriod, (newPeriod) => {
    selectedRevenuePeriod.value = newPeriod;
});
</script>
