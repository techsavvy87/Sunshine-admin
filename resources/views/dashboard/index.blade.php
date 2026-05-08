@extends('layouts.main')
@section('title', 'Dashboard')

@section('content')
<div class="flex items-center justify-between">
    <h3 class="text-lg font-medium">Business Overview</h3>
    <div class="breadcrumbs hidden p-0 text-sm sm:inline">
        <ul>
            <li><a href="./dashboards-ecommerce.html">PawPrints</a></li>
            <li>Dashboards</li>
        </ul>
    </div>
</div>
<div class="mt-6">
    <!--  Start: Dashboard Stats Widget -->

    <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-4">
        <div class="card bg-base-100 shadow">
            <div class="card-body gap-2">
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div>
                        <p class="text-base-content/80 font-medium">
                            Today's Revenue
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <p class="inline text-2xl font-semibold">
                                ${{ number_format($todayRevenue, 2) }}
                            </p>
                            @if($percentageChange != 0)
                            <div
                                class="badge badge-soft {{ $percentageChange > 0 ? 'badge-success' : 'badge-error' }} badge-sm gap-0.5 px-1 font-medium">
                                <span
                                    class="iconify lucide--arrow-{{ $percentageChange > 0 ? 'up' : 'down' }} size-3.5"></span>
                                {{ abs($percentageChange) }}%
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="bg-base-200 rounded-box flex items-center p-2">
                        <span class="iconify lucide--circle-dollar-sign size-5"></span>
                    </div>
                </div>
                <p class="text-base-content/60 text-sm">
                    vs.
                    <span class="mx-1">${{ number_format($yesterdayRevenue, 2) }}</span>
                    yesterday
                </p>
            </div>
        </div>
        <div class="card bg-base-100 shadow">
            <div class="card-body gap-2">
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div>
                        <p class="text-base-content/80 font-medium">
                            Today's Appointments
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <p class="inline text-2xl font-semibold">
                                {{ number_format($todayAppointments) }}
                            </p>
                            @if($appointmentPercentageChange != 0)
                            <div
                                class="badge badge-soft {{ $appointmentPercentageChange > 0 ? 'badge-success' : 'badge-error' }} badge-sm gap-0.5 px-1 font-medium">
                                <span
                                    class="iconify lucide--arrow-{{ $appointmentPercentageChange > 0 ? 'up' : 'down' }} size-3.5"></span>
                                {{ abs($appointmentPercentageChange) }}%
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="bg-base-200 rounded-box flex items-center p-2">
                        <span class="iconify lucide--calendar size-5"></span>
                    </div>
                </div>
                <p class="text-base-content/60 text-sm">
                    vs.
                    <span class="mx-1">{{ number_format($yesterdayAppointments) }}</span>
                    yesterday
                </p>
            </div>
        </div>
        <div class="card bg-base-100 shadow">
            <div class="card-body gap-2">
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div>
                        <p class="text-base-content/80 font-medium">
                            Customers
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <p class="inline text-2xl font-semibold">
                                {{ number_format($totalCustomers) }}
                            </p>
                            @if($customerPercentageChange != 0)
                            <div
                                class="badge badge-soft {{ $customerPercentageChange > 0 ? 'badge-success' : 'badge-error' }} badge-sm gap-0.5 px-1 font-medium">
                                <span
                                    class="iconify lucide--arrow-{{ $customerPercentageChange > 0 ? 'up' : 'down' }} size-3.5"></span>
                                {{ abs($customerPercentageChange) }}%
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="bg-base-200 rounded-box flex items-center p-2">
                        <span class="iconify lucide--users size-5"></span>
                    </div>
                </div>
                <p class="text-base-content/60 text-sm">
                    @if($todayNewCustomers > 0 || $yesterdayCustomers > 0)
                    {{ $todayNewCustomers }} new today
                    @if($yesterdayCustomers > 0)
                    vs. {{ $yesterdayCustomers }} yesterday
                    @endif
                    @else
                    Total registered customers
                    @endif
                </p>
            </div>
        </div>
        <div class="card bg-base-100 shadow">
            <div class="card-body gap-2">
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div>
                        <p class="text-base-content/80 font-medium">
                            Pets
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <p class="inline text-2xl font-semibold">
                                {{ number_format($totalPets) }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-base-200 rounded-box flex items-center p-2">
                        <span class="iconify lucide--brain-circuit size-5"></span>
                    </div>
                </div>
                <p class="text-base-content/60 text-sm">
                    Total registered pets
                </p>
            </div>
        </div>
        <div class="card bg-base-100 shadow">
            <div class="card-body gap-2">
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div>
                        <p class="text-base-content/80 font-medium">
                            Check-Ins
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <p class="inline text-2xl font-semibold">
                                {{ number_format($todayBoardingCheckins) }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-base-200 rounded-box flex items-center p-2">
                        <span class="iconify lucide--log-in size-5"></span>
                    </div>
                </div>
                <p class="text-base-content/60 text-sm">
                    Today: {{ number_format($todayBoardingCheckins) }} arrivals
                </p>
            </div>
        </div>
        <div class="card bg-base-100 shadow">
            <div class="card-body gap-2">
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div>
                        <p class="text-base-content/80 font-medium">
                            Check-Outs
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <p class="inline text-2xl font-semibold">
                                {{ number_format($todayBoardingCheckouts) }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-base-200 rounded-box flex items-center p-2">
                        <span class="iconify lucide--log-out size-5"></span>
                    </div>
                </div>
                <p class="text-base-content/60 text-sm">
                    Today: {{ number_format($todayBoardingCheckouts) }} departures
                </p>
            </div>
        </div>
        <div class="card bg-base-100 shadow">
            <div class="card-body gap-2">
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div>
                        <p class="text-base-content/80 font-medium">
                            Dogs on Property
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <p class="inline text-2xl font-semibold">
                                {{ number_format($dogsOnProperty) }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-base-200 rounded-box flex items-center p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-house-icon lucide-house">
                            <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" />
                            <path
                                d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        </svg>
                    </div>
                </div>
                <p class="text-base-content/60 text-sm">
                    Currently staying
                </p>
            </div>
        </div>
        @if(!empty($incidentReportServiceId))
        <a href="{{ route('list-incident-reports', ['serviceId' => $incidentReportServiceId]) }}"
            class="card bg-base-100 shadow transition-shadow hover:shadow-md">
            <div class="card-body gap-2">
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div>
                        <p class="text-base-content/80 font-medium">
                            Incidents
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <p class="inline text-2xl font-semibold">
                                {{ number_format($todayBoardingIncidents) }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-base-200 rounded-box flex items-center p-2">
                        <span class="iconify lucide--triangle-alert size-5"></span>
                    </div>
                </div>
                <p class="text-base-content/60 text-sm">
                    Today: {{ number_format($todayBoardingIncidents) }} incidents
                </p>
            </div>
        </a>
        @else
        <div class="card bg-base-100 shadow">
            <div class="card-body gap-2">
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div>
                        <p class="text-base-content/80 font-medium">
                            Incidents
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <p class="inline text-2xl font-semibold">
                                {{ number_format($todayBoardingIncidents) }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-base-200 rounded-box flex items-center p-2">
                        <span class="iconify lucide--triangle-alert size-5"></span>
                    </div>
                </div>
                <p class="text-base-content/60 text-sm">
                    Today: {{ number_format($todayBoardingIncidents) }} incidents
                </p>
            </div>
        </div>
        @endif
    </div>

    <!--  End: Dashboard Stats Widget -->

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-7">
            <!--  Start: Revenue Statistic -->

            <div class="card bg-base-100 shadow">
                <div class="card-body px-0 pb-0">
                    <div class="px-6">
                        <div class="flex items-start justify-between">
                            <span class="font-medium">
                                Revenue Statistics
                            </span>
                            <div class="tabs tabs-box tabs-xs hidden sm:block" id="revenue-period-tabs">
                                <a href="?period=day"
                                    class="tab {{ $period === 'day' ? 'tab-active' : '' }} px-3">Day</a>
                                <a href="?period=week"
                                    class="tab {{ $period === 'week' ? 'tab-active' : '' }} px-3">Week</a>
                                <a href="?period=month"
                                    class="tab {{ $period === 'month' ? 'tab-active' : '' }} px-3">Month</a>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center gap-3">
                                <span class="text-4xl font-semibold">
                                    ${{ number_format($revenueData['total'] / 1000, 2) }}K
                                </span>
                                @if($revenueData['percentageChange'] != 0)
                                <span
                                    class="{{ $revenueData['percentageChange'] > 0 ? 'text-success' : 'text-error' }} font-medium">
                                    {{ $revenueData['percentageChange'] > 0 ? '+' : '' }}{{ number_format($revenueData['percentageChange'], 2) }}%
                                </span>
                                @endif
                            </div>
                            <span class="text-base-content/60 text-sm">
                                Total income in this
                                {{ $period === 'day' ? 'week' : ($period === 'week' ? 'period' : 'year') }}
                            </span>
                        </div>
                    </div>
                    <div id="revenue-statics-chart"></div>
                </div>
            </div>

            <!--  End: Revenue Statistic -->
        </div>
        <div class="xl:col-span-5 space-y-6">

            <!--  Start: Recent Orders -->

            <div aria-label="Card" class="card bg-base-100 shadow">
                <div class="card-body p-0">
                    <div class="flex items-center gap-3 px-5 pt-5">
                        <span class="iconify lucide--calendar size-4.5"></span>
                        <span class="font-medium">Recent Appointments</span>
                        <a href="{{ route('appointments') }}" class="btn btn-outline border-base-300 btn-sm ms-auto">
                            <span class="iconify lucide--arrow-right size-3.5"></span>
                            View All
                        </a>
                    </div>
                    <div class="mt-2 overflow-auto">
                        <table class="table *:text-nowrap">
                            <thead>
                                <tr>
                                    <th>Pet</th>
                                    <th>Service</th>
                                    <th>Price</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentAppointments as $appointment)
                                <tr>
                                    <td>
                                        <div class="flex items-center space-x-3 truncate">
                                            <div class="flex -space-x-2 shrink-0">
                                                @foreach(($appointment->display_pets ?? collect()) as $petDisplay)
                                                    <img alt="pet image" class="mask mask-squircle bg-base-200 size-7.5"
                                                        src="{{ !empty($petDisplay['pet_img']) ? asset('storage/pets/'.$petDisplay['pet_img']) : asset('images/no_image.jpg') }}" />
                                                @endforeach
                                            </div>
                                            <p>{{ $appointment->display_pet_names ?? ($appointment->pet->name ?? 'N/A') }}</p>
                                        </div>
                                    </td>
                                    <td class="font-medium">{{ $appointment->service->name ?? 'N/A' }}</td>
                                    <td class="font-medium">${{ number_format($appointment->total_price ?? 0, 2) }}</td>
                                    <td class="text-xs">
                                        {{ $appointment->date ? \Carbon\Carbon::parse($appointment->date)->format('M j, Y') : 'N/A' }}
                                    </td>
                                    <td>
                                        @php
                                        $status = $appointment->status ?? '';
                                        $badgeClass = 'badge-secondary';
                                        if ($status === 'completed') {
                                        $badgeClass = 'badge-success';
                                        } elseif ($status === 'checked_in' || $status === 'in_progress') {
                                        $badgeClass = 'badge-info';
                                        } elseif ($status === 'cancelled' || $status === 'canceled') {
                                        $badgeClass = 'badge-error';
                                        } elseif ($status === 'confirmed') {
                                        $badgeClass = 'badge-primary';
                                        }
                                        @endphp
                                        <div class="badge {{ $badgeClass }} badge-sm badge-soft">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-1">
                                            <a href="{{ route('appointment-dashboard', $appointment->id) }}"
                                                aria-label="View appointment" class="btn btn-square btn-ghost btn-xs">
                                                <span class="iconify lucide--eye text-base-content/60 size-4"></span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-base-content/60">
                                        No recent appointments found.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!--  End: Recent Orders -->

            <div aria-label="Treatment List" class="card bg-base-100 shadow">
                <div class="card-body p-0">
                    <div class="flex items-center gap-3 px-5 pt-5">
                        <span class="font-medium">Treatment List ({{ $treatmentListItems->count() }})</span>
                        <a href="{{ route('boarding-process-log') }}"
                            class="btn btn-outline border-base-300 btn-sm ms-auto">
                            View All
                        </a>
                    </div>
                    <div class="mt-2 overflow-auto">
                        <table class="table *:text-nowrap">
                            <thead>
                                <tr>
                                    <th>Pet</th>
                                    <th>Treatment</th>
                                    <th>Detail</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($treatmentListItems->take(3) as $item)
                                <tr>
                                    <td class="flex items-center space-x-3 truncate">
                                        @if(!empty($item['pet_img']))
                                        <img alt="pet image" class="mask mask-squircle bg-base-200 size-7.5"
                                            src="{{ asset('storage/pets/' . $item['pet_img']) }}" />
                                        @else
                                        <div
                                            class="mask mask-squircle bg-base-200 size-7.5 flex items-center justify-center">
                                            <span class="iconify lucide--paw-print size-4 text-base-content/60"></span>
                                        </div>
                                        @endif
                                        <p>{{ $item['pet_name'] }}</p>
                                    </td>
                                    <td class="font-medium">{{ $item['treatment_label'] }}</td>
                                    <td class="text-base-content/70">
                                        {{ $item['detail'] !== '' ? \Illuminate\Support\Str::limit($item['detail'], 32) : 'Treatment detail' }}
                                    </td>
                                    <td>
                                        @php
                                        $status = $item['status'] ?? 'Pending';
                                        $statusBadgeClass = 'badge-warning';

                                        if ($status === 'Resolved') {
                                        $statusBadgeClass = 'badge-success';
                                        } elseif ($status === 'Escalate') {
                                        $statusBadgeClass = 'badge-error';
                                        } elseif ($status === 'Continue') {
                                        $statusBadgeClass = 'badge-warning';
                                        }
                                        @endphp
                                        <span class="badge {{ $statusBadgeClass }} badge-sm">{{ $status }}</span>
                                    </td>
                                    <td class="font-medium text-error">{{ $item['time'] }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-base-content/60">
                                        No treatment items scheduled for today.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($treatmentListItems->count() > 3)
                    <div class="border-t border-base-200 px-5 py-3 text-sm text-base-content/70">
                        +{{ $treatmentListItems->count() - 3 }} more treatments
                    </div>
                    @endif
                </div>
            </div>

            <div aria-label="Additional Services" class="card bg-base-100 shadow">
                <div class="card-body p-0">
                    <div class="flex items-center gap-3 px-5 pt-5">
                        <span class="font-medium">Additional Services (Checkout Day)</span>
                        @if($checkoutDayAdditionalServiceItems->count() > 0)
                        <span
                            class="badge badge-soft badge-primary badge-sm">{{ $checkoutDayAdditionalServiceItems->count() }}</span>
                        @endif
                        <a href="{{ route('appointments') }}" class="btn btn-outline border-base-300 btn-sm ms-auto">
                            View All
                        </a>
                    </div>
                    <div class="mt-2 overflow-auto">
                        <table class="table *:text-nowrap">
                            <thead>
                                <tr>
                                    <th>Pet</th>
                                    <th>Additional Service</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($checkoutDayAdditionalServiceItems->take(3) as $item)
                                <tr>
                                    <td class="flex items-center space-x-3 truncate">
                                        @if(!empty($item['pet_img']))
                                        <img alt="pet image" class="mask mask-squircle bg-base-200 size-7.5"
                                            src="{{ asset('storage/pets/' . $item['pet_img']) }}" />
                                        @else
                                        <div
                                            class="mask mask-squircle bg-base-200 size-7.5 flex items-center justify-center">
                                            <span class="iconify lucide--paw-print size-4 text-base-content/60"></span>
                                        </div>
                                        @endif
                                        <p>{{ $item['pet_name'] }}</p>
                                    </td>
                                    <td class="font-medium">{{ $item['service_name'] }}</td>
                                    <td class="font-medium text-error">{{ $item['time'] }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center py-8 text-base-content/60">
                                        No additional services scheduled for checkout today.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($checkoutDayAdditionalServiceItems->count() > 3)
                    <div class="border-t border-base-200 px-5 py-3 text-sm text-base-content/70">
                        +{{ $checkoutDayAdditionalServiceItems->count() - 3 }} more services
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

</div>
@endsection

@section('page-js')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/4.3.0/apexcharts.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/4.3.0/apexcharts.min.js"></script>
<script>
// Revenue Statistics Chart Data
const revenueChartData = @json($revenueData);

const initRevenueStatisticsChart = () => {
    const chartOptions = {
        chart: {
            height: 288,
            type: "bar",
            stacked: false,
            background: "transparent",
            toolbar: {
                show: false,
            },
        },
        plotOptions: {
            bar: {
                borderRadius: 8,
                borderRadiusApplication: "end",
                colors: {
                    backgroundBarColors: ["rgba(150,150,150,0.07)"],
                    backgroundBarRadius: 8,
                },
                columnWidth: "45%",
                barHeight: "100%",
            },
        },
        dataLabels: {
            enabled: false,
        },
        colors: ["#6c74f8"],
        series: [{
            name: "Revenue",
            data: revenueChartData.data,
        }, ],
        xaxis: {
            categories: revenueChartData.categories,
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            labels: {
                formatter: (val) => {
                    return val;
                },
            },
        },
        yaxis: {
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            labels: {
                formatter: (val) => {
                    return '$' + (val / 1000).toFixed(0) + 'K';
                },
            },
        },
        tooltip: {
            enabled: true,
            y: {
                formatter: (val) => {
                    return '$' + val.toFixed(2);
                },
            },
        },
        grid: {
            show: false,
        },
        responsive: [{
            breakpoint: 450,
            options: {
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                    },
                },
                xaxis: {
                    tickAmount: 3,
                },
            },
        }, ],
    };

    if (document.getElementById("revenue-statics-chart")) {
        new ApexCharts(document.getElementById("revenue-statics-chart"), chartOptions).render();
    }
};

// Initialize chart when page loads
document.addEventListener('DOMContentLoaded', function() {
    initRevenueStatisticsChart();
});
</script>
<script src="{{ asset('src/js/pages/dashboards/ecommerce.js') }}"></script>
@endsection