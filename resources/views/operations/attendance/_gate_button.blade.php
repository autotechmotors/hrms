@if(!empty($passes))
    <button type="button" class="ib gp-open" title="Gate pass" data-passes="{{ rawurlencode(json_encode($passes)) }}">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/></svg>
    </button>
@else
    —
@endif
