# Live UI — no full page load

Keywords: ajax, jQuery, JavaScript, Laravel Livewire, no page reload.

Filters, search, pagination, create, edit, delete, toggles, and modal saves stay on the current page. The shell (sidebar, top bar, bottom bar) does not reload. The first visit to a module may be a normal Laravel GET. After that, update only the region that changed.

Do not add Vue, React, Inertia, or a second Alpine app. Do not add Vite or npm. jQuery, Select2, and Flatpickr stay.

## Which tool

| Work | Use |
|---|---|
| List already built like Employees (`partial=1`) | jQuery `$.ajax`. Keep that pattern. |
| Row action on that list (delete, block, page, filter) | jQuery `$.ajax` to the existing route, then refresh the partial. |
| New screen, or a form with several fields and live validation | Laravel Livewire component. |
| File download, login, logout, MFA, device push (`device-events`, Hikvision) | Normal HTTP. Do not wrap these in ajax. |

When the first Livewire screen is added: `composer require livewire/livewire` only. In `resources/views/layouts/app.blade.php`, `@livewireStyles` in `<head>` and `@livewireScripts` before `app.js`. No Vite build.

## jQuery partial

Match `resources/views/employees/index.blade.php`.

- GET the same route with header `X-Requested-With: XMLHttpRequest` and `partial=1`.
- Controller returns the list fragment, not the layout.
- Replace the results node. `history.replaceState` so refresh keeps the filter.
- POST, PUT, and DELETE send `_token` and `_method`. On success, reload the partial. On failure, `window.toast`.
- Confirm with `window.askConfirm` before a destructive call. Do not use `window.confirm`.
- Intercept pagination links inside the partial. Do not follow them as a full navigation.
- Abort the previous list request when a new filter starts.

## Livewire

- One component per screen region: `app/Livewire/<Module>/<Name>.php` and `resources/views/livewire/<module>/<name>.blade.php`.
- `wire:submit.prevent` and `wire:click`. Do not add a form `action` that reloads the page.
- Validation errors render on the component. Success uses `window.toast`.
- Select2 and Flatpickr sit in `wire:ignore` and are re-inited after that region updates.
- Queries still use `visibleTo` and the same policies. Livewire is not a scope bypass.

## Do not

- `window.location` after a save, except when leaving for a different module or an auth screen.
- Cache partial HTML or Livewire responses in the service worker.
- Rename the employee list routes or the `partial` flag.
