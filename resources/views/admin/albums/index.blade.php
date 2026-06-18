@extends('admin.layout', ['title' => 'Albums'])

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
                <h1>Albums</h1>
                <p class="muted">Manage releases and album publishing status.</p>
            </div>
            <a class="btn primary" href="{{ route('admin.albums.create') }}">Add Album</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Album</th><th>Owner</th><th>Tracks</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($albums as $album)
                    <tr>
                        <td><strong>{{ $album->title }}</strong><br><span class="muted">{{ $album->artist_name ?? 'Unknown artist' }}</span></td>
                        <td>{{ $album->user?->username ?? 'No owner' }}</td>
                        <td><span class="badge">{{ $album->tracks_count }}</span></td>
                        <td>
                            <form method="post" action="{{ route('admin.albums.status', $album) }}">
                                @csrf @method('patch')
                                <select name="status" onchange="this.form.submit()">
                                    @foreach ($albumStatuses as $status)
                                        <option value="{{ $status }}" @selected($album->status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="actions">
                            <a class="btn" href="{{ route('admin.albums.edit', $album) }}">Edit</a>
                            <form method="post" action="{{ route('admin.albums.destroy', $album) }}" onsubmit="return confirm('Delete this album?')">
                                @csrf @method('delete')
                                <button class="danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No albums found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $albums->links() }}</div>
    </section>
</main>
@endsection
