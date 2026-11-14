<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Install · HANDY NOW</title>
<style>body{margin:0;font-family:system-ui,sans-serif;background:#F4F6F8;color:#101D35;padding:24px}.c{max-width:640px;margin:40px auto;background:#fff;border-radius:16px;padding:28px;box-shadow:0 8px 24px rgba(16,29,53,.08)}h1{margin:0 0 4px}li{margin:6px 0}.ok{color:#047857}.bad{color:#b91c1c}button{background:#FF8A24;color:#fff;border:0;border-radius:12px;padding:14px 22px;font-weight:700;font-size:15px;cursor:pointer}button:disabled{opacity:.5}pre{background:#101D35;color:#d6dcea;padding:16px;border-radius:12px;white-space:pre-wrap;font-size:12px}a{color:#ec7008;font-weight:700}</style></head>
<body><div class="c">
<h1>HANDY NOW installer</h1>
<p>Sets up the database tables, service catalogue and your administrator account.</p>
@if($output === null)
<ul>
    <li class="{{ $phpOk ? 'ok' : 'bad' }}">PHP {{ $php }} {{ $phpOk ? '✓' : '– PHP 8.3 or newer is required (hPanel → Advanced → PHP Configuration)' }}</li>
    <li class="{{ $dbOk ? 'ok' : 'bad' }}">{{ $db }}</li>
    <li class="{{ $writable ? 'ok' : 'bad' }}">storage/ and bootstrap/cache {{ $writable ? 'writable ✓' : 'not writable – set folder permissions to 755' }}</li>
</ul>
<form method="POST" action="{{ url('/install') }}">
    @csrf <input type="hidden" name="token" value="{{ $token }}">
    <button @disabled(! ($phpOk && $dbOk && $writable))>Install HANDY NOW</button>
</form>
@else
<pre>{{ $output }}</pre>
@if($success ?? false)
<p class="ok"><b>Done!</b> For security, now remove <code>INSTALL_TOKEN</code> from your .env file.</p>
<p><a href="{{ url('/login') }}">Log in as administrator →</a> &nbsp; <a href="{{ url('/') }}">View website →</a></p>
@endif
@endif
</div></body></html>
