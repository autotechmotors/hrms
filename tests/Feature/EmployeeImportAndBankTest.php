<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class EmployeeImportAndBankTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        Storage::fake('local');
    }

    public function test_invalid_import_creates_nobody(): void
    {
        $org = $this->makeCompany('IMP');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)->post(route('employees.import.store'), [
            'company_id' => $org['company']->id,
            'file' => $this->spreadsheet([
                ['employee_code', 'first_name', 'last_name', 'email', 'phone', 'joining_date', 'branch_code', 'department_code', 'designation_code', 'employee_type_code', 'employment_status_code', 'reporting_manager_code'],
                ['E100', 'Rita', 'Shah', '', '', '2026-04-01', 'MISSING', 'DEP', 'DES', 'PERM', 'CONF', ''],
            ]),
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, Employee::query()->count());
    }

    public function test_valid_import_creates_the_employee(): void
    {
        $org = $this->makeCompany('IMP');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)->post(route('employees.import.store'), [
            'company_id' => $org['company']->id,
            'file' => $this->spreadsheet([
                ['employee_code', 'first_name', 'last_name', 'email', 'phone', 'joining_date', 'branch_code', 'department_code', 'designation_code', 'employee_type_code', 'employment_status_code', 'reporting_manager_code'],
                ['E100', 'Rita', 'Shah', 'rita@example.test', '', '2026-04-01', 'BR', 'DEP', 'DES', 'PERM', 'CONF', ''],
            ]),
        ])->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', ['employee_code' => 'E100', 'company_id' => $org['company']->id]);
    }

    public function test_bank_account_is_masked_and_replaced_without_losing_history(): void
    {
        $org = $this->makeCompany('BNK');
        $employee = $this->makeEmployee($org, 'BNK1');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)->post(route('employees.bank-accounts.store', $employee), [
            'beneficiary_name' => 'Rita Shah',
            'bank_name' => 'Example Bank',
            'account_number' => '123456789012',
            'ifsc' => 'EXAM0001234',
            'effective_from' => '2026-04-01',
        ])->assertRedirect();

        $this->actingAsScoped($hr)->post(route('employees.bank-accounts.store', $employee), [
            'beneficiary_name' => 'Rita Shah',
            'bank_name' => 'Example Bank',
            'account_number' => '999900001111',
            'ifsc' => 'EXAM0001234',
            'effective_from' => '2026-08-01',
        ])->assertRedirect();

        $this->assertSame(2, EmployeeBankAccount::query()->count());
        $this->assertSame(1, EmployeeBankAccount::query()->where('is_active', true)->count());

        $html = $this->actingAsScoped($hr)->get(route('employees.show', $employee))->assertOk()->getContent();
        $this->assertStringContainsString('XXXXXX1111', $html);
        $this->assertStringContainsString('999900001111', $html);

        $viewer = $this->makeUser('manager');
        $employee->update(['user_id' => $viewer->id, 'reporting_manager_id' => null]);
        $hidden = $this->actingAsScoped($viewer->fresh())->get(route('employees.show', $employee))->assertOk()->getContent();
        $this->assertStringNotContainsString('999900001111', $hidden);
    }

    public function test_document_from_another_company_is_not_found(): void
    {
        $first = $this->makeCompany('D1');
        $second = $this->makeCompany('D2');
        $employee = $this->makeEmployee($second, 'DOC1');
        $document = EmployeeDocument::query()->create([
            'employee_id' => $employee->id,
            'title' => 'ID',
            'file_path' => 'employee-documents/secret.pdf',
            'original_name' => 'secret.pdf',
            'size' => 10,
        ]);
        $hr = $this->makeUser('hr-admin', $first['company']);

        $this->actingAsScoped($hr)
            ->get(route('employees.documents.download', [$employee, $document]))
            ->assertNotFound();
    }

    public function test_a_removed_employee_post_returns_to_the_list(): void
    {
        $org = $this->makeCompany('GONE');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->get('/employees/99999')
            ->assertNotFound();

        $this->actingAsScoped($hr)
            ->post('/employees/99999', ['_method' => 'DELETE'])
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('error', 'This employee is no longer in the list. Refresh and try again.');

        $this->actingAsScoped($hr)
            ->postJson('/employees/99999', ['_method' => 'DELETE'])
            ->assertNotFound()
            ->assertJsonPath('message', 'This employee is no longer in the list. Refresh and try again.');
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function spreadsheet(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = storage_path('framework/testing/import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
