<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFormSubmission;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private function grant(User $user, array $codes): void
    {
        $role = Role::firstOrCreate(
            ['org_id' => $user->org_id, 'code' => 'emp_import_test_'.substr(md5(implode(',', $codes)), 0, 8)],
            ['name' => 'Employee Import Test Role', 'is_system' => true]
        );
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'hr', 'action' => 'manage']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    public function test_user_with_hr_manage_permission_can_download_employee_import_template(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        $response = $this->actingAsOrgUser($user)->get(route('hr.employees.template'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('employee_code', $content);
        $this->assertStringContainsString('first_name', $content);
        $this->assertStringContainsString('position_name', $content);
        $this->assertStringContainsString('EMP-001', $content);
    }

    public function test_user_without_permission_cannot_download_template(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsOrgUser($user)->get(route('hr.employees.template'));

        $response->assertStatus(403);
    }

    public function test_can_bulk_import_employees_via_csv(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        $branch = Branch::create([
            'org_id' => $user->org_id,
            'code' => 'HQ',
            'name' => 'Headquarters',
            'status' => 'active',
        ]);

        $division = Division::create([
            'org_id' => $user->org_id,
            'branch_id' => $branch->id,
            'code' => 'TECH',
            'name' => 'Technology',
            'status' => 'active',
        ]);

        $dept = Department::create([
            'org_id' => $user->org_id,
            'branch_id' => $branch->id,
            'division_id' => $division->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'status' => 'active',
        ]);

        $csv = "employee_code,title,first_name,last_name,nickname,position_name,department_code,branch_code,employment_type,employment_status,started_on,national_id,bank_name,bank_account_number\n"
            ."EMP-101,นาย,สมชาย,ใจดี,ชาย,Fullstack Developer,ENG,HQ,monthly,active,2026-01-15,1100501234567,SCB,1234567890\n"
            ."EMP-102,นางสาว,วิไล,สุขสันต์,ไล,QA Lead,ENG,HQ,monthly,active,2026-02-01,1100507654321,KBANK,0987654321\n";

        $file = UploadedFile::fake()->createWithContent('employees.csv', $csv);

        $response = $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withoutMiddleware([EnsureFormSubmission::class])
            ->post(route('hr.employees.import'), [
                'file' => $file,
                'auto_create_position' => true,
                'update_existing' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $emp101 = Employee::where('org_id', $user->org_id)->where('employee_code', 'EMP-101')->first();
        $this->assertNotNull($emp101);
        $this->assertSame('สมชาย', $emp101->first_name);
        $this->assertSame('ใจดี', $emp101->last_name);
        $this->assertSame('ชาย', $emp101->nickname);
        $this->assertSame('Fullstack Developer', $emp101->position_title);
        $this->assertSame($dept->id, $emp101->department_id);
        $this->assertSame($branch->id, $emp101->branch_id);
        $this->assertSame('2026-01-15', $emp101->started_on->toDateString());

        // Test encryption of sensitive attributes
        $this->assertSame('1100501234567', $emp101->national_id);
        $this->assertNotSame('1100501234567', (string) $emp101->getRawOriginal('national_id'));
        $this->assertSame('1234567890', $emp101->bank_account_number);
        $this->assertNotSame('1234567890', (string) $emp101->getRawOriginal('bank_account_number'));

        // Test position was auto-created in positions table
        $pos = Position::where('org_id', $user->org_id)->where('name', 'Fullstack Developer')->first();
        $this->assertNotNull($pos);
        $this->assertSame($pos->id, $emp101->position_id);

        $emp102 = Employee::where('org_id', $user->org_id)->where('employee_code', 'EMP-102')->first();
        $this->assertNotNull($emp102);
        $this->assertSame('วิไล', $emp102->first_name);

        // Audit log
        $this->assertTrue(AuditLog::where('org_id', $user->org_id)->where('action', 'hr.employee.import_bulk')->exists());
    }

    public function test_duplicate_employee_code_fails_when_update_existing_is_false(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        Employee::create([
            'org_id' => $user->org_id,
            'employee_code' => 'EMP-DUP',
            'first_name' => 'Original Name',
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'cash',
        ]);

        $csv = "employee_code,first_name\n"
            ."EMP-DUP,Overwriting Name\n";

        $file = UploadedFile::fake()->createWithContent('dup.csv', $csv);

        $response = $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withoutMiddleware([EnsureFormSubmission::class])
            ->post(route('hr.employees.import'), [
                'file' => $file,
                'update_existing' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['import_errors']);

        // Check original employee was NOT modified
        $emp = Employee::where('org_id', $user->org_id)->where('employee_code', 'EMP-DUP')->first();
        $this->assertSame('Original Name', $emp->first_name);
    }

    public function test_duplicate_employee_code_updates_when_update_existing_is_true(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        Employee::create([
            'org_id' => $user->org_id,
            'employee_code' => 'EMP-UPD',
            'first_name' => 'Original Name',
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'cash',
        ]);

        $csv = "employee_code,first_name,nickname\n"
            ."EMP-UPD,Updated Name,NewNick\n";

        $file = UploadedFile::fake()->createWithContent('update.csv', $csv);

        $response = $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withoutMiddleware([EnsureFormSubmission::class])
            ->post(route('hr.employees.import'), [
                'file' => $file,
                'update_existing' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Check original employee WAS modified
        $emp = Employee::where('org_id', $user->org_id)->where('employee_code', 'EMP-UPD')->first();
        $this->assertSame('Updated Name', $emp->first_name);
        $this->assertSame('NewNick', $emp->nickname);
    }

    public function test_multi_tenant_isolation_during_import(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->grant($userA, ['hr.employees.manage']);

        $branchB = Branch::create([
            'org_id' => $userB->org_id,
            'code' => 'HQ-B',
            'name' => 'HQ Org B',
            'status' => 'active',
        ]);

        $divisionB = Division::create([
            'org_id' => $userB->org_id,
            'branch_id' => $branchB->id,
            'code' => 'TECH-B',
            'name' => 'Tech Org B',
            'status' => 'active',
        ]);

        // Create Department in Org B
        $deptB = Department::create([
            'org_id' => $userB->org_id,
            'branch_id' => $branchB->id,
            'division_id' => $divisionB->id,
            'code' => 'SEC-B',
            'name' => 'Security Org B',
            'status' => 'active',
        ]);

        $csv = "employee_code,first_name,department_code\n"
            ."EMP-TENANT,Somchai,SEC-B\n";

        $file = UploadedFile::fake()->createWithContent('tenant.csv', $csv);

        $response = $this->actingAsOrgUser($userA)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withoutMiddleware([EnsureFormSubmission::class])
            ->post(route('hr.employees.import'), [
                'file' => $file,
            ]);

        $response->assertRedirect();

        $emp = Employee::where('org_id', $userA->org_id)->where('employee_code', 'EMP-TENANT')->first();
        $this->assertNotNull($emp);
        // Department from Org B must NOT be assigned to Org A employee
        $this->assertNull($emp->department_id);
    }

    public function test_validation_errors_when_required_columns_missing(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        $csv = "employee_code,first_name\n"
            .",Missing Code\n"
            ."EMP-VALID,\n";

        $file = UploadedFile::fake()->createWithContent('invalid.csv', $csv);

        $response = $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withoutMiddleware([EnsureFormSubmission::class])
            ->post(route('hr.employees.import'), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['import_errors']);
        $this->assertSame(0, Employee::where('org_id', $user->org_id)->count());
    }

    public function test_can_bulk_import_employees_via_excel_xml(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        $xml = '<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Worksheet ss:Name="Sheet1">
  <Table>
   <Row>
    <Cell><Data ss:Type="String">employee_code</Data></Cell>
    <Cell><Data ss:Type="String">first_name</Data></Cell>
    <Cell><Data ss:Type="String">last_name</Data></Cell>
    <Cell><Data ss:Type="String">position_name</Data></Cell>
    <Cell><Data ss:Type="String">employment_type</Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String">EMP-XLS-1</Data></Cell>
    <Cell><Data ss:Type="String">Anan</Data></Cell>
    <Cell><Data ss:Type="String">Suksabai</Data></Cell>
    <Cell><Data ss:Type="String">Support Engineer</Data></Cell>
    <Cell><Data ss:Type="String">monthly</Data></Cell>
   </Row>
  </Table>
 </Worksheet>
</Workbook>';

        $file = UploadedFile::fake()->createWithContent('employees.xml', $xml);

        $response = $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withoutMiddleware([EnsureFormSubmission::class])
            ->post(route('hr.employees.import'), [
                'file' => $file,
                'auto_create_position' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $emp = Employee::where('org_id', $user->org_id)->where('employee_code', 'EMP-XLS-1')->first();
        $this->assertNotNull($emp);
        $this->assertSame('Anan', $emp->first_name);
        $this->assertSame('Support Engineer', $emp->position_title);
    }
}
