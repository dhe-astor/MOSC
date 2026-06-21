<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Services\AuditLogService;
use Exception;

class NotificationTemplateService
{
    public static function createTemplate(array $data, $user)
    {
        self::validateVariables($data['body'], $data['variables'] ?? []);

        $template = NotificationTemplate::create([
            'diocese_id' => $data['diocese_id'],
            'template_key' => $data['template_key'],
            'name' => $data['name'],
            'channel' => $data['channel'],
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'variables' => $data['variables'] ?? [],
            'status' => 'active',
            'is_system' => false,
            'created_by' => $user->id,
        ]);

        AuditLogService::log(
            'Communications',
            'Template Created',
            "Created notification template: {$template->name} ({$template->template_key})",
            null,
            $template->toArray(),
            $template,
            $user->id,
            $template->diocese_id
        );

        return $template;
    }

    public static function updateTemplate(NotificationTemplate $template, array $data, $user)
    {
        if ($template->is_system && !$user->hasRole(['Super Admin', 'Diocese Admin'])) {
            throw new Exception("System templates can only be edited by Super Admin or Diocese Admin.");
        }

        self::validateVariables($data['body'], $data['variables'] ?? []);

        $oldValues = $template->toArray();

        $template->update([
            'name' => $data['name'],
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'variables' => $data['variables'] ?? [],
            'status' => $data['status'] ?? $template->status,
            'updated_by' => $user->id,
        ]);

        AuditLogService::log(
            'Communications',
            'Template Updated',
            "Updated notification template: {$template->name} ({$template->template_key})",
            $oldValues,
            $template->toArray(),
            $template,
            $user->id,
            $template->diocese_id
        );

        return $template;
    }

    public static function archiveTemplate(NotificationTemplate $template, $user)
    {
        if ($template->is_system) {
            throw new Exception("System templates cannot be archived.");
        }

        $oldValues = $template->toArray();
        $template->update(['status' => 'archived']);

        AuditLogService::log(
            'Communications',
            'Template Archived',
            "Archived notification template: {$template->name} ({$template->template_key})",
            $oldValues,
            $template->toArray(),
            $template,
            $user->id,
            $template->diocese_id
        );

        return $template;
    }

    public static function renderTemplate(string $body, ?string $subject, array $data): array
    {
        $renderedBody = $body;
        $renderedSubject = $subject ?? '';

        foreach ($data as $key => $val) {
            $placeholder = '{{' . $key . '}}';
            $renderedBody = str_replace($placeholder, (string)$val, $renderedBody);
            $renderedSubject = str_replace($placeholder, (string)$val, $renderedSubject);
        }

        return [
            'subject' => $renderedSubject,
            'body' => $renderedBody
        ];
    }

    public static function validateVariables(string $body, array $variables): void
    {
        // Extract all placeholders matching {{variable_name}}
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $matches);
        $foundPlaceholders = $matches[1] ?? [];

        // Any placeholder used in the body must be declared in variables
        foreach ($foundPlaceholders as $placeholder) {
            if (!in_array($placeholder, $variables)) {
                throw new Exception("Variable '{$placeholder}' is used in the body but not defined in template variables.");
            }
        }
    }
}
