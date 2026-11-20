<?php

namespace App\Services\Hostel;

use App\Models\HostelAllocation;
use App\Models\HostelAttendance;
use App\Models\HostelBuilding;
use App\Models\HostelComplaint;
use App\Models\HostelMessMenu;
use App\Models\HostelRoom;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HostelService
{
    /**
     * Create a new hostel building.
     */
    public function createBuilding(array $data): HostelBuilding
    {
        return HostelBuilding::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'building_type' => $data['building_type'] ?? 'boys',
            'warden_name' => $data['warden_name'] ?? null,
            'warden_phone' => $data['warden_phone'] ?? null,
        ]);
    }

    /**
     * Create a new hostel room.
     */
    public function createRoom(array $data): HostelRoom
    {
        $building = HostelBuilding::findOrFail($data['building_id']);

        return HostelRoom::create([
            'building_id' => $building->id,
            'room_number' => strtoupper(trim($data['room_number'])),
            'room_type' => $data['room_type'] ?? 'double',
            'capacity' => $data['capacity'] ?? 2,
            'occupied_beds' => 0,
            'fee_per_session' => $data['fee_per_session'] ?? 0.00,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Allocate a student to a room and bed.
     */
    public function allocateStudent(array $data): HostelAllocation
    {
        return DB::transaction(function () use ($data) {
            $room = HostelRoom::with('building')->findOrFail($data['room_id']);
            $student = Student::withoutGlobalScopes()->findOrFail($data['student_id']);

            // Gender compatibility check
            $buildingType = $room->building->building_type;
            if ($buildingType === 'boys' && strtolower($student->gender) === 'female') {
                throw new InvalidArgumentException("Female student cannot be allocated to a Boys hostel.");
            }
            if ($buildingType === 'girls' && strtolower($student->gender) === 'male') {
                throw new InvalidArgumentException("Male student cannot be allocated to a Girls hostel.");
            }

            // Room capacity check
            if ($room->occupied_beds >= $room->capacity) {
                throw new InvalidArgumentException("Room '{$room->room_number}' has reached full capacity ({$room->capacity} beds).");
            }

            // Check if student already has active allocation
            $existing = HostelAllocation::where('student_id', $student->id)
                ->where('status', 'active')
                ->first();

            if ($existing) {
                throw new InvalidArgumentException("Student already has an active hostel room allocation.");
            }

            $allocation = HostelAllocation::create([
                'room_id' => $room->id,
                'student_id' => $student->id,
                'enrollment_id' => $data['enrollment_id'],
                'bed_number' => $data['bed_number'] ?? null,
                'allocation_date' => $data['allocation_date'] ?? Carbon::today()->toDateString(),
                'status' => 'active',
            ]);

            $room->syncOccupancy();

            return $allocation->fresh(['room.building', 'student']);
        });
    }

    /**
     * Vacate student from room.
     */
    public function vacateStudent(string $allocationId, ?string $vacatingDate = null): HostelAllocation
    {
        return DB::transaction(function () use ($allocationId, $vacatingDate) {
            $allocation = HostelAllocation::with('room')->findOrFail($allocationId);

            if ($allocation->status === 'vacated') {
                throw new InvalidArgumentException("This room allocation is already vacated.");
            }

            $allocation->update([
                'status' => 'vacated',
                'vacating_date' => $vacatingDate ?? Carbon::today()->toDateString(),
            ]);

            $allocation->room->syncOccupancy();

            return $allocation->fresh(['room.building', 'student']);
        });
    }

    /**
     * Record daily hostel attendance.
     */
    public function recordAttendance(string $buildingId, array $records, string $date, ?string $userId = null): Collection
    {
        $building = HostelBuilding::findOrFail($buildingId);

        return DB::transaction(function () use ($building, $records, $date, $userId) {
            $results = new Collection();

            foreach ($records as $item) {
                $att = HostelAttendance::updateOrCreate(
                    [
                        'building_id' => $building->id,
                        'student_id' => $item['student_id'],
                        'attendance_date' => $date,
                    ],
                    [
                        'status' => $item['status'] ?? 'present',
                        'remarks' => $item['remarks'] ?? null,
                        'marked_by' => $userId,
                    ]
                );

                $results->push($att);
            }

            return $results;
        });
    }

    /**
     * Set or update weekly mess menu.
     */
    public function setMessMenu(string $buildingId, array $data): HostelMessMenu
    {
        $building = HostelBuilding::findOrFail($buildingId);

        return HostelMessMenu::updateOrCreate(
            [
                'building_id' => $building->id,
                'day_of_week' => strtolower($data['day_of_week']),
                'meal_type' => strtolower($data['meal_type']),
            ],
            [
                'menu_items' => $data['menu_items'],
                'special_notes' => $data['special_notes'] ?? null,
            ]
        );
    }

    /**
     * File a complaint.
     */
    public function fileComplaint(array $data): HostelComplaint
    {
        return HostelComplaint::create([
            'building_id' => $data['building_id'],
            'room_id' => $data['room_id'] ?? null,
            'student_id' => $data['student_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'category' => $data['category'] ?? 'other',
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'pending',
        ]);
    }

    /**
     * Resolve a complaint.
     */
    public function resolveComplaint(string $complaintId, array $data, ?string $userId = null): HostelComplaint
    {
        $complaint = HostelComplaint::findOrFail($complaintId);

        $complaint->update([
            'status' => $data['status'] ?? 'resolved',
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'resolved_at' => Carbon::now(),
            'resolved_by' => $userId,
        ]);

        return $complaint->fresh(['resolver', 'student', 'building']);
    }

    /**
     * Hostel analytics and room availability.
     */
    public function getHostelReports(string $branchId): array
    {
        $buildings = HostelBuilding::where('branch_id', $branchId)->with(['rooms.activeAllocations'])->get();

        $totalRooms = 0;
        $totalCapacity = 0;
        $totalOccupied = 0;

        foreach ($buildings as $building) {
            foreach ($building->rooms as $room) {
                $totalRooms++;
                $totalCapacity += $room->capacity;
                $totalOccupied += $room->occupied_beds;
            }
        }

        $totalVacant = max(0, $totalCapacity - $totalOccupied);
        $occupancyPct = $totalCapacity > 0 ? round(($totalOccupied / $totalCapacity) * 100, 2) : 0.0;

        // Complaints summary
        $buildingIds = $buildings->pluck('id');
        $pendingComplaints = HostelComplaint::whereIn('building_id', $buildingIds)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $resolvedComplaints = HostelComplaint::whereIn('building_id', $buildingIds)
            ->where('status', 'resolved')
            ->count();

        return [
            'total_buildings' => $buildings->count(),
            'total_rooms' => $totalRooms,
            'total_capacity' => $totalCapacity,
            'occupied_beds' => $totalOccupied,
            'vacant_beds' => $totalVacant,
            'occupancy_percentage' => $occupancyPct,
            'pending_complaints_count' => $pendingComplaints,
            'resolved_complaints_count' => $resolvedComplaints,
            'buildings_breakdown' => $buildings,
        ];
    }
}

