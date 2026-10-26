<div class="tabs" role="tablist">
    @foreach ($list['tabs'] as $tab)
        <button type="button" role="tab" data-connector-tab="{{ $tab['id'] }}" data-empty="{{ $tab['empty'] }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
            {{ $tab['label'] }} <span @class(['n', 'hot' => $tab['hot']])>{{ $tab['count'] }}</span>
        </button>
    @endforeach
</div>

<div data-connector-entries>
    @foreach ($list['entries'] as $entry)
        @if ($entry['kind'] === 'group')
            <div class="group">{{ $entry['label'] }}</div>
        @else
            @include('avisaas.connector::connector._row', ['row' => $entry['row'], 'status' => $entry['status'], 'sub' => $entry['sub'], 'grouped' => $entry['grouped'], 'notice' => null, 'fail' => null])
        @endif
    @endforeach
</div>

@php($noEntries = $list['entries'] === [])
<div class="empty" data-connector-empty @if (!$noEntries) hidden @endif>{{ $noEntries ? $list['tabs'][0]['empty'] : '' }}</div>

@foreach ($list['entries'] as $entry)
    @if ($entry['kind'] === 'row')
        @include('avisaas.connector::connector._details', ['row' => $entry['row'], 'status' => $entry['status']])
    @endif
@endforeach
