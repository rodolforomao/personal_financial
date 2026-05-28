<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Infrastructure\Models\Transaction;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $memberIds = User::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
            ->pluck('id');

        $scope = $request->string('scope')->toString() ?: 'all';

        $query = Activity::query()->latest();

        if ($scope === 'security') {
            $query->where('log_name', SecurityAuditLogger::LOG_NAME)
                ->where(function ($q) use ($workspaceId, $memberIds) {
                    $q->where('properties->workspace_id', $workspaceId)
                        ->orWhereIn('causer_id', $memberIds);
                });
        } elseif ($scope === 'transactions') {
            $query->where('subject_type', Transaction::class)
                ->whereIn('subject_id', function ($sub) use ($workspaceId) {
                    $sub->select('id')
                        ->from('transactions')
                        ->where('workspace_id', $workspaceId);
                });
        } else {
            $query->where(function ($q) use ($workspaceId, $memberIds) {
                $q->where(function ($inner) use ($workspaceId) {
                    $inner->where('subject_type', Transaction::class)
                        ->whereIn('subject_id', function ($sub) use ($workspaceId) {
                            $sub->select('id')
                                ->from('transactions')
                                ->where('workspace_id', $workspaceId);
                        });
                })->orWhere(function ($inner) use ($memberIds, $workspaceId) {
                    $inner->where('log_name', SecurityAuditLogger::LOG_NAME)
                        ->where(function ($security) use ($workspaceId, $memberIds) {
                            $security->where('properties->workspace_id', $workspaceId)
                                ->orWhereIn('causer_id', $memberIds);
                        });
                })->orWhere(function ($inner) use ($memberIds) {
                    $inner->where('causer_type', User::class)
                        ->whereIn('causer_id', $memberIds)
                        ->where('log_name', '!=', SecurityAuditLogger::LOG_NAME);
                });
            });
        }

        if ($event = $request->string('event')->toString()) {
            $query->where('event', $event);
        }

        $entries = $query
            ->paginate((int) $request->integer('per_page', 50))
            ->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'causer_type' => $activity->causer_type,
                'causer_id' => $activity->causer_id,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at?->toIso8601String(),
            ]);

        return response()->json($entries);
    }
}
