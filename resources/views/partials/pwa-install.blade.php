<div class="pwa-sheet" id="pwaSheet" hidden>
    <div class="pwa-sheet-card" role="dialog" aria-modal="true" aria-labelledby="pwaSheetTitle">
        <img src="{{ asset('icons/apple-touch-icon.png') }}" alt="" width="64" height="64">
        <div>
            <h2 id="pwaSheetTitle">Add {{ config('app.name') }} to your Home Screen</h2>
            <p>The icon opens full screen, the same way an installed app does.</p>
        </div>
        <ol class="pwa-steps" id="pwaSteps">
            <li>Tap the Share button in Safari.</li>
            <li>Tap Add to Home Screen.</li>
            <li>Tap Add.</li>
        </ol>
        <button class="btn pr" type="button" data-pwa-close>Done</button>
    </div>
</div>
