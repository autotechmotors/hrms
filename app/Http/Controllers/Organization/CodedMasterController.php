<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\CodedMasterRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class CodedMasterController extends Controller
{
    use GuardsCompany;

    abstract protected function model(): string;

    abstract protected function title(): string;

    abstract protected function routeName(): string;

    abstract protected function permission(): string;

    abstract protected function routeParameter(): string;

    protected function hasExitFlag(): bool
    {
        return false;
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', $this->model());
        $model = $this->model();
        $records = $model::query()
            ->with($this->indexRelations())
            ->visibleTo($request->user())
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('organization.coded.index', [
            'records' => $records,
            'title' => $this->title(),
            'route' => $this->routeName(),
            'permission' => $this->permission(),
            'showExit' => $this->hasExitFlag(),
            'showCategory' => $this->showsCategory(),
            'categoryLink' => $this->categoryLink(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', $this->model());

        return view('organization.coded.form', [
            'record' => null,
            'companies' => $this->visibleCompanies(),
            'title' => 'New '.$this->title(),
            'action' => route($this->routeName().'.store'),
            'showExit' => $this->hasExitFlag(),
        ] + $this->formExtras(null));
    }

    public function store(CodedMasterRequest $request): RedirectResponse
    {
        $this->authorize('create', $this->model());
        $data = $this->payload($request);
        $this->guardCompany((int) $data['company_id']);
        $model = $this->model();
        $model::query()->create($data);

        return redirect()->route($this->routeName().'.index')->with('success', $this->title().' saved.');
    }

    public function edit(Request $request): View
    {
        $record = $this->find($this->routeId());
        $this->authorize('update', $record);

        return view('organization.coded.form', [
            'record' => $record,
            'companies' => $this->visibleCompanies(),
            'title' => 'Edit '.$record->name,
            'action' => route($this->routeName().'.update', $record),
            'showExit' => $this->hasExitFlag(),
        ] + $this->formExtras($record));
    }

    public function update(CodedMasterRequest $request): RedirectResponse
    {
        $record = $this->find($this->routeId());
        $this->authorize('update', $record);
        $data = $this->payload($request);
        $this->guardCompany((int) $data['company_id']);
        $record->update($data);

        return redirect()->route($this->routeName().'.index')->with('success', $this->title().' updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $record = $this->find($this->routeId());
        $this->authorize('delete', $record);

        try {
            $record->delete();
        } catch (QueryException) {
            return back()->with('error', 'This record is in use and cannot be deleted.');
        }

        return back()->with('success', 'Deleted.');
    }

    protected function find(int $id): Model
    {
        $model = $this->model();

        return $model::query()->visibleTo(request()->user())->findOrFail($id);
    }

    protected function routeId(): int
    {
        $value = request()->route($this->routeParameter());

        return $value instanceof Model ? (int) $value->getKey() : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(CodedMasterRequest $request): array
    {
        $data = $request->safe()->only(['company_id', 'name', 'code', 'description']);
        $data['code'] = strtoupper((string) $data['code']);
        $data['is_active'] = $request->boolean('is_active');
        if ($this->hasExitFlag()) {
            $data['is_exit'] = $request->boolean('is_exit');
        }

        return $this->mutatePayload($data, $request);
    }

    /**
     * @return list<string>
     */
    protected function indexRelations(): array
    {
        return ['company'];
    }

    protected function showsCategory(): bool
    {
        return false;
    }

    protected function categoryLink(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formExtras(?Model $record): array
    {
        return [
            'showCategory' => false,
            'categories' => collect(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutatePayload(array $data, CodedMasterRequest $request): array
    {
        return $data;
    }
}
