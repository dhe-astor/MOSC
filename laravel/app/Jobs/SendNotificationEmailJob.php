<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\NotificationDelivery;
use App\Services\EmailService;
use Exception;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $delivery;
    protected $subject;
    protected $body;

    public $tries = 3;

    public function __construct(NotificationDelivery $delivery, string $subject, string $body)
    {
        $this->delivery = $delivery;
        $this->subject = $subject;
        $this->body = $body;
    }

    public function handle(): void
    {
        $delivery = $this->delivery->fresh();
        
        // Prevent sending if already delivered or cancelled/archived
        if ($delivery->delivery_status === 'delivered') {
            return;
        }

        $delivery->increment('attempt_count');
        $delivery->update([
            'delivery_status' => 'sent',
            'last_attempt_at' => now()
        ]);

        try {
            // Send email via EmailService
            EmailService::sendEmail($delivery->recipient_email, $this->subject, $this->body);

            $delivery->update([
                'delivery_status' => 'delivered',
                'sent_at' => now(),
                'delivered_at' => now()
            ]);

            if ($delivery->notification_id) {
                $delivery->notification->update(['status' => 'delivered']);
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $delivery->update([
                'delivery_status' => 'failed',
                'error_message' => $errorMessage,
                'failed_at' => now()
            ]);

            if ($delivery->notification_id) {
                $delivery->notification->update(['status' => 'failed']);
            }

            // Throw the exception so Laravel handles retry/fails correctly
            throw $e;
        }
    }
}
