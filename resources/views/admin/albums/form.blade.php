@extends('admin.layout', ['title' => $album->exists ? 'Edit Album' : 'Add Album'])

@section('body')
<main class="shell">
    @include('admin.partials.header')

    <section class="panel">
        <h1>{{ $album->exists ? 'Edit Album' : 'Add Album' }}</h1>

        @if ($errors->any())
            <div class="alert err">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ $album->exists ? route('admin.albums.update', $album) : route('admin.albums.store') }}">
            @csrf
            @if ($album->exists)
                @method('put')
            @endif

            <div class="form-grid">
                <div>
                    <label for="title">Title</label>
                    <input id="title" name="title" value="{{ old('title', $album->title) }}" required>
                </div>
                <div>
                    <label for="artist_name">Artist Name</label>
                    <input id="artist_name" name="artist_name" value="{{ old('artist_name', $album->artist_name) }}">
                </div>
                <div>
                    <label for="user_id">Owner</label>
                    <select id="user_id" name="user_id">
                        <option value="">No owner</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('user_id', $album->user_id) === $user->id)>{{ $user->username }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $album->status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="cover_image">Cover Image Path</label>
                    <input id="cover_image" name="cover_image" value="{{ old('cover_image', $album->cover_image) }}">
                </div>
            </div>

            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $album->description) }}</textarea>

            <div class="actions" style="margin-top:16px;">
                <button class="primary">{{ $album->exists ? 'Save Album' : 'Add Album' }}</button>
                <a class="btn" href="{{ route('admin.dashboard') }}#albums">Cancel</a>
            </div>
        </form>
    </section>
</main>
@endsection
