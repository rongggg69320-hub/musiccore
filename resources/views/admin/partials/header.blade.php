<header class="topbar admin-header">
    <div>
        <div class="brand">Music Admin</div>
        <div class="muted">Accounts, genres, tracks, albums, and publishing controls</div>
    </div>
    <nav class="nav">
        <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
        <a class="btn" href="{{ route('admin.users.index') }}">Accounts</a>
        <a class="btn" href="{{ route('admin.genres.index') }}">Genres</a>
        <a class="btn" href="{{ route('admin.tracks.index') }}">Tracks</a>
        <a class="btn" href="{{ route('admin.albums.index') }}">Albums</a>
        <form method="post" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit">Logout</button>
        </form>
    </nav>
</header>
