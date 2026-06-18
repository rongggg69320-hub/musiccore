@extends('admin.layout', ['title' => 'Genres'])

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
                <h1>Genres</h1>
                <p class="muted">Manage the catalog labels used for discovery.</p>
            </div>
        </div>

        <form class="actions" method="post" action="{{ route('admin.genres.store') }}" style="margin-bottom:12px;">
            @csrf
            <input name="name" placeholder="New genre name" required>
            <button class="primary">Add Genre</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Tracks</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($genres as $genre)
                    <tr>
                        <td>
                            <form method="post" action="{{ route('admin.genres.update', $genre) }}" class="actions">
                                @csrf @method('put')
                                <input name="name" value="{{ $genre->name }}" required>
                                <button>Save</button>
                            </form>
                        </td>
                        <td><span class="badge">{{ $genre->tracks_count }}</span></td>
                        <td>
                            <form class="inline-form" method="post" action="{{ route('admin.genres.destroy', $genre) }}" onsubmit="return confirm('Delete this genre?')">
                                @csrf @method('delete')
                                <button class="danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">No genres found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $genres->links() }}</div>
    </section>
</main>
@endsection
