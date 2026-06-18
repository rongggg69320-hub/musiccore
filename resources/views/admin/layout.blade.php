<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin Panel' }}</title>
    <style>
        :root { color-scheme: light; --bg:#f4f6f8; --panel:#fff; --text:#172026; --muted:#68727d; --line:#dfe3e8; --primary:#2563eb; --danger:#dc2626; --ok:#16803c; --warn:#b45309; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Arial, sans-serif; background:var(--bg); color:var(--text); }
        a { color:var(--primary); text-decoration:none; }
        h1, h2, h3, p { margin-top:0; }
        .shell { max-width:1280px; margin:0 auto; padding:24px; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; }
        .admin-header { background:#fff; border:1px solid var(--line); border-radius:8px; padding:18px; }
        .brand { font-size:24px; font-weight:800; letter-spacing:0; }
        .nav { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .panel { background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:18px; margin-bottom:18px; box-shadow:0 1px 2px rgba(17, 24, 39, .04); }
        .panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:14px; }
        .panel-head h2 { margin-bottom:4px; }
        .grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:12px; margin-bottom:18px; }
        .stat { padding:16px; background:#fff; border:1px solid var(--line); border-radius:8px; }
        .stat strong { display:block; font-size:30px; line-height:1; margin-bottom:8px; }
        .muted { color:var(--muted); font-size:13px; }
        .actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn, button { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 12px; border:1px solid var(--line); border-radius:6px; background:#fff; color:var(--text); cursor:pointer; font-weight:700; white-space:nowrap; }
        .btn.primary, button.primary { background:var(--primary); color:#fff; border-color:var(--primary); }
        .btn.danger, button.danger { background:var(--danger); color:#fff; border-color:var(--danger); }
        .btn:hover, button:hover { filter:brightness(.98); }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:12px 10px; border-bottom:1px solid var(--line); vertical-align:middle; font-size:14px; }
        th { color:var(--muted); background:#f9fafb; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
        tr:last-child td { border-bottom:0; }
        input, select, textarea { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:6px; background:#fff; color:var(--text); }
        textarea { min-height:100px; resize:vertical; }
        label { display:block; font-weight:700; margin:12px 0 6px; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
        .badge { display:inline-flex; align-items:center; min-height:24px; padding:4px 8px; border-radius:999px; background:#eef2ff; color:#1d4ed8; font-size:12px; font-weight:700; }
        .badge.good { background:#dcfce7; color:#166534; }
        .badge.bad { background:#fee2e2; color:#991b1b; }
        .badge.warn { background:#fef3c7; color:var(--warn); }
        .table-wrap { overflow-x:auto; border:1px solid var(--line); border-radius:8px; }
        .table-wrap table { min-width:760px; }
        .inline-form { margin:0; }
        .status-select { min-width:130px; }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:16px; }
        .alert.ok { background:#dcfce7; color:#166534; }
        .alert.err { background:#fee2e2; color:#991b1b; }
        .login { min-height:100vh; display:grid; place-items:center; padding:24px; }
        .login .panel { width:min(420px, 100%); }
        .pagination { margin-top:12px; font-size:14px; }
        .pagination nav > div:first-child { display:none; }
        .pagination nav > div:last-child { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .pagination p { margin:0; color:var(--muted); }
        .pagination a, .pagination span { display:inline-flex; align-items:center; justify-content:center; min-height:32px; }
        .pagination svg { width:18px; height:18px; vertical-align:middle; }
        @media (max-width: 860px) { .grid, .form-grid { grid-template-columns:1fr; } .topbar, .panel-head { align-items:flex-start; flex-direction:column; } .shell { padding:16px; } }
    </style>
</head>
<body>
    @yield('body')
</body>
</html>
