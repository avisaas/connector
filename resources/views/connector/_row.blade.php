@php($toggleable = $canToggle && !$row['is_theme'])
<div @class(['ext-row', 'sub' => $sub, 'off' => $row['disabled']]) id="connector-row-{{ $row['key'] }}" data-tabs="{{ implode(' ', $status['tabs']) }}">
    @include('avisaas.connector::connector._tile', ['icon' => $row['icon']])

    <div class="nm">{{ $row['label'] }}@if ($row['is_theme'])<span class="tag">Theme</span>@endif</div>

    @include('avisaas.connector::connector._status')

    <div class="ac">
        @switch($status['action'])
            @case('install')
                @if ($canInstall)
                    <button type="button" class="btn primary" data-connector-action="install" data-product="{{ $row['key'] }}" data-name="{{ $row['label'] }}"><i class="fa fa-download"></i> Install</button>
                @endif
                @break
            @case('update')
                @if ($canInstall)
                    <button type="button" class="btn primary" data-connector-action="update" data-product="{{ $row['key'] }}" data-name="{{ $row['label'] }}"><i class="fa fa-arrow-up"></i> Update</button>
                @endif
                @break
            @case('enable')
                @if ($toggleable)
                    <button type="button" class="btn" data-connector-action="enable" data-product="{{ $row['key'] }}">Enable</button>
                @endif
                @break
            @case('buy')
            @case('renew')
            @case('view')
                <a class="btn buy" href="{{ $status['href'] }}" target="_blank" rel="noopener noreferrer">{{ ucfirst($status['action']) }}</a>
                @break
        @endswitch

        @if ($status['installed'])
            <button type="button" class="btn icon ghost" data-connector-menu aria-haspopup="menu" aria-expanded="false" aria-label="More actions for {{ $row['label'] }}"><i class="fa fa-ellipsis-vertical"></i></button>
        @else
            <button type="button" class="btn icon ghost" data-connector-details="{{ $row['key'] }}" aria-label="Details for {{ $row['label'] }}"><i class="fa fa-circle-info"></i></button>
        @endif
    </div>

    @if ($notice !== null)
        <p class="note" role="status">{{ $notice }}</p>
    @endif

    @if ($fail !== null)
        <div class="fail" role="alert">
            <h4><i class="fa fa-triangle-exclamation"></i> {{ $row['label'] }} could not be {{ $fail['verb'] }} from here</h4>
            @if ($fail['notStarted'])
                <p>Nothing changed on your site.</p>
            @elseif ($fail['restored'])
                <p>Nothing changed: your site was restored from the backup taken a moment ago.</p>
            @elseif ($fail['action'] === 'delete')
                <p>Its settings and data were deleted, but its files are still on your site.</p>
            @endif
            <p>{{ $fail['reason'] }}</p>
            <p>To finish, send these lines to your hosting provider or developer. Once they have run them, click Check for updates.</p>
            <div class="cmd"><code>{{ implode("\n", $fail['lines']) }}</code><button type="button" class="btn" data-connector-copy><i class="fa fa-copy"></i> Copy</button></div>
            <div class="acts">
                <button type="button" class="btn" data-connector-action="{{ $fail['action'] }}" data-product="{{ $row['key'] }}" data-name="{{ $row['label'] }}" @if ($fail['purge'] !== null) data-purge="{{ $fail['purge'] ? '1' : '0' }}" @endif>Try again</button>
                <button type="button" class="btn ghost" data-connector-dismiss>Dismiss</button>
            </div>
        </div>
    @endif

    @if ($status['installed'])
        <div class="menu" role="menu" hidden>
            <button type="button" role="menuitem" data-connector-details="{{ $row['key'] }}"><i class="fa fa-circle-info"></i> View details</button>
            <a role="menuitem" href="{{ admin_url('avisaas/connector') }}"><i class="fa fa-rotate"></i> Check for updates</a>
            @if ($toggleable)
                @php($toggle = $row['disabled'] ? 'enable' : 'disable')
                <button type="button" role="menuitem" data-connector-action="{{ $toggle }}" data-product="{{ $row['key'] }}"><i class="fa fa-power-off"></i> {{ ucfirst($toggle) }}</button>
            @endif
            @if ($canInstall)
                <div class="sep"></div>
                <button type="button" role="menuitem" class="del" data-connector-confirm><i class="fa fa-trash-can"></i> Delete</button>
            @endif
        </div>

        @if ($canInstall)
            <div class="menu" role="dialog" aria-label="Delete {{ $row['label'] }}" tabindex="-1" hidden>
                <div class="confirm">
                    <b>Delete {{ $row['label'] }}?</b>
                    <span>Its files are removed from your site. You can install it again any time.</span>
                    <label><input type="checkbox"> Also delete its settings and data</label>
                    <div class="acts">
                        <button type="button" class="btn danger" data-connector-action="delete" data-product="{{ $row['key'] }}" data-name="{{ $row['label'] }}">Delete</button>
                        <button type="button" class="btn" data-connector-cancel>Cancel</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
