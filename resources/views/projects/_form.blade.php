@php
    $user = auth()->user();
    $val = fn ($key, $default = null) => old($key, $default);
    $err = fn ($key) => $errors->has($key) ? ' is-invalid' : '';
    $memberIds = $project->exists ? $project->members->pluck('id')->all() : [];
    $selectedDevelopers = array_map('intval', (array) old('developers', $memberIds));
    $selectedCustomers = array_map('intval', (array) old('customers', $memberIds));
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-accent mb-3">
            <div class="card-header"><i class="bi bi-info-circle text-brand"></i> {{ __('projects.general') }}</div>
            <div class="card-body row g-3">
                <div class="col-md-8">
                    <label class="form-label required">{{ __('projects.fields.name') }}</label>
                    <input type="text" name="name" value="{{ $val('name', $project->name) }}" maxlength="150" required class="form-control{{ $err('name') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label required">{{ __('projects.fields.code') }}</label>
                    <input type="text" name="code" value="{{ $val('code', $project->code) }}" maxlength="12" required class="form-control ltr-input text-uppercase{{ $err('code') }}" pattern="[A-Za-z0-9_\-]+">
                    <div class="form-text">{{ __('projects.code_hint') }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('projects.fields.description') }}</label>
                    <input type="text" name="description" value="{{ $val('description', $project->description) }}" maxlength="255" class="form-control{{ $err('description') }}">
                    <div class="form-text">{{ __('projects.description_hint') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">{{ __('projects.fields.status') }}</label>
                    <select name="status" class="form-select">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($val('status', $project->status?->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('projects.fields.logo') }}</label>
                    <input type="file" name="logo" accept="image/*" class="form-control{{ $err('logo') }}">
                    @if ($project->logoUrl())
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <img src="{{ $project->logoUrl() }}" alt="" class="project-logo">
                            <label class="form-check small mb-0"><input type="checkbox" name="remove_logo" value="1" class="form-check-input"> {{ __('projects.remove_logo') }}</label>
                        </div>
                    @endif
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('projects.fields.notes') }}</label>
                    <textarea name="notes" rows="3" class="form-control{{ $err('notes') }}">{{ $val('notes', $project->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card card-accent">
            <div class="card-header"><i class="bi bi-telephone text-brand"></i> {{ __('projects.contact_info') }}</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('projects.fields.contact_person') }}</label>
                    <input type="text" name="contact_person" value="{{ $val('contact_person', $project->contact_person) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('projects.fields.email') }}</label>
                    <input type="email" name="email" value="{{ $val('email', $project->email) }}" class="form-control ltr-input{{ $err('email') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('projects.fields.phone1') }}</label>
                    <input type="text" name="phone1" value="{{ $val('phone1', $project->phone1) }}" class="form-control ltr-input">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('projects.fields.phone2') }}</label>
                    <input type="text" name="phone2" value="{{ $val('phone2', $project->phone2) }}" class="form-control ltr-input">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('projects.fields.website') }}</label>
                    <input type="url" name="website" value="{{ $val('website', $project->website) }}" placeholder="https://" class="form-control ltr-input{{ $err('website') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('projects.fields.address') }}</label>
                    <input type="text" name="address" value="{{ $val('address', $project->address) }}" maxlength="500" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-accent mb-3">
            <div class="card-header"><i class="bi bi-cash-coin text-brand"></i> {{ __('projects.budget_and_dates') }}</div>
            <div class="card-body row g-3">
                <div class="col-6">
                    <label class="form-label">{{ __('projects.fields.start_date') }}</label>
                    <x-date-input name="start_date" :value="$project->start_date" />
                </div>
                <div class="col-6">
                    <label class="form-label">{{ __('projects.fields.end_date') }}</label>
                    <x-date-input name="end_date" :value="$project->end_date" />
                </div>
                <div class="col-8">
                    <label class="form-label">{{ __('projects.fields.budget') }}</label>
                    <input type="text" name="budget" data-money inputmode="decimal" value="{{ $val('budget', $project->budget) }}" class="form-control ltr-input{{ $err('budget') }}">
                </div>
                <div class="col-4">
                    <label class="form-label">{{ __('projects.fields.currency') }}</label>
                    <select name="currency" class="form-select">
                        @foreach ($currencies as $cur)
                            <option value="{{ $cur }}" @selected($val('currency', $project->currency) === $cur)>{{ __('app.currency.'.$cur) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card card-accent">
            <div class="card-header"><i class="bi bi-people text-brand"></i> {{ __('projects.members') }}</div>
            <div class="card-body">
                @if ($user->isAdmin())
                    <label class="form-label">{{ __('projects.fields.developers') }}</label>
                    <div class="border rounded p-2 mb-3" style="max-height: 200px; overflow-y: auto">
                        @forelse ($allDevelopers as $dev)
                            <label class="form-check mb-1">
                                <input type="checkbox" class="form-check-input" name="developers[]" value="{{ $dev->id }}" @checked(in_array($dev->id, $selectedDevelopers, true))>
                                <span class="form-check-label small">{{ $dev->name }}</span>
                            </label>
                        @empty
                            <span class="text-muted small">{{ __('app.no_results') }}</span>
                        @endforelse
                    </div>
                @else
                    <div class="form-text mb-2"><i class="bi bi-info-circle"></i> {{ __('projects.developers_hint') }}</div>
                @endif

                <label class="form-label">{{ __('projects.fields.customers') }}</label>
                <div class="border rounded p-2" style="max-height: 240px; overflow-y: auto">
                    @forelse ($allCustomers as $customer)
                        <label class="form-check mb-1">
                            <input type="checkbox" class="form-check-input" name="customers[]" value="{{ $customer->id }}" @checked(in_array($customer->id, $selectedCustomers, true))>
                            <span class="form-check-label small">{{ $customer->name }} <span class="text-muted">({{ $customer->email }})</span></span>
                        </label>
                    @empty
                        <span class="text-muted small">{{ __('app.no_results') }}</span>
                    @endforelse
                </div>
                <div class="form-text">{{ __('projects.customers_hint') }}</div>
            </div>
        </div>
    </div>
</div>
