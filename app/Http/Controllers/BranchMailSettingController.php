<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MailSetting;
use App\Services\BranchMailerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BranchMailSettingController extends Controller
{
    public function __construct(private readonly BranchMailerService $branchMailer)
    {
    }

    public function edit(Branch $branch): View
    {
        return view('branches.mail-settings', [
            'branch' => $branch,
            'setting' => $branch->mailSetting,
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $this->validated($request);

        $setting = $branch->mailSetting()->firstOrNew();
        $setting->fill($this->settingAttributes($validated));

        // Blank password means "keep the current one".
        if (filled($validated['password'] ?? null)) {
            $setting->password = $validated['password'];
        }

        $branch->mailSetting()->save($setting);

        return redirect()
            ->route('branches.mail-settings.edit', $branch)
            ->with('success', 'Mail settings saved successfully.');
    }

    public function test(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $this->validated($request, [
            'test_recipient' => ['required', 'email'],
        ]);

        $setting = new MailSetting($this->settingAttributes($validated));

        // Fall back to the stored password when the field is left blank.
        $setting->password = filled($validated['password'] ?? null)
            ? $validated['password']
            : $branch->mailSetting?->password;

        try {
            $mailer = $setting->hasSmtpConfig()
                ? $this->branchMailer->buildMailer($setting)
                : $this->branchMailer->mailerFor(null);

            $mailer->raw(
                sprintf(
                    "This is a test email for the %s branch mail settings of %s.\n\nIf you received this message, the SMTP configuration works.",
                    $branch->name,
                    config('app.name')
                ),
                function ($message) use ($validated, $branch): void {
                    $message->to($validated['test_recipient'])
                        ->subject('['.$branch->name.'] Mail Settings Test');
                }
            );
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Test email failed: '.$e->getMessage());
        }

        return back()
            ->withInput()
            ->with('success', 'Test email sent to '.$validated['test_recipient'].'. Settings are NOT saved yet — click "Save Settings" to keep them.');
    }

    /**
     * @param array<int, mixed> $extraRules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $extraRules = []): array
    {
        // Normalize the comma-separated recipients into an array so each
        // address gets validated individually.
        $request->merge([
            'recipients_list' => collect(explode(',', (string) $request->input('recipients')))
                ->map(fn (string $email) => trim($email))
                ->filter()
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'mailer' => ['required', 'string', 'in:smtp,log'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'recipients_list' => ['nullable', 'array'],
            'recipients_list.*' => ['email:rfc'],
            'is_active' => ['nullable', 'boolean'],
        ] + $extraRules, [
            'recipients_list.*.email' => 'Recipient ":input" is not a valid email address.',
        ]);

        $validated['recipients'] = implode(', ', $validated['recipients_list'] ?? []);

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function settingAttributes(array $validated): array
    {
        return [
            'mailer' => $validated['mailer'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? null,
            'username' => $validated['username'] ?? null,
            'encryption' => $validated['encryption'] ?? null,
            'from_address' => $validated['from_address'] ?? null,
            'from_name' => $validated['from_name'] ?? null,
            'recipients' => filled($validated['recipients']) ? $validated['recipients'] : null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
