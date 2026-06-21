<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use App\Mail\TemplatedNotificationMail;
use App\Services\NotificationTemplateService;
use Exception;

class EmailService
{
    public static function sendEmail(string $to, string $subject, string $body)
    {
        Mail::to($to)->send(new TemplatedNotificationMail($subject, $body));
    }

    public static function sendTemplatedEmail(string $to, string $templateKey, array $data, int $dioceseId = 1)
    {
        $template = \App\Models\NotificationTemplate::where('diocese_id', $dioceseId)
            ->where('template_key', $templateKey)
            ->where('channel', 'email')
            ->where('status', 'active')
            ->first();

        if (!$template) {
            throw new Exception("Template '{$templateKey}' not found.");
        }

        $rendered = NotificationTemplateService::renderTemplate($template->body, $template->subject, $data);
        self::sendEmail($to, $rendered['subject'], $rendered['body']);
    }
}
