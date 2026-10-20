@if ($icon === null)
    <span class="tile"><i class="fa fa-plug"></i></span>
@else
    @php($generated = generate_extension_icon($icon))
    <span class="tile" style="{{ $generated['styles'] }}"><i class="{{ $generated['class'] }}"></i></span>
@endif
