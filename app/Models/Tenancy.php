<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Tenancy extends Model
{
    protected $fillable = [
        'company_id',
        'estate_id',
        'tenant_id',
        'unit_id',
        'move_in_date',
        'move_out_date',
        'notes',
        'status'
    ];

    protected $casts = [
        'move_in_date' => 'datetime',
        'move_out_date' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    /**
     * Boot the model and register event listeners
     */
    protected static function booted()
    {
        // Send welcome notification when a new tenancy is created (tenant moves in)
        static::created(function ($tenancy) {
            $tenancy->sendWelcomeNotification();
        });

        // Send thank you notification when tenancy status changes to 'ended' (tenant moves out)
        static::updated(function ($tenancy) {
            if ($tenancy->wasChanged('status') && $tenancy->status === 'ended') {
                $tenancy->sendThankYouNotification();
            }
        });
    }

    /**
     * Send welcome notification to new tenant
     */
    public function sendWelcomeNotification(): void
    {
        try {
            $tenant = $this->tenant;
            $unit = $this->unit;
            $estate = $this->estate;

            if (!$tenant || !$unit || !$estate) {
                Log::warning("Missing data for welcome notification - Tenancy ID: {$this->id}");
                return;
            }

            $message = "Dear {$tenant->name},\n\n" .
                       "Welcome to {$estate->name}!\n" .
                       "Your unit: {$unit->unit_number}\n" .
                       "Move-in date: " . ($this->move_in_date ? $this->move_in_date->format('d M Y') : 'N/A') . "\n\n" .
                       "For inquiries, contact: " . ($estate->phone ?? 'N/A') . "\n" .
                       "We're happy to have you!";

            // Log the notification
            Log::info("WELCOME NOTIFICATION", [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_phone' => $tenant->phone ?? 'N/A',
                'tenant_email' => $tenant->email ?? 'N/A',
                'unit_id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'estate_id' => $estate->id,
                'estate_name' => $estate->name,
                'message' => $message
            ]);

            // TODO: Uncomment below when ready to send actual SMS
            // $this->sendSms($tenant->phone, $message);

            // TODO: Uncomment below when ready to send actual Email
            // $this->sendEmail($tenant->email, 'Welcome to ' . $estate->name, $message);

        } catch (\Exception $e) {
            Log::error("Failed to send welcome notification: " . $e->getMessage(), [
                'tenancy_id' => $this->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send thank you notification to departing tenant
     */
    public function sendThankYouNotification(): void
    {
        try {
            $tenant = $this->tenant;
            $unit = $this->unit;
            $estate = $this->estate;

            if (!$tenant || !$unit || !$estate) {
                Log::warning("Missing data for thank you notification - Tenancy ID: {$this->id}");
                return;
            }

            // Calculate duration of stay
            $duration = $this->getStayDuration();

            $message = "Dear {$tenant->name},\n\n" .
                       "Thank you for being a valued tenant at {$estate->name}!\n" .
                       "Your tenancy at unit {$unit->unit_number} has ended.\n" .
                       "You stayed with us for: {$duration}\n\n" .
                       "We wish you all the best in your future endeavors!\n" .
                       "Thank you for choosing us.";

            // Log the notification
            Log::info("THANK YOU NOTIFICATION", [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_phone' => $tenant->phone ?? 'N/A',
                'tenant_email' => $tenant->email ?? 'N/A',
                'unit_id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'estate_id' => $estate->id,
                'estate_name' => $estate->name,
                'move_in_date' => $this->move_in_date?->format('d M Y'),
                'move_out_date' => $this->move_out_date?->format('d M Y'),
                'duration' => $duration,
                'message' => $message
            ]);

            // TODO: Uncomment below when ready to send actual SMS
            // $this->sendSms($tenant->phone, $message);

            // TODO: Uncomment below when ready to send actual Email
            // $this->sendEmail($tenant->email, 'Thank You from ' . $estate->name, $message);

        } catch (\Exception $e) {
            Log::error("Failed to send thank you notification: " . $e->getMessage(), [
                'tenancy_id' => $this->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get the duration of stay in a readable format
     */
    public function getStayDuration(): string
    {
        $start = $this->move_in_date;
        $end = $this->move_out_date ?? now();
        
        if (!$start) {
            return 'N/A';
        }

        $diff = $start->diff($end);

        $parts = [];
        if ($diff->y > 0) {
            $parts[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
        }
        if ($diff->d > 0 && empty($parts)) {
            $parts[] = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
        }

        return empty($parts) ? 'a short period' : implode(', ', $parts);
    }

    /**
     * Send SMS notification (implement when ready)
     */
    protected function sendSms(?string $phone, string $message): void
    {
        if (empty($phone)) {
            Log::warning("No phone number provided for SMS");
            return;
        }

        // TODO: Implement SMS sending
        // Example using your KenyaSMS service:
        // try {
        //     $smsService = app(\App\Modules\SMS\Services\KenyaSMSService::class);
        //     $smsService->send($phone, $message);
        //     Log::info("SMS sent successfully to: {$phone}");
        // } catch (\Exception $e) {
        //     Log::error("Failed to send SMS: " . $e->getMessage());
        // }
    }

    /**
     * Send Email notification (implement when ready)
     */
    protected function sendEmail(?string $email, string $subject, string $message): void
    {
        if (empty($email)) {
            Log::warning("No email address provided for email");
            return;
        }

        // TODO: Implement Email sending
        // Example:
        // try {
        //     Mail::raw($message, function ($mail) use ($email, $subject) {
        //         $mail->to($email)->subject($subject);
        //     });
        //     Log::info("Email sent successfully to: {$email}");
        // } catch (\Exception $e) {
        //     Log::error("Failed to send email: " . $e->getMessage());
        // }
    }

    /**
     * Check if tenancy is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if tenancy has ended
     */
    public function hasEnded(): bool
    {
        return $this->status === 'ended';
    }

    /**
     * Manually trigger welcome notification
     */
    public function resendWelcome(): void
    {
        $this->sendWelcomeNotification();
    }

    /**
     * Manually trigger thank you notification
     */
    public function resendThankYou(): void
    {
        $this->sendThankYouNotification();
    }
}