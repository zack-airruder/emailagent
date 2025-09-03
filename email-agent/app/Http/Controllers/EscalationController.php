<?php

namespace App\Http\Controllers;

use App\Models\Escalation;
use App\Models\ProcessedEmail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EscalationController extends Controller
{
    /**
     * Get all escalations with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Escalation::with(['processedEmail.account', 'assignedUser'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by priority
            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            // Filter by assigned user
            if ($request->has('assigned_to')) {
                $query->where('assigned_to', $request->assigned_to);
            }

            // Search in reason or notes
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            $escalations = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Escalations retrieved successfully',
                'data' => $escalations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve escalations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific escalation with full details
     */
    public function show(Escalation $escalation): JsonResponse
    {
        try {
            $escalation->load([
                'processedEmail.account',
                'processedEmail.rules',
                'assignedUser'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Escalation retrieved successfully',
                'data' => $escalation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve escalation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new escalation
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'processed_email_id' => 'required|exists:processed_emails,id',
                'reason' => 'required|string|max:500',
                'priority' => 'required|in:low,medium,high,urgent',
                'assigned_to' => 'nullable|exists:users,id',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $escalation = Escalation::create([
                'processed_email_id' => $request->processed_email_id,
                'reason' => $request->reason,
                'priority' => $request->priority,
                'status' => 'pending',
                'assigned_to' => $request->assigned_to,
                'notes' => $request->notes,
                'escalated_by' => Auth::id(),
                'escalated_at' => now()
            ]);

            $escalation->load(['processedEmail.account', 'assignedUser']);

            return response()->json([
                'success' => true,
                'message' => 'Escalation created successfully',
                'data' => $escalation
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create escalation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an escalation
     */
    public function update(Request $request, Escalation $escalation): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:pending,in_progress,resolved,closed',
                'priority' => 'sometimes|in:low,medium,high,urgent',
                'assigned_to' => 'nullable|exists:users,id',
                'notes' => 'nullable|string|max:1000',
                'resolution_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only([
                'status', 'priority', 'assigned_to', 'notes', 'resolution_notes'
            ]);

            // Set resolution timestamp if status is being changed to resolved
            if ($request->has('status') && $request->status === 'resolved' && $escalation->status !== 'resolved') {
                $updateData['resolved_at'] = now();
                $updateData['resolved_by'] = Auth::id();
            }

            $escalation->update($updateData);
            $escalation->load(['processedEmail.account', 'assignedUser']);

            return response()->json([
                'success' => true,
                'message' => 'Escalation updated successfully',
                'data' => $escalation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update escalation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign escalation to a user
     */
    public function assign(Request $request, Escalation $escalation): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'assigned_to' => 'required|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $escalation->update([
                'assigned_to' => $request->assigned_to,
                'status' => 'in_progress'
            ]);

            $escalation->load(['processedEmail.account', 'assignedUser']);

            return response()->json([
                'success' => true,
                'message' => 'Escalation assigned successfully',
                'data' => $escalation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign escalation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve an escalation
     */
    public function resolve(Request $request, Escalation $escalation): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'resolution_notes' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $escalation->update([
                'status' => 'resolved',
                'resolution_notes' => $request->resolution_notes,
                'resolved_at' => now(),
                'resolved_by' => Auth::id()
            ]);

            $escalation->load(['processedEmail.account', 'assignedUser']);

            return response()->json([
                'success' => true,
                'message' => 'Escalation resolved successfully',
                'data' => $escalation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve escalation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get escalation statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Escalation::count(),
                'pending' => Escalation::where('status', 'pending')->count(),
                'in_progress' => Escalation::where('status', 'in_progress')->count(),
                'resolved' => Escalation::where('status', 'resolved')->count(),
                'closed' => Escalation::where('status', 'closed')->count(),
                'by_priority' => [
                    'low' => Escalation::where('priority', 'low')->count(),
                    'medium' => Escalation::where('priority', 'medium')->count(),
                    'high' => Escalation::where('priority', 'high')->count(),
                    'urgent' => Escalation::where('priority', 'urgent')->count()
                ],
                'avg_resolution_time' => $this->getAverageResolutionTime(),
                'recent_escalations' => Escalation::with(['processedEmail.account'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Escalation statistics retrieved successfully',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve escalation statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get escalations assigned to the current user
     */
    public function myEscalations(Request $request): JsonResponse
    {
        try {
            $query = Escalation::with(['processedEmail.account'])
                ->where('assigned_to', Auth::id())
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $escalations = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'My escalations retrieved successfully',
                'data' => $escalations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve my escalations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate average resolution time in hours
     */
    private function getAverageResolutionTime(): ?float
    {
        $resolvedEscalations = Escalation::whereNotNull('resolved_at')
            ->whereNotNull('escalated_at')
            ->get();

        if ($resolvedEscalations->isEmpty()) {
            return null;
        }

        $totalHours = $resolvedEscalations->sum(function ($escalation) {
            return $escalation->escalated_at->diffInHours($escalation->resolved_at);
        });

        return round($totalHours / $resolvedEscalations->count(), 2);
    }
}
