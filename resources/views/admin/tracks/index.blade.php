@extends('admin.layout', ['title' => 'Tracks'])

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
                <h1>Tracks</h1>
                <p class="muted">Review uploaded tracks and publishing status.</p>
            </div>
            <a class="btn primary" href="{{ route('admin.tracks.create') }}">Add Track</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Track</th><th>Owner</th><th>Genre</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($tracks as $track)
                    <tr>
                        <td><strong>{{ $track->title }}</strong><br><span class="muted">{{ $track->artist_name }}</span></td>
                        <td>{{ $track->user?->username ?? 'No owner' }}</td>
                        <td>{{ $track->genre?->name ?? 'None' }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.tracks.status', $track) }}">
                                @csrf @method('patch')
                                <select name="status" onchange="this.form.submit()">
                                    @foreach ($trackStatuses as $status)
                                        <option value="{{ $status }}" @selected($track->status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="actions">
                            <a class="btn" href="{{ route('admin.tracks.edit', $track) }}">Edit</a>
                            <form method="post" action="{{ route('admin.tracks.destroy', $track) }}" onsubmit="return confirm('Delete this track?')">
                                @csrf @method('delete')
                                <button class="danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No tracks found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $tracks->links() }}</div>
    </section>
</main>
@endsection
