<?php

namespace App\Services\Transport;

use App\Models\Employee;
use App\Models\Student;
use App\Models\StudentTransportAllocation;
use App\Models\TransportAttendance;
use App\Models\TransportDriver;
use App\Models\TransportFuelLog;
use App\Models\TransportGpsTelemetry;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportVehicle;
use App\Models\TransportVehicleMaintenance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransportService
{
    /**
     * Register a new vehicle in the fleet.
     */
    public function createVehicle(array $data): TransportVehicle
    {
        return TransportVehicle::create([
            'branch_id' => $data['branch_id'],
            'registration_number' => strtoupper(trim($data['registration_number'])),
            'vehicle_model' => $data['vehicle_model'],
            'capacity' => $data['capacity'] ?? 40,
            'fitness_certificate_expiry' => $data['fitness_certificate_expiry'] ?? null,
            'insurance_expiry' => $data['insurance_expiry'] ?? null,
            'gps_device_id' => $data['gps_device_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Register a driver.
     */
    public function registerDriver(array $data): TransportDriver
    {
        $employee = Employee::withoutGlobalScopes()->findOrFail($data['employee_id']);

        return TransportDriver::create([
            'employee_id' => $employee->id,
            'license_number' => strtoupper(trim($data['license_number'])),
            'license_expiry' => $data['license_expiry'],
            'phone' => $data['phone'] ?? $employee->phone,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Create a route with optional stops.
     */
    public function createRoute(array $data): TransportRoute
    {
        return DB::transaction(function () use ($data) {
            $route = TransportRoute::create([
                'branch_id' => $data['branch_id'],
                'route_name' => $data['route_name'],
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'start_point' => $data['start_point'],
                'end_point' => $data['end_point'],
                'fare' => $data['fare'] ?? 0.00,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (!empty($data['stops']) && is_array($data['stops'])) {
                foreach ($data['stops'] as $index => $stop) {
                    TransportStop::create([
                        'route_id' => $route->id,
                        'stop_name' => $stop['stop_name'],
                        'pickup_time' => $stop['pickup_time'],
                        'drop_time' => $stop['drop_time'],
                        'fare_amount' => $stop['fare_amount'] ?? $route->fare,
                        'stop_order' => $stop['stop_order'] ?? ($index + 1),
                    ]);
                }
            }

            return $route->fresh(['stops', 'vehicle', 'driver.employee']);
        });
    }

    /**
     * Add a bus stop to an existing route.
     */
    public function addStop(string $routeId, array $data): TransportStop
    {
        $route = TransportRoute::findOrFail($routeId);

        $order = $data['stop_order'] ?? ($route->stops()->count() + 1);

        return TransportStop::create([
            'route_id' => $route->id,
            'stop_name' => $data['stop_name'],
            'pickup_time' => $data['pickup_time'],
            'drop_time' => $data['drop_time'],
            'fare_amount' => $data['fare_amount'] ?? $route->fare,
            'stop_order' => $order,
        ]);
    }

    /**
     * Allocate a student to a transport route and stop.
     */
    public function allocateStudent(array $data): StudentTransportAllocation
    {
        return DB::transaction(function () use ($data) {
            $route = TransportRoute::with('vehicle')->findOrFail($data['route_id']);
            $stop = TransportStop::where('route_id', $route->id)->findOrFail($data['stop_id']);

            // Capacity check if vehicle is assigned
            if ($route->vehicle) {
                $activeAllocationsCount = StudentTransportAllocation::where('route_id', $route->id)
                    ->where('status', 'active')
                    ->count();

                if ($activeAllocationsCount >= $route->vehicle->capacity) {
                    throw new InvalidArgumentException("Route '{$route->route_name}' has reached vehicle capacity limit ({$route->vehicle->capacity} students).");
                }
            }

            // Check if student already has active allocation for session
            $existing = StudentTransportAllocation::where('student_id', $data['student_id'])
                ->where('academic_session_id', $data['academic_session_id'])
                ->first();

            if ($existing) {
                $existing->update([
                    'route_id' => $route->id,
                    'stop_id' => $stop->id,
                    'status' => 'active',
                ]);
                return $existing->fresh(['route', 'stop', 'student']);
            }

            return StudentTransportAllocation::create([
                'student_id' => $data['student_id'],
                'enrollment_id' => $data['enrollment_id'],
                'route_id' => $route->id,
                'stop_id' => $stop->id,
                'academic_session_id' => $data['academic_session_id'],
                'status' => 'active',
            ])->fresh(['route', 'stop', 'student']);
        });
    }

    /**
     * Log maintenance expense for vehicle.
     */
    public function logMaintenance(string $vehicleId, array $data): TransportVehicleMaintenance
    {
        $vehicle = TransportVehicle::findOrFail($vehicleId);

        return TransportVehicleMaintenance::create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => $data['maintenance_type'],
            'service_date' => $data['service_date'] ?? Carbon::today()->toDateString(),
            'odometer_reading' => $data['odometer_reading'] ?? null,
            'cost' => $data['cost'] ?? 0.00,
            'service_center' => $data['service_center'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Log fuel consumption and cost.
     */
    public function logFuel(string $vehicleId, array $data): TransportFuelLog
    {
        $vehicle = TransportVehicle::findOrFail($vehicleId);

        $quantity = (float) $data['quantity_litres'];
        $costPerLitre = isset($data['cost_per_litre']) ? (float) $data['cost_per_litre'] : 0.00;
        $totalCost = isset($data['total_cost']) ? (float) $data['total_cost'] : round($quantity * $costPerLitre, 2);

        return TransportFuelLog::create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => $data['fuel_date'] ?? Carbon::today()->toDateString(),
            'fuel_type' => $data['fuel_type'] ?? 'diesel',
            'quantity_litres' => $quantity,
            'cost_per_litre' => $costPerLitre,
            'total_cost' => $totalCost,
            'odometer_reading' => $data['odometer_reading'] ?? null,
            'receipt_number' => $data['receipt_number'] ?? null,
        ]);
    }

    /**
     * Record transport attendance for route students.
     */
    public function recordAttendance(string $routeId, array $records, string $tripType, string $date, ?string $userId = null): Collection
    {
        $route = TransportRoute::findOrFail($routeId);

        return DB::transaction(function () use ($route, $records, $tripType, $date, $userId) {
            $results = new Collection();

            foreach ($records as $item) {
                $attendance = TransportAttendance::updateOrCreate(
                    [
                        'route_id' => $route->id,
                        'student_id' => $item['student_id'],
                        'attendance_date' => $date,
                        'trip_type' => $tripType,
                    ],
                    [
                        'status' => $item['status'] ?? 'present',
                        'remarks' => $item['remarks'] ?? null,
                        'recorded_by' => $userId,
                    ]
                );

                $results->push($attendance);
            }

            return $results;
        });
    }

    /**
     * Telemetry ingestion hook for hardware/GPS devices.
     * Architectural interface without simulated mocks.
     */
    public function ingestGpsTelemetry(string $vehicleId, array $telemetryData): TransportGpsTelemetry
    {
        $vehicle = TransportVehicle::findOrFail($vehicleId);

        return TransportGpsTelemetry::create([
            'vehicle_id' => $vehicle->id,
            'latitude' => $telemetryData['latitude'],
            'longitude' => $telemetryData['longitude'],
            'speed_kph' => $telemetryData['speed_kph'] ?? 0.00,
            'heading' => $telemetryData['heading'] ?? null,
            'recorded_at' => $telemetryData['recorded_at'] ?? Carbon::now(),
        ]);
    }

    /**
     * Comprehensive fleet reports and compliance audit.
     */
    public function getTransportReports(string $branchId): array
    {
        $vehicles = TransportVehicle::where('branch_id', $branchId)->get();
        $totalVehicles = $vehicles->count();
        $totalCapacity = $vehicles->sum('capacity');

        $routes = TransportRoute::where('branch_id', $branchId)->withCount(['allocations' => fn($q) => $q->where('status', 'active')])->get();
        $totalAllocations = $routes->sum('allocations_count');

        // Document compliance
        $expiredFitness = $vehicles->filter(fn($v) => $v->isFitnessCertificateExpired())->values();
        $expiredInsurance = $vehicles->filter(fn($v) => $v->isInsuranceExpired())->values();

        // Expiring in next 30 days
        $expiringFitnessSoon = $vehicles->filter(function ($v) {
            if (!$v->fitness_certificate_expiry) return false;
            $exp = Carbon::parse($v->fitness_certificate_expiry);
            return $exp->isFuture() && $exp->diffInDays(Carbon::today()) <= 30;
        })->values();

        // Drivers compliance
        $drivers = TransportDriver::whereHas('routes', fn($q) => $q->where('branch_id', $branchId))->with('employee')->get();
        $expiredLicenses = $drivers->filter(fn($d) => $d->license_expiry && Carbon::parse($d->license_expiry)->isPast())->values();

        // Financial totals (fuel & maintenance)
        $vehicleIds = $vehicles->pluck('id');
        $totalFuelCost = TransportFuelLog::whereIn('vehicle_id', $vehicleIds)->sum('total_cost');
        $totalMaintenanceCost = TransportVehicleMaintenance::whereIn('vehicle_id', $vehicleIds)->sum('cost');

        return [
            'total_vehicles' => $totalVehicles,
            'total_capacity' => (int) $totalCapacity,
            'allocated_students' => (int) $totalAllocations,
            'capacity_utilization_pct' => $totalCapacity > 0 ? round(($totalAllocations / $totalCapacity) * 100, 2) : 0,
            'total_routes' => $routes->count(),
            'total_fuel_expenses' => (float) $totalFuelCost,
            'total_maintenance_expenses' => (float) $totalMaintenanceCost,
            'compliance_alerts' => [
                'fitness_expired_count' => $expiredFitness->count(),
                'insurance_expired_count' => $expiredInsurance->count(),
                'fitness_expiring_soon_count' => $expiringFitnessSoon->count(),
                'driver_licenses_expired_count' => $expiredLicenses->count(),
            ],
            'routes_summary' => $routes,
        ];
    }
}

