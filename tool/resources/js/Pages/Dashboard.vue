<template>
    <DashboardLayout :auth="auth">
        <div class="space-y-6">
            <!-- ウェルカムカード -->
            <div class="bg-white rounded-lg shadow-sm p-8">
                <h2 class="text-3xl font-semibold text-gray-800 mb-4">
                    ようこそ、{{ auth.user.name }}さん
                </h2>
                <p class="text-gray-600 text-lg">
                    運営ツールにログインしました。
                </p>
            </div>

            <!-- アクセス統計グラフ -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">API アクセス統計</h3>
                    
                    <!-- 期間選択ボタン -->
                    <div class="flex gap-2">
                        <button 
                            v-for="option in periodOptions" 
                            :key="option.value"
                            @click="changePeriod(option.value)"
                            :class="[
                                'px-4 py-2 rounded-lg font-medium transition-colors',
                                selectedPeriod === option.value
                                    ? 'bg-violet-600 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                            ]"
                        >
                            {{ option.label }}
                        </button>
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
                    
                    <!-- 期間選択ボタン -->
                    <div class="flex gap-2">
                        <button 
                            v-for="option in revenuePeriodOptions" 
                            :key="option.value"
                            @click="changeRevenuePeriod(option.value)"
                            :class="[
                                'px-4 py-2 rounded-lg font-medium transition-colors',
                                selectedRevenuePeriod === option.value
                                    ? 'bg-violet-600 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                            ]"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
                
                <div class="relative h-80">
                    <canvas ref="revenueChartCanvas"></canvas>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
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
    revenueStats: Object,
    currentPeriod: {
        type: String,
        default: '1day'
    },
    currentRevenuePeriod: {
        type: String,
        default: '1month'
    }
});

const chartCanvas = ref(null);
const revenueChartCanvas = ref(null);
let chartInstance = null;
let revenueChartInstance = null;

const selectedPeriod = ref(props.currentPeriod);
const selectedRevenuePeriod = ref(props.currentRevenuePeriod);

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

const changePeriod = (period) => {
    selectedPeriod.value = period;
    router.get('/dashboard', { period }, {
        preserveState: true,
        preserveScroll: true,
        only: ['accessStats', 'currentPeriod']
    });
};

const changeRevenuePeriod = (revenuePeriod) => {
    selectedRevenuePeriod.value = revenuePeriod;
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
    
    if (chartCanvas.value && props.accessStats) {
        chartInstance = new Chart(chartCanvas.value, {
            type: 'line',
            data: {
                labels: props.accessStats.labels,
                datasets: [{
                    label: 'アクセス数',
                    data: props.accessStats.data,
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
