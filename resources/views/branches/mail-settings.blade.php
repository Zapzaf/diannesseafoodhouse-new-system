@extends('layouts.app')
@section('page_title', 'Mail Settings - ' . $branch->name)
@section('content')
    <x-page-header :title="'Mail Settings — ' . $branch->name" subtitle="Per-branch SMTP configuration for low stock email notifications" icon="mail">
        <a href="{{ route('branches.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Branches
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="alert alert-info d-flex align-items-center" role="alert">
            <i data-lucide="info" class="me-2 flex-shrink-0"></i>
            <div>Leave the SMTP host empty to use the system default mail configuration (.env). Disabling notifications stops low stock emails for this branch entirely.</div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header fw-semibold"><i data-lucide="mail" class="me-1"></i> SMTP Configuration</div>
            <div class="card-body">
                <form action="{{ route('branches.mail-settings.update', $branch) }}" method="POST">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Mailer <span class="text-danger">*</span></label>
                            <select name="mailer" class="form-select @error('mailer') is-invalid @enderror" required>
                                @foreach(['smtp' => 'SMTP', 'log' => 'Log (testing only)'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('mailer', $setting->mailer ?? 'smtp') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('mailer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">SMTP Host</label>
                            <input type="text" name="host" class="form-control @error('host') is-invalid @enderror"
                                   value="{{ old('host', $setting->host ?? '') }}" placeholder="e.g. smtp.gmail.com">
                            @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SMTP Port</label>
                            <input type="number" name="port" class="form-control @error('port') is-invalid @enderror"
                                   value="{{ old('port', $setting->port ?? '') }}" placeholder="587" min="1" max="65535">
                            @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Encryption</label>
                            <select name="encryption" class="form-select @error('encryption') is-invalid @enderror">
                                <option value="" @selected(old('encryption', $setting->encryption ?? '') === '')>None</option>
                                <option value="tls" @selected(old('encryption', $setting->encryption ?? '') === 'tls')>TLS</option>
                                <option value="ssl" @selected(old('encryption', $setting->encryption ?? '') === 'ssl')>SSL</option>
                            </select>
                            @error('encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control @error('username') is-invalid @enderror"
                                   value="{{ old('username', $setting->username ?? '') }}" autocomplete="off">
                            @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                                   value="" placeholder="{{ ($setting && filled($setting->password)) ? '••••••••  (leave blank to keep current)' : 'SMTP password' }}" autocomplete="new-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Stored encrypted. Leave blank to keep the current password.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">From Email</label>
                            <input type="email" name="from_address" class="form-control @error('from_address') is-invalid @enderror"
                                   value="{{ old('from_address', $setting->from_address ?? '') }}" placeholder="noreply@example.com">
                            @error('from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">From Name</label>
                            <input type="text" name="from_name" class="form-control @error('from_name') is-invalid @enderror"
                                   value="{{ old('from_name', $setting->from_name ?? '') }}" placeholder="{{ config('app.name') }}">
                            @error('from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Low Stock Notification Recipients</label>
                        <textarea name="recipients" rows="2" class="form-control @error('recipients_list.*') is-invalid @enderror"
                                  placeholder="manager@example.com, owner@example.com">{{ old('recipients', $setting->recipients ?? '') }}</textarea>
                        @error('recipients_list.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">One or more email addresses separated by commas. Leave empty to notify the branch manager's account email.</div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="isActive" name="is_active" value="1"
                               @checked(old('is_active', $setting->is_active ?? true))>
                        <label class="form-check-label fw-semibold" for="isActive">Enable low stock email notifications for this branch</label>
                    </div>

                    <hr>

                    <div class="row g-3 align-items-end mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Send Test Email To</label>
                            <input type="email" name="test_recipient" class="form-control @error('test_recipient') is-invalid @enderror"
                                   value="{{ old('test_recipient', auth()->user()->email) }}">
                            @error('test_recipient')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Uses the values entered above without saving them.</div>
                        </div>
                        <div class="col-auto">
                            <button type="submit" formaction="{{ route('branches.mail-settings.test', $branch) }}" class="btn btn-outline-primary">
                                <i data-lucide="send" class="me-1"></i> Send Test Email
                            </button>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <a href="{{ route('branches.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
