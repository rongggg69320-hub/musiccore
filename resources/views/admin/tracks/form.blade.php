@extends('admin.layout', ['title' => $track->exists ? 'Edit Track' : 'Add Track'])

@section('body')
<main class="shell">
    @include('admin.partials.header')

    <section class="panel">
        <h1>{{ $track->exists ? 'Edit Track' : 'Add Track' }}</h1>

        @if ($errors->any())
            <div class="alert err">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ $track->exists ? route('admin.tracks.update', $track) : route('admin.tracks.store') }}">
            @csrf
            @if ($track->exists)
                @method('put')
            @endif

            <div class="form-grid">
                <div>
                    <label for="title">Title</label>
                    <input id="title" name="title" value="{{ old('title', $track->title) }}" required>
                </div>
                <div>
                    <label for="artist_name">Artist Name</label>
                    <input id="artist_name" name="artist_name" value="{{ old('artist_name', $track->artist_name) }}" required>
                </div>
                <div>
                    <label for="user_id">Owner</label>
                    <select id="user_id" name="user_id">
                        <option value="">No owner</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('user_id', $track->user_id) === $user->id)>{{ $user->username }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="genre_id">Genre</label>
                    <select id="genre_id" name="genre_id">
                        <option value="">No genre</option>
                        @foreach ($genres as $genre)
                            <option value="{{ $genre->id }}" @selected((int) old('genre_id', $track->genre_id) === $genre->id)>{{ $genre->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="album_id">Album Link</label>
                    <select id="album_id" name="album_id">
                        <option value="">No album link</option>
                        @foreach ($albums as $album)
                            <option value="{{ $album->id }}" @selected((int) old('album_id', $track->album_id) === $album->id)>{{ $album->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="album">Album Text</label>
                    <input id="album" name="album" value="{{ old('album', $track->album) }}">
                </div>
                <div>
                    <label for="audio_file">Audio File Path</label>
                    <input id="audio_file" name="audio_file" value="{{ old('audio_file', $track->audio_file) }}" required>
                </div>
                <div>
                    <label for="cover_image">Cover Image Path</label>
                    <input id="cover_image" name="cover_image" value="{{ old('cover_image', $track->cover_image) }}">
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $track->status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top:16px;">
                <button class="primary">{{ $track->exists ? 'Save Track' : 'Add Track' }}</button>
                <a class="btn" href="{{ route('admin.dashboard') }}#tracks">Cancel</a>
            </div>
        </form>
    </section>
</main>
@endsection
