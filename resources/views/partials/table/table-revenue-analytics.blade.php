{{-- resources/views/partials/dashboard/revenue-analytics.blade.php --}}
<div x-data="revenueAnalytics()" x-init="init()" class="space-y-6">

  <!-- Revenue Summary Cards -->
  <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
      <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">KES {{ number_format(array_sum($monthlyRevenue ?? []), 2) }}</p>
      <p class="mt-1 text-xs text-green-600 dark:text-green-400">All time</p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <p class="text-sm text-gray-500 dark:text-gray-400">Average Monthly</p>
      <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">KES {{ number_format(!empty($monthlyRevenue) ? array_sum($monthlyRevenue) / count($monthlyRevenue) : 0, 2) }}</p>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Last 12 months</p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <p class="text-sm text-gray-500 dark:text-gray-400">Payment Methods</p>
      <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ count($paymentMethods ?? []) }}</p>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Active payment channels</p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <p class="text-sm text-gray-500 dark:text-gray-400">Collection Rate</p>
      <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">{{ isset($stats['collection_rate']) ? number_format($stats['collection_rate'], 1) : '0' }}%</p>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Of total invoiced</p>
    </div>
  </div>

  <!-- Revenue Chart -->
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Monthly Revenue</h3>
      <div class="flex flex-wrap items-center gap-3 lg:justify-end">
        <span class="text-sm text-gray-500 dark:text-gray-400">Last 12 months</span>
        <button type="button" @click="toggleChartView" class="shadow-theme-xs inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
          <span x-text="chartView === 'bar' ? 'Switch to Line' : 'Switch to Bar'"></span>
        </button>
      </div>
    </div>
    <div class="p-5">
      <div class="h-64">
        <canvas id="revenueChart" x-ref="revenueChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Payment Methods Distribution -->
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Payment Methods Chart -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Payment Methods</h3>
      </div>
      <div class="p-5">
        <div class="h-64">
          <canvas id="paymentMethodsChart" x-ref="paymentMethodsChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Payment Methods Table -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Payment Method Breakdown</h3>
      </div>
      <div class="w-full overflow-x-auto">
        <table class="data-table w-full table-auto">
          <thead>
            <tr class="border-b border-gray-200 dark:border-gray-800">
              <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Method</th>
              <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Amount</th>
              <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Percentage</th>
            </tr>
          </thead>
          <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
            @php
              $totalPaymentMethods = array_sum($paymentMethods ?? []);
              $methodColors = [
                'wallet' => '#465FFF',
                'mpesa_stk' => '#10B981',
                'mpesa_paybill' => '#059669',
                'bank_transfer' => '#8B5CF6',
                'cash' => '#F59E0B',
                'manual_topup' => '#EF4444',
                'message_paste' => '#3B82F6'
              ];
            @endphp
            @forelse($paymentMethods ?? [] as $method => $amount)
              <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                <td class="p-4 whitespace-nowrap">
                  <span class="inline-flex items-center gap-2 text-sm font-medium text-gray-800 dark:text-white/90">
                    <span class="h-2 w-2 rounded-full" style="background: {{ $methodColors[$method] ?? '#6B7280' }}"></span>
                    {{ ucfirst(str_replace('_', ' ', $method)) }}
                  </span>
                </td>
                <td class="p-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">KES {{ number_format($amount, 2) }}</td>
                <td class="p-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">{{ $totalPaymentMethods > 0 ? number_format(($amount / $totalPaymentMethods) * 100, 1) : 0 }}%</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="px-4 py-12 text-center">
                  <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No payment data available</h3>
                </td>
              </tr>
            @endforelse
          </tbody>
          <tfoot>
            <tr class="border-t border-gray-200 dark:border-gray-800">
              <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">Total</td>
              <td class="p-4 whitespace-nowrap text-right text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($totalPaymentMethods, 2) }}</td>
              <td class="p-4 whitespace-nowrap text-right text-sm font-medium text-gray-800 dark:text-white/90">100%</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Monthly Revenue Summary Table -->
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Monthly Revenue Summary</h3>
    </div>
    <div class="w-full overflow-x-auto">
      <table class="data-table w-full table-auto">
        <thead>
          <tr class="border-b border-gray-200 dark:border-gray-800">
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Month</th>
            <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Revenue</th>
            <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Change</th>
            <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
          </tr>
        </thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
          @php
            $monthlyData = $monthlyRevenue ?? [];
            $previousMonth = null;
          @endphp
          @forelse($monthlyData as $month => $amount)
            @php
              $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $month);
              $change = $previousMonth !== null ? (($amount - $previousMonth) / max($previousMonth, 1)) * 100 : null;
              $previousMonth = $amount;
              $isPositive = $change !== null && $change >= 0;
            @endphp
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
              <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">{{ $monthDate->format('M Y') }}</td>
              <td class="p-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">KES {{ number_format($amount, 2) }}</td>
              <td class="p-4 whitespace-nowrap text-right text-sm">
                @if($change !== null)
                  <span class="{{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $isPositive ? '+' : '' }}{{ number_format($change, 1) }}%</span>
                @else
                  <span class="text-gray-400">—</span>
                @endif
              </td>
              <td class="p-4 whitespace-nowrap text-right">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $amount > 0 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">{{ $amount > 0 ? 'Collected' : 'No Revenue' }}</span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="px-4 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No revenue data available</h3>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>


