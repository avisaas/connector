<div class="p-4">
    <div class="avisaas-connector">
        <div class="ph">
            <div>
                <h1>Marketplace</h1>
                <p>Buy, install and update avisaas extensions for your restaurant.</p>
            </div>
            <div class="right">
                @if (!empty($catalogueAsOf))
                    <span class="asof">{{ $catalogueAsOf }}</span>
                @endif
                <a class="btn" href="{{ admin_url('avisaas/connector') }}"><i class="fa fa-rotate"></i> Check for updates</a>
            </div>
        </div>

        <div id="connector-licence-card">
            {!! $licence !!}
        </div>

        <div class="card">
            @if ($status === 'error')
                <div class="notice bad" role="alert">
                    <i class="fa fa-triangle-exclamation"></i>
                    <div>{{ $message }}</div>
                </div>
            @else
                @if ($status === 'undetermined' && !empty($message))
                    <div class="notice" role="status">
                        <i class="fa fa-circle-exclamation"></i>
                        <div>{{ $message }}</div>
                    </div>
                @endif

                @include('avisaas.connector::connector._list')
            @endif
        </div>
    </div>
</div>
