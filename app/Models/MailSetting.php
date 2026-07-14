<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'mailer',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'recipients',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'password' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The configured low stock notification recipients as a clean array.
     *
     * @return array<int, string>
     */
    public function recipientList(): array
    {
        return collect(explode(',', (string) $this->recipients))
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether this setting carries enough SMTP detail to build a custom mailer.
     */
    public function hasSmtpConfig(): bool
    {
        return filled($this->host) && filled($this->port);
    }

    /**
     * Build a Laravel mailer configuration array from this setting.
     *
     * @return array<string, mixed>
     */
    public function toMailerConfig(): array
    {
        return [
            'transport' => $this->mailer ?: 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            // Laravel 12 reads 'scheme', not 'encryption': 'smtps' forces
            // implicit SSL; plain 'smtp' upgrades via STARTTLS when available.
            'scheme' => $this->encryption === 'ssl' ? 'smtps' : null,
            'timeout' => 30,
        ];
    }
}