<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('revenueAnalytics', () => ({
        chartView: 'bar',
        revenueChartInstance: null,
        paymentChartInstance: null,
        
        init() {
            console.log('Revenue Analytics initialized');
            
            // Wait for DOM to be ready
            this.$nextTick(() => {
                setTimeout(() => {
                    this.initRevenueChart();
                    this.initPaymentMethodsChart();
                }, 200);
            });
            
            // Watch for chart view changes
            this.$watch('chartView', () => {
                setTimeout(() => {
                    this.initRevenueChart();
                }, 100);
            });
        },
        
        toggleChartView() {
            this.chartView = this.chartView === 'bar' ? 'line' : 'bar';
        },
        
        getMethodColor(method) {
            const colors = {
                'wallet': '#465FFF',
                'mpesa_stk': '#10B981',
                'mpesa_paybill': '#059669',
                'bank_transfer': '#8B5CF6',
                'cash': '#F59E0B',
                'manual_topup': '#EF4444',
                'message_paste': '#3B82F6'
            };
            return colors[method] || '#6B7280';
        },
        
        initRevenueChart() {
            const canvas = this.$refs.revenueChart;
            if (!canvas) {
                console.warn('Revenue chart canvas not found');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            
            if (this.revenueChartInstance) {
                this.revenueChartInstance.destroy();
                this.revenueChartInstance = null;
            }
            
            const monthlyData = @json($monthlyRevenue ?? []);
            const labels = Object.keys(monthlyData);
            const values = Object.values(monthlyData);
            
            console.log('Revenue chart data:', { labels, values });
            
            if (values.length === 0 || values.every(v => v === 0)) {
                this.revenueChartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['No Data'],
                        datasets: [{
                            data: [0],
                            backgroundColor: ['#E5E7EB'],
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { 
                                callbacks: { 
                                    label: function() { return 'No revenue data available'; } 
                                } 
                            }
                        }
                    }
                });
                return;
            }
            
            const isBar = this.chartView === 'bar';
            
            this.revenueChartInstance = new Chart(ctx, {
                type: isBar ? 'bar' : 'line',
                data: {
                    labels: labels.map(label => {
                        const date = new Date(label + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Revenue',
                        data: values,
                        backgroundColor: isBar ? 'rgba(70, 95, 255, 0.7)' : 'rgba(70, 95, 255, 0.1)',
                        borderColor: '#465FFF',
                        borderWidth: isBar ? 0 : 3,
                        pointBackgroundColor: '#465FFF',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.3,
                        fill: !isBar,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'KES ' + context.raw.toLocaleString('en-KE', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'KES ' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
            
            console.log('Revenue chart created successfully');
        },
        
        initPaymentMethodsChart() {
            const canvas = this.$refs.paymentMethodsChart;
            if (!canvas) {
                console.warn('Payment methods chart canvas not found');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            
            if (this.paymentChartInstance) {
                this.paymentChartInstance.destroy();
                this.paymentChartInstance = null;
            }
            
            const paymentData = @json($paymentMethods ?? []);
            const labels = Object.keys(paymentData).map(key => 
                key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
            );
            const values = Object.values(paymentData);
            const colors = Object.keys(paymentData).map(key => this.getMethodColor(key));
            
            console.log('Payment methods data:', { labels, values, colors });
            
            if (values.length === 0 || values.every(v => v === 0)) {
                this.paymentChartInstance = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['No Data'],
                        datasets: [{
                            data: [1],
                            backgroundColor: ['#E5E7EB'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function() { return 'No payment data available'; }
                                }
                            }
                        }
                    }
                });
                return;
            }
            
            this.paymentChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 12,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return 'KES ' + context.parsed.toLocaleString('en-KE', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            console.log('Payment methods chart created successfully');
        }
    }));
});
</script>