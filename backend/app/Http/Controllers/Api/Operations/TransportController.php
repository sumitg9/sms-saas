<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\TransportDriver;
use App\Models\TransportRoute;
use App\Models\TransportVehicle;
use App\Services\Transport\TransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TransportController extends Controller
{
    public function __construct(
        protected TransportService $transportService
    ) {}

    /**
     * List vehicles.
     */
    public function vehicles(Request $request): JsonResponse
    {
        $query = TransportVehicle::with(['routes.stops']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('registration_number', 'ilike', $search)
                  ->orWhere('vehicle_model', 'ilike', $search)
                  ->orWhere('gps_device_id', 'ilike', $search);
            });
        }

        $vehicles = $query->orderBy('registration_number')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $vehicles->items(),
            'meta' => [
                'current_page' => $vehicles->currentPage(),
                'last_page' => $vehicles->lastPage(),
                'total' => $vehicles->total(),
            ],
        ]);
    }

    /**
     * Store new vehicle.
     */
    public function storeVehicle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'registration_number' => 'required|string|max:50|unique:transport_vehicles,registration_number',
            'vehicle_model' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1|max:150',
            'fitness_certificate_expiry' => 'nullable|date',
            'insurance_expiry' => 'nullable|date',
            'gps_device_id' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $vehicle = $this->transportService->createVehicle($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle registered successfully.',
            'data' => $vehicle,
        ], 201);
    }

    /**
     * Show vehicle.
     */
    public function showVehicle(string $id): JsonResponse
    {
        $vehicle = TransportVehicle::with(['routes.stops', 'maintenances', 'fuelLogs'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $vehicle,
        ]);
    }

    /**
     * Update vehicle.
     */
    public function updateVehicle(Request $request, string $id): JsonResponse
    {
        $vehicle = TransportVehicle::findOrFail($id);

        $validated = $request->validate([
            'registration_number' => "sometimes|required|string|max:50|unique:transport_vehicles,registration_number,{$vehicle->id}",
            'vehicle_model' => 'sometimes|required|string|max:100',
            'capacity' => 'sometimes|required|integer|min:1|max:150',
            'fitness_certificate_expiry' => 'nullable|date',
            'insurance_expiry' => 'nullable|date',
            'gps_device_id' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $vehicle->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully.',
            'data' => $vehicle,
        ]);
    }

    /**
     * Register driver.
     */
    public function storeDriver(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id|unique:transport_drivers,employee_id',
            'license_number' => 'required|string|max:50',
            'license_expiry' => 'required|date',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $driver = $this->transportService->registerDriver($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transport driver registered successfully.',
            'data' => $driver->load('employee'),
        ], 201);
    }

    /**
     * List drivers.
     */
    public function drivers(Request $request): JsonResponse
    {
        $drivers = TransportDriver::with('employee')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $drivers->items(),
            'meta' => [
                'current_page' => $drivers->currentPage(),
                'last_page' => $drivers->lastPage(),
                'total' => $drivers->total(),
            ],
        ]);
    }

    /**
     * List routes.
     */
    public function routes(Request $request): JsonResponse
    {
        $query = TransportRoute::with(['stops', 'vehicle', 'driver.employee'])
            ->withCount(['allocations' => fn($q) => $q->where('status', 'active')]);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $routes = $query->orderBy('route_name')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $routes->items(),
            'meta' => [
                'current_page' => $routes->currentPage(),
                'last_page' => $routes->lastPage(),
                'total' => $routes->total(),
            ],
        ]);
    }

    /**
     * Store route with stops.
     */
    public function storeRoute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'route_name' => 'required|string|max:150',
            'vehicle_id' => 'nullable|uuid|exists:transport_vehicles,id',
            'driver_id' => 'nullable|uuid|exists:transport_drivers,id',
            'start_point' => 'required|string|max:150',
            'end_point' => 'required|string|max:150',
            'fare' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'stops' => 'nullable|array',
            'stops.*.stop_name' => 'required|string|max:150',
            'stops.*.pickup_time' => 'required',
            'stops.*.drop_time' => 'required',
            'stops.*.fare_amount' => 'nullable|numeric|min:0',
            'stops.*.stop_order' => 'nullable|integer',
        ]);

        $route = $this->transportService->createRoute($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transport route created successfully.',
            'data' => $route,
        ], 201);
    }

    /**
     * Add stop to route.
     */
    public function addStop(Request $request, string $routeId): JsonResponse
    {
        $validated = $request->validate([
            'stop_name' => 'required|string|max:150',
            'pickup_time' => 'required',
            'drop_time' => 'required',
            'fare_amount' => 'nullable|numeric|min:0',
            'stop_order' => 'nullable|integer',
        ]);

        $stop = $this->transportService->addStop($routeId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Stop added to route successfully.',
            'data' => $stop,
        ], 201);
    }

    /**
     * Allocate student to transport.
     */
    public function allocate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'enrollment_id' => 'required|uuid|exists:student_enrollments,id',
            'route_id' => 'required|uuid|exists:transport_routes,id',
            'stop_id' => 'required|uuid|exists:transport_stops,id',
            'academic_session_id' => 'required|uuid|exists:academic_sessions,id',
        ]);

        try {
            $allocation = $this->transportService->allocateStudent($validated);

            return response()->json([
                'success' => true,
                'message' => 'Student allocated to transport route successfully.',
                'data' => $allocation,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Log maintenance.
     */
    public function logMaintenance(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'maintenance_type' => 'required|string|max:50',
            'service_date' => 'nullable|date',
            'odometer_reading' => 'nullable|integer|min:0',
            'cost' => 'required|numeric|min:0',
            'service_center' => 'nullable|string|max:150',
            'invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $maintenance = $this->transportService->logMaintenance($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance record logged successfully.',
            'data' => $maintenance,
        ], 201);
    }

    /**
     * Log fuel.
     */
    public function logFuel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'fuel_date' => 'nullable|date',
            'fuel_type' => 'nullable|string|max:30',
            'quantity_litres' => 'required|numeric|min:0.1',
            'cost_per_litre' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'odometer_reading' => 'nullable|integer|min:0',
            'receipt_number' => 'nullable|string|max:100',
        ]);

        $fuelLog = $this->transportService->logFuel($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Fuel consumption logged successfully.',
            'data' => $fuelLog,
        ], 201);
    }

    /**
     * Transport attendance.
     */
    public function attendance(Request $request, string $routeId): JsonResponse
    {
        $validated = $request->validate([
            'attendance_date' => 'required|date',
            'trip_type' => 'required|string|in:pickup,drop',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|uuid|exists:students,id',
            'records.*.status' => 'required|string|in:present,absent,skipped',
            'records.*.remarks' => 'nullable|string|max:255',
        ]);

        $attendances = $this->transportService->recordAttendance(
            $routeId,
            $validated['records'],
            $validated['trip_type'],
            $validated['attendance_date'],
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Transport attendance recorded successfully.',
            'data' => $attendances,
        ]);
    }

    /**
     * GPS telemetry ingestion hook.
     */
    public function telemetry(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kph' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'recorded_at' => 'nullable|date',
        ]);

        $telemetry = $this->transportService->ingestGpsTelemetry($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'GPS telemetry ingested successfully.',
            'data' => $telemetry,
        ], 201);
    }

    /**
     * Fleet reports and compliance.
     */
    public function reports(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'branch_id query parameter is required.',
            ], 422);
        }

        $reports = $this->transportService->getTransportReports($branchId);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }
}

