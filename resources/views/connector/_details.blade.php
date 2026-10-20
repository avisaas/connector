<dialog class="dlg" id="connector-details-{{ $row['key'] }}" aria-labelledby="connector-details-{{ $row['key'] }}-title">
    <div class="info">
        <div class="hd">
            @include('avisaas.connector::connector._tile', ['icon' => $row['icon']])

            <div>
                <h3 id="connector-details-{{ $row['key'] }}-title">{{ $row['label'] }}@if ($row['is_theme'])<span class="tag">Theme</span>@endif</h3>
                @include('avisaas.connector::connector._status')
            </div>
        </div>

        @if ($row['composer_package'] !== null)
            <details class="dev">
                <summary>For your developer</summary>
                <span class="mono">{{ $row['composer_package'] }}</span>
                <div class="cmd"><code>{{ $installInstructions->requireCommand($row['composer_package']) }}</code></div>
            </details>
        @endif

        <div class="acts">
            <button type="button" class="btn" data-connector-close>Close</button>
        </div>
    </div>
</dialog>
