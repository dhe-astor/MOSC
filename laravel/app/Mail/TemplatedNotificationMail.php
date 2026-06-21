<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplatedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectText;
    public $bodyText;

    public function __construct(string $subjectText, string $bodyText)
    {
        $this->subjectText = $subjectText;
        $this->bodyText = $bodyText;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->getHtmlBody(),
        );
    }

    protected function getHtmlBody(): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 20px; }
                .container { max-width: 600px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; margin: 0 auto; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
                .header { background-color: #881337; color: #ffffff; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 20px; font-weight: bold; }
                .content { padding: 40px 30px; line-height: 1.6; font-size: 15px; }
                .footer { background-color: #f1f5f9; color: #64748b; padding: 20px; text-align: center; font-size: 12px; border-top: 1px solid #e2e8f0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>MSOC Europe Diocese</h1>
                </div>
                <div class='content'>
                    " . nl2br(e($this->bodyText)) . "
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " MSOC Europe Diocese Administration Portal. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
