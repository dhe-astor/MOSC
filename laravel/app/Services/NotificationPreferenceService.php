<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\Member;
use App\Models\Family;

class NotificationPreferenceService
{
    public static function getPreferences($target)
    {
        $query = NotificationPreference::where('diocese_id', $target->diocese_id ?? 1);
        if ($target instanceof User) {
            $query->where('user_id', $target->id);
        } elseif ($target instanceof Member) {
            $query->where('member_id', $target->id);
        } elseif ($target instanceof Family) {
            $query->where('family_id', $target->id);
        }
        return $query->get();
    }

    public static function updatePreference($target, string $channel, string $notificationType, bool $isEnabled, $updatedBy = null)
    {
        if (self::isCritical($notificationType)) {
            // Cannot disable critical system notifications
            $isEnabled = true;
        }

        $lookup = [
            'diocese_id' => $target->diocese_id ?? 1,
            'channel' => $channel,
            'notification_type' => $notificationType
        ];

        if ($target instanceof User) {
            $lookup['user_id'] = $target->id;
        } elseif ($target instanceof Member) {
            $lookup['member_id'] = $target->id;
        } elseif ($target instanceof Family) {
            $lookup['family_id'] = $target->id;
        }

        return NotificationPreference::updateOrCreate($lookup, [
            'is_enabled' => $isEnabled,
            'updated_by' => $updatedBy ? $updatedBy->id : null
        ]);
    }

    public static function canSend($recipient, string $channel, string $notificationType): bool
    {
        if (self::isCritical($notificationType)) {
            return true;
        }

        $query = NotificationPreference::where('channel', $channel)
            ->where('notification_type', $notificationType);

        if (isset($recipient['recipient_type'])) {
            if ($recipient['recipient_type'] === 'user' && isset($recipient['recipient_id'])) {
                $query->where('user_id', $recipient['recipient_id']);
            } elseif ($recipient['recipient_type'] === 'member' && isset($recipient['recipient_id'])) {
                $query->where('member_id', $recipient['recipient_id']);
            } elseif ($recipient['recipient_type'] === 'family' && isset($recipient['recipient_id'])) {
                $query->where('family_id', $recipient['recipient_id']);
            } else {
                return true; // Externals have no preference table, so send by default
            }
        } else {
            return true;
        }

        $pref = $query->first();
        return $pref ? (bool)$pref->is_enabled : true;
    }

    public static function isCritical(string $notificationType): bool
    {
        $critical = [
            'approval',
            'certificate',
            'finance',
            'security',
            'system',
            'emergency'
        ];

        return in_array($notificationType, $critical);
    }
}
