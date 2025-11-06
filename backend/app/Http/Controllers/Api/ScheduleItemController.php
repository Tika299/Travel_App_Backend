<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduleItem;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScheduleItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $scheduleId = $request->query('schedule_id');
        
        $query = ScheduleItem::query();
        
        if ($scheduleId) {
            $query->where('schedule_id', $scheduleId);
        }
        
        $items = $query->orderBy('start_time')
            ->with(['schedule', 'details'])
            ->get();
        
        return response()->json($items);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'title' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'location' => 'nullable|string',
            'description' => 'nullable|string',
            'cost' => 'nullable|string|max:100',
            'weather' => 'nullable|string|max:100',
            'all_day' => 'boolean',
            'repeat' => 'string|in:none,daily,weekly,monthly,yearly',
            'order' => 'integer|min:0',
            'custom_fields' => 'nullable|array',
        ]);

        $item = ScheduleItem::create($validated);
        
        return response()->json($item->load(['schedule', 'details']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $item = ScheduleItem::with(['schedule', 'details'])->findOrFail($id);
        return response()->json($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $item = ScheduleItem::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'nullable|date|after:start_time',
            'location' => 'nullable|string',
            'description' => 'nullable|string',
            'cost' => 'nullable|string|max:100',
            'weather' => 'nullable|string|max:100',
            'all_day' => 'boolean',
            'repeat' => 'string|in:none,daily,weekly,monthly,yearly',
            'order' => 'integer|min:0',
            'custom_fields' => 'nullable|array',
        ]);

        $item->update($validated);
        
        return response()->json($item->load(['schedule', 'details']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $item = ScheduleItem::findOrFail($id);
        $item->delete();
        
        return response()->json(null, 204);
    }

    /**
     * Lấy sự kiện theo ngày
     */
    public function getByDate(Request $request)
    {
        $user = $request->user();
        $date = $request->query('date');
        $scheduleId = $request->query('schedule_id');
        
        $query = ScheduleItem::query();
        
        if ($scheduleId) {
            // Kiểm tra schedule thuộc về user hiện tại
            $schedule = Schedule::where('id', $scheduleId)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$schedule) {
                return response()->json(['error' => 'Không tìm thấy lịch trình'], 404);
            }
            
            $query->where('schedule_id', $scheduleId);
        } else {
            // Chỉ lấy schedule items của user hiện tại
            $query->whereHas('schedule', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        
        if ($date) {
            $query->forDate($date);
        }
        
        $items = $query->with(['schedule', 'details'])->get();
        
        return response()->json($items);
    }

    /**
     * Lấy sự kiện theo khoảng thời gian
     */
    public function getByDateRange(Request $request)
    {
        $user = $request->user();
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $scheduleId = $request->query('schedule_id');
        
        $query = ScheduleItem::query();
        
        if ($scheduleId) {
            // Kiểm tra schedule thuộc về user hiện tại
            $schedule = Schedule::where('id', $scheduleId)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$schedule) {
                return response()->json(['error' => 'Không tìm thấy lịch trình'], 404);
            }
            
            $query->where('schedule_id', $scheduleId);
        } else {
            // Chỉ lấy schedule items của user hiện tại
            $query->whereHas('schedule', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        
        if ($startDate && $endDate) {
            $query->betweenDates($startDate, $endDate);
        }
        
        $items = $query->with(['schedule', 'details'])->get();
        
        return response()->json($items);
    }
}
