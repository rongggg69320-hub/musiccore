@extends('admin.layout', ['title' => 'Accounts'])

@section('body')
<main class="shell">
    @include('admin.partials.header')

    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert err">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <div>
                <h1>Accounts</h1>
                <p class="muted">Only normal user accounts are shown here. Admin accounts are hidden.</p>
            </div>
            <a class="btn primary" href="{{ route('admin.users.create') }}">Add Account</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Account</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($users as $user)
                    @php($isSuspended = $user->status === 'suspended')
                    <tr>
                        <td><strong>{{ $user->username }}</strong><br><span class="muted">#{{ $user->id }}</span></td>
                        <td>{{ $user->email }}</td>
                        <td><span class="badge">{{ ucfirst($user->role?->role_name ?? 'User') }}</span></td>
                        <td>
                            <div class="actions">
                                <span class="badge {{ $user->status === 'active' ? 'good' : ($isSuspended ? 'bad' : 'warn') }}">{{ ucfirst($user->status) }}</span>
                                <form class="inline-form" method="post" action="{{ route('admin.users.status', $user) }}">
                                    @csrf @method('patch')
                                    <select class="status-select" name="status" onchange="this.form.submit()">
                                        @foreach ($userStatuses as $status)
                                            <option value="{{ $status }}" @selected($user->status === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        </td>
                        <td>{{ $user->last_login?->format('Y-m-d H:i') ?? 'Never' }}</td>
                        <td class="actions">
                            <a class="btn" href="{{ route('admin.users.edit', $user) }}">Edit</a>
                            <form class="inline-form" method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('{{ $isSuspended ? 'Unsuspend this user?' : 'Suspend this user?' }}')">
                                @csrf @method('delete')
                                <button class="danger">{{ $isSuspended ? 'Unsuspend' : 'Suspend' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No user accounts found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $users->links() }}</div>
    </section>
</main>
@endsection
