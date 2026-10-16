<input type="hidden" name="company_id" value="{{ old('company_id', $companyId ?? $workspaceCompany?->id) }}">
@if(!empty($withBranch))
    <input type="hidden" name="branch_id" value="{{ old('branch_id', $branchId ?? $workspaceBranch?->id) }}">
@endif
