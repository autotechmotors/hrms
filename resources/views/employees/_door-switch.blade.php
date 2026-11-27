@if($canBlock)
    <label class="el-block-switch" title="{{ $title }}">
        <input
            type="checkbox"
            class="el-door-toggle"
            data-url="{{ $url }}"
            data-field="{{ $field }}"
            data-on="{{ $on }}"
            data-off="{{ $off }}"
            @checked($checked)
        >
        <span class="el-switch-ui" aria-hidden="true"></span>
        <span class="el-block-label">{{ $checked ? $on : $off }}</span>
    </label>
@else
    <span class="b {{ $checked ? 'er' : 'ok' }}">{{ $checked ? $on : $off }}</span>
@endif
