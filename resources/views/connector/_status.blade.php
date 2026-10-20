<div class="st">
    <span class="pill {{ $status['tone'] }}">@if ($status['icon'] !== null)<i class="fa {{ $status['icon'] }}"></i> @endif{{ $status['label'] }}</span>
    @if ($status['line'] !== null)
        <small>{{ $status['line'] }}</small>
    @endif
    @if ($row['activate_theme'])
        <small><a href="{{ admin_url('themes') }}">Activate it in Design → Themes</a></small>
    @endif
</div>
