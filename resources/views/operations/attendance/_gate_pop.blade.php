<div id="gpPop" class="gp-pop" hidden>
    <div class="gp-sheet" role="dialog" aria-modal="true" aria-labelledby="gpTitle">
        <header class="gp-sheet-head">
            <div>
                <p class="gp-kicker">Attendance</p>
                <h3 id="gpTitle">Gate pass</h3>
            </div>
            <button type="button" class="ib" data-gp-close title="Close">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </header>
        <div id="gpBody" class="gp-sheet-body"></div>
    </div>
</div>
<script>
if (!window.gpReady) {
    window.gpReady = true;
    window.gateSvg = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/></svg>';
    window.gateHtml = function (passes) {
        if (!passes || !passes.length) return '—';
        return '<button type="button" class="ib gp-open" title="Gate pass" data-passes="' + encodeURIComponent(JSON.stringify(passes)) + '">' + window.gateSvg + '</button>';
    };
    function gpEsc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }
    function gpPhoto(src, label) {
        if (!src) {
            return '<div class="gp-shot is-empty"><span>' + gpEsc(label) + '</span><b>No photo</b></div>';
        }
        return '<button type="button" class="att-zoom gp-shot" data-photo="' + gpEsc(src) + '"><img src="' + gpEsc(src) + '" alt="' + gpEsc(label) + ' photo"><span>' + gpEsc(label) + '</span></button>';
    }
    function gpOpen(passes) {
        const body = document.getElementById('gpBody');
        const pop = document.getElementById('gpPop');
        const title = document.getElementById('gpTitle');
        if (!body || !pop) return;
        const list = passes || [];
        if (title) title.textContent = list.length > 1 ? 'Gate passes' : 'Gate pass';
        body.innerHTML = list.map(function (pass, index) {
            return '<article class="gp-one">'
                + '<header class="gp-one-head">'
                + '<div><span class="gp-badge">' + gpEsc(pass.type) + '</span>'
                + (pass.leave ? '<span class="gp-badge is-soft">' + gpEsc(pass.leave) + '</span>' : '')
                + '</div>'
                + (list.length > 1 ? '<small>Pass ' + (index + 1) + '</small>' : '')
                + '</header>'
                + '<dl class="gp-facts">'
                + '<div><dt>Taken</dt><dd>' + gpEsc(pass.taken) + '</dd></div>'
                + '<div class="gp-span"><dt>Reason</dt><dd>' + gpEsc(pass.reason || '—') + '</dd></div>'
                + '<div><dt>Let out</dt><dd>' + gpEsc(pass.out) + '</dd></div>'
                + '<div><dt>Let in</dt><dd>' + gpEsc(pass.in) + '</dd></div>'
                + '</dl>'
                + '<div class="gp-photos">' + gpPhoto(pass.out_photo, 'Machine out') + gpPhoto(pass.in_photo, 'Machine in') + '</div>'
                + '</article>';
        }).join('') || '<p class="gp-miss">No gate pass for this date.</p>';
        pop.hidden = false;
    }
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.gp-open');
        if (button) {
            event.preventDefault();
            event.stopPropagation();
            let passes = [];
            try { passes = JSON.parse(decodeURIComponent(button.getAttribute('data-passes') || '')); } catch (e) { passes = []; }
            gpOpen(passes);
            return;
        }
        if (event.target.closest('[data-gp-close]') || event.target.id === 'gpPop') {
            const pop = document.getElementById('gpPop');
            if (pop) pop.hidden = true;
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        const pop = document.getElementById('gpPop');
        if (pop) pop.hidden = true;
    });
}
</script>
