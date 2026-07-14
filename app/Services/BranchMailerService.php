<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MailSetting;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

class BranchMailerService
{
    /**
     * Whether low stock email notifications are enabled for the branch.
     *
     * A branch without a mail setting row falls back to the default .env
     * mailer and stays enabled; a row with is_active = false disables
     * email notifications for that branch.
     */
    public function notificationsEnabled(?Branch $branch): bool
    {
        $setting = $branch?->mailSetting;

        return $setting === null || $setting->is_active;
    }

    /**
     * Resolve the mailer for a branch: its own active SMTP configuration
     * when one exists, otherwise the default mailer from .env.
     */
    public function mailerFor(?Branch $branch): Mailer
    {
        $setting = $branch?->mailSetting;

        if ($setting && $setting->is_active && $setting->hasSmtpConfig()) {
            return $this->buildMailer($setting);
        }

        /** @var Mailer $mailer */
        $mailer = Mail::mailer();

        return $mailer;
    }

    /**
     * Build an on-the-fly mailer from a mail setting without touching
     * the global mail configuration.
     */
    public function buildMailer(MailSetting $setting): Mailer
    {
        $mailer = Mail::build($setting->toMailerConfig());

        if (filled($setting->from_address)) {
            $mailer->alwaysFrom($setting->from_address, $setting->from_name ?: config('app.name'));
        }

        return $mailer;
    }
}
