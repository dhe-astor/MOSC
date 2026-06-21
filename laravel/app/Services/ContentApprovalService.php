<?php

namespace App\Services;

use App\Models\ContentApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContentApprovalService
{
    public static function submit(Model $model, string $approvalType, User $user, ?string $remarks = null): ContentApproval
    {
        return DB::transaction(function () use ($model, $approvalType, $user, $remarks) {
            $model->update([
                'status' => 'submitted',
                'submitted_by' => $user->id,
                'submitted_at' => Carbon::now(),
            ]);

            $approval = ContentApproval::create([
                'diocese_id' => $model->diocese_id,
                'church_id' => $model->church_id,
                'approvable_type' => get_class($model),
                'approvable_id' => $model->id,
                'approval_type' => $approvalType,
                'requested_by' => $user->id,
                'requested_at' => Carbon::now(),
                'status' => 'pending',
                'remarks' => $remarks
            ]);

            AuditLogService::log(
                'CMS',
                'Submit Content',
                get_class($model) . " submitted for approval: {$model->title}",
                null,
                $model->toArray(),
                $model,
                $model->church_id,
                $model->diocese_id
            );

            // Trigger notification
            \App\Services\NotificationTriggerService::triggerCmsContentApprovalRequested($model, $user);

            return $approval;
        });
    }

    public static function approve(ContentApproval $approval, User $user, ?string $remarks = null): void
    {
        DB::transaction(function () use ($approval, $user, $remarks) {
            $approval->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => Carbon::now(),
                'remarks' => $remarks
            ]);

            $model = $approval->approvable;
            if ($model) {
                $oldValues = $model->toArray();
                $model->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => Carbon::now(),
                ]);

                AuditLogService::log(
                    'CMS',
                    'Approve Content',
                    get_class($model) . " approved: {$model->title}",
                    $oldValues,
                    $model->toArray(),
                    $model,
                    $model->church_id,
                    $model->diocese_id
                );

                // Trigger notification
                \App\Services\NotificationTriggerService::triggerCmsContentApproved($model);
            }
        });
    }

    public static function reject(ContentApproval $approval, User $user, string $reason): void
    {
        DB::transaction(function () use ($approval, $user, $reason) {
            $approval->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => Carbon::now(),
                'rejection_reason' => $reason
            ]);

            $model = $approval->approvable;
            if ($model) {
                $oldValues = $model->toArray();
                $model->update([
                    'status' => 'rejected',
                    'rejection_reason' => $reason
                ]);

                AuditLogService::log(
                    'CMS',
                    'Reject Content',
                    get_class($model) . " rejected: {$model->title}. Reason: {$reason}",
                    $oldValues,
                    $model->toArray(),
                    $model,
                    $model->church_id,
                    $model->diocese_id
                );

                // Trigger notification
                \App\Services\NotificationTriggerService::triggerCmsContentRejected($model, $reason);
            }
        });
    }

    public static function publish(Model $model, User $user): void
    {
        DB::transaction(function () use ($model, $user) {
            $oldValues = $model->toArray();
            $model->update([
                'status' => 'published',
                'published_by' => $user->id,
                'published_at' => Carbon::now(),
            ]);

            AuditLogService::log(
                'CMS',
                'Publish Content',
                get_class($model) . " published: {$model->title}",
                $oldValues,
                $model->toArray(),
                $model,
                $model->church_id,
                $model->diocese_id
            );

            // Trigger notification
            \App\Services\NotificationTriggerService::triggerCmsContentPublished($model);
        });
    }

    public static function archive(Model $model, User $user): void
    {
        DB::transaction(function () use ($model, $user) {
            $oldValues = $model->toArray();
            $model->update([
                'status' => 'archived',
            ]);

            AuditLogService::log(
                'CMS',
                'Archive Content',
                get_class($model) . " archived: {$model->title}",
                $oldValues,
                $model->toArray(),
                $model,
                $model->church_id,
                $model->diocese_id
            );
        });
    }
}
