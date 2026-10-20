@php($warn = $connected && ($composerWriteFailed || $reconnectMessage !== null))
<div @class(['card', 'lic', 'ok' => $connected && !$warn, 'warn' => $warn])>
    <div class="ico">
        <i class="fa {{ $connected ? 'fa-circle-check' : 'fa-key' }}"></i>
    </div>

    @if (!$connected)
        <h2>Already bought something? Paste your licence key</h2>
        <p>It is in your purchase email. This is the only setup.</p>

        <form data-request="onConnect" autocomplete="off">
            @csrf
            <input type="text" name="license_key" placeholder="Licence key" aria-label="Licence key" spellcheck="false">
            <button class="btn primary" type="submit">Connect</button>
        </form>

        @if ($notConnectedError)
            <div class="err" role="alert">{{ $notConnectedError }}</div>
        @endif
    @else
        <div class="keyline">
            <h2>Licence connected{{ $ownedCount !== null ? ' · '.$ownedCount.' extensions' : '' }}</h2>
            <span class="key">{{ $keyMasked }}</span>
            <button type="button" class="btn ghost rm" data-bs-toggle="collapse" data-bs-target="#connector-change-key">Change key</button>
        </div>
        <p class="covers"><i class="fa fa-globe"></i> Your key is locked to <b style="color:var(--at-text);font-weight:600">{{ $domain }}</b></p>

        @if ($composerWriteFailed)
            <p class="covers full">Licence connected, but this site's Composer files (composer.json and auth.json) could not be written.</p>
        @endif

        @if ($reconnectMessage !== null)
            <div class="full">
                <p class="covers">{{ $reconnectMessage }}</p>
                <form data-request="onReconnect">
                    @csrf
                    <button class="btn primary" type="submit">Reconnect</button>
                </form>
            </div>
        @endif

        <div class="collapse full{{ $changeKeyOpen ? ' show' : '' }}" id="connector-change-key">
            <form data-request="onConnect" autocomplete="off">
                @csrf
                <input type="text" name="license_key" placeholder="Licence key" aria-label="Licence key" spellcheck="false">
                <button class="btn primary" type="submit">Connect</button>
            </form>

            @if ($changeKeyError)
                <div class="err" role="alert">{{ $changeKeyError }}</div>
            @endif
        </div>
    @endif
</div>
