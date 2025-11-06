<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduleDetail;
use App\Models\ScheduleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScheduleDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $scheduleId = $request->query('schedule_id');
        $scheduleItemId = $request->query('schedule_item_id');
        $type = $request->query('type');
        
        $query = ScheduleDetail::query();
        
        if ($scheduleId) {
            $query->where('schedule_id', $scheduleId);
        }
        
        if ($scheduleItemId) {
            $query->where('schedule_item_id', $scheduleItemId);
        }
        
        if ($type) {
            $query->ofType($type);
        }
        
        $details = $query->with(['schedule', 'scheduleItem'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at')
            ->get();
        
        return response()->json($details);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'schedule_item_id' => 'required|exists:schedule_items,id',
            'type' => 'required|string|in:transport,accommodation,activity,note,reminder',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'status' => 'string|in:pending,completed,cancelled',
            'cost' => 'nullable|numeric|min:0',
            'currency' => 'string|max:10',
            'due_date' => 'nullable|date',
            'priority' => 'integer|min:1|max:5',
            'attachments' => 'nullable|array',
            'tags' => 'nullable|array',
        ]);

        // Kiểm tra schedule thuộc về user hiện tại
        $schedule = \App\Models\Schedule::where('id', $validated['schedule_id'])
            ->where('user_id', $user->id)
            ->first();
            
        if (!$schedule) {
            return response()->json(['error' => 'Không có quyền truy cập lịch trình này'], 403);
        }

        // Kiểm tra schedule item thuộc về user hiện tại
        $scheduleItem = ScheduleItem::whereHas('schedule', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $validated['schedule_item_id'])->first();
        
        if (!$scheduleItem) {
            return response()->json(['error' => 'Không có quyền truy cập sự kiện này'], 403);
        }

        $detail = ScheduleDetail::create($validated);
        
        return response()->json($detail->load(['schedule', 'scheduleItem']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        
        $detail = ScheduleDetail::with(['schedule', 'scheduleItem'])
            ->whereHas('schedule', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->findOrFail($id);
            
        return response()->json($detail);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        
        $detail = ScheduleDetail::whereHas('schedule', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($id);
        
        $validated = $request->validate([
            'type' => 'sometimes|required|string|in:transport,accommodation,activity,note,reminder',
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string',
            'status' => 'string|in:pending,completed,cancelled',
            'cost' => 'nullable|numeric|min:0',
            'currency' => 'string|max:10',
            'due_date' => 'nullable|date',
            'priority' => 'integer|min:1|max:5',
            'attachments' => 'nullable|array',
            'tags' => 'nullable|array',
        ]);

        $detail->update($validated);
        
        return response()->json($detail->load(['schedule', 'scheduleItem']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        
        $detail = ScheduleDetail::whereHas('schedule', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($id);
        
        $detail->delete();
        
        return response()->json(null, 204);
    }

    /**
     * Lấy chi tiết theo loại
     */
    public function getByType(Request $request)
    {
        $user = $request->user();
        $type = $request->query('type');
        $scheduleId = $request->query('schedule_id');
        
        if (!$type) {
            return response()->json(['error' => 'Type is required'], 400);
        }
        
        $query = ScheduleDetail::query();
        
        if ($scheduleId) {
            // Kiểm tra schedule thuộc về user hiện tại
            $schedule = \App\Models\Schedule::where('id', $scheduleId)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$schedule) {
                return response()->json(['error' => 'Không tìm thấy lịch trình'], 404);
            }
            
            $query->where('schedule_id', $scheduleId);
        } else {
            // Chỉ lấy schedule details của user hiện tại
            $query->whereHas('schedule', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        
        $details = $query->ofType($type)
            ->with(['schedule', 'scheduleItem'])
            ->orderBy('priority', 'desc')
            ->get();
        
        return response()->json($details);
    }

    /**
     * Lấy chi tiết theo trạng thái
     */
    public function getByStatus(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $scheduleId = $request->query('schedule_id');
        
        if (!$status) {
            return response()->json(['error' => 'Status is required'], 400);
        }
        
        $query = ScheduleDetail::query();
        
        if ($scheduleId) {
            // Kiểm tra schedule thuộc về user hiện tại
            $schedule = \App\Models\Schedule::where('id', $scheduleId)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$schedule) {
                return response()->json(['error' => 'Không tìm thấy lịch trình'], 404);
            }
            
            $query->where('schedule_id', $scheduleId);
        } else {
            // Chỉ lấy schedule details của user hiện tại
            $query->whereHas('schedule', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        
        $details = $query->withStatus($status)
            ->with(['schedule', 'scheduleItem'])
            ->orderBy('priority', 'desc')
            ->get();
        
        return response()->json($details);
    }
}
