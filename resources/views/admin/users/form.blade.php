@extends('admin.layout', ['title' => $user->exists ? 'Edit Account' : 'Add Account'])

@section('body')
<main class="shell">
    @include('admin.partials.header')

    <section class="panel">
        <h1>{{ $user->exists ? 'Edit Account' : 'Add Account' }}</h1>

        @if ($errors->any())
            <div class="alert err">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if ($user->exists)
                @method('put')
            @endif

            <div class="form-grid">
                <div>
                    <label for="username">Username</label>
                    <input id="username" name="username" value="{{ old('username', $user->username) }}" required>
                </div>
                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                </div>
                <div>
                    <label for="role_id">Role</label>
                    <select id="role_id" name="role_id" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((int) old('role_id', $user->role_id) === $role->id)>
                                {{ $role->role_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $user->status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="password">{{ $user->exists ? 'New Password' : 'Password' }}</label>
                    <input id="password" name="password" type="password" {{ $user->exists ? '' : 'required' }}>
                    @if ($user->exists)
                        <span class="muted">Leave blank to keep the current password.</span>
                    @endif
                </div>
                <div>
                    <label for="is_verified">Verified</label>
                    <input type="hidden" name="is_verified" value="0">
                    <select id="is_verified" name="is_verified">
                        <option value="1" @selected(old('is_verified', $user->is_verified) == 1)>Yes</option>
                        <option value="0" @selected(old('is_verified', $user->is_verified) == 0)>No</option>
                    </select>
                </div>
            </div>

            <label for="bio">Bio</label>
            <textarea id="bio" name="bio">{{ old('bio', $user->bio) }}</textarea>

            <div class="actions" style="margin-top:16px;">
                <button class="primary">{{ $user->exists ? 'Save Account' : 'Add Account' }}</button>
                <a class="btn" href="{{ route('admin.dashboard') }}">Cancel</a>
            </div>
        </form>
    </section>
</main>
@endsection
