@extends('admin.layout', ['title' => 'Admin Dashboard'])

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
                <h1>Dashboard</h1>
                <p class="muted">Choose a section to manage records with dedicated pagination.</p>
            </div>
        </div>

        <div class="grid">
            <a class="stat" href="{{ route('admin.users.index') }}">
                <strong>{{ $stats['users'] }}</strong>
                <span class="muted">User accounts</span>
            </a>
            <a class="stat" href="{{ route('admin.genres.index') }}">
                <strong>{{ $stats['genres'] }}</strong>
                <span class="muted">Genres</span>
            </a>
            <a class="stat" href="{{ route('admin.tracks.index') }}">
                <strong>{{ $stats['tracks'] }}</strong>
                <span class="muted">Tracks</span>
            </a>
            <a class="stat" href="{{ route('admin.albums.index') }}">
                <strong>{{ $stats['albums'] }}</strong>
                <span class="muted">Albums</span>
            </a>
        </div>
    </section>
</main>
@endsection
