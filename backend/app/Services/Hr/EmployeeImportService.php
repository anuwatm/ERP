<?php

namespace App\Services\Hr;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeImportService
{
    /**
     * Parse uploaded file into array of associative rows.
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if ($ext === 'xlsx') {
            return $this->parseXlsx($file->getRealPath());
        }

        $content = (string) file_get_contents($file->getRealPath());
        if ($ext === 'xml' || ($ext === 'xls' && str_contains($content, '<?xml'))) {
            return $this->parseXmlSpreadsheet($content);
        }

        return $this->parseCsv($content);
    }

    /**
     * Import employees for an organization.
     *
     * @param  array{auto_create_position?: bool, update_existing?: bool}  $options
     * @return array{batch_id: string, total: int, created: int, updated: int, errors: array<string>}
     */
    public function importEmployees(string $orgId, string $actorUserId, array $rows, array $options = []): array
    {
        $batchId = (string) Str::uuid();
        $autoCreatePosition = (bool) ($options['auto_create_position'] ?? true);
        $updateExisting = (bool) ($options['update_existing'] ?? false);

        $created = 0;
        $updated = 0;
        $errors = [];

        // Preload reference maps for performance
        $branches = Branch::where('org_id', $orgId)->where('status', 'active')->get();
        $branchByCode = $branches->keyBy(fn ($b) => strtolower(trim($b->code)));
        $branchByName = $branches->keyBy(fn ($b) => strtolower(trim($b->name)));

        $divisions = Division::where('org_id', $orgId)->where('status', 'active')->get();
        $divisionByCode = $divisions->keyBy(fn ($d) => strtolower(trim($d->code)));
        $divisionByName = $divisions->keyBy(fn ($d) => strtolower(trim($d->name)));

        $departments = Department::where('org_id', $orgId)->where('status', 'active')->get();
        $departmentByCode = $departments->keyBy(fn ($d) => strtolower(trim($d->code)));
        $departmentByName = $departments->keyBy(fn ($d) => strtolower(trim($d->name)));

        $positions = Position::where('org_id', $orgId)->get();
        $positionByName = $positions->keyBy(fn ($p) => strtolower(trim($p->name)));
        $positionByCode = $positions->filter(fn ($p) => ! empty($p->code))->keyBy(fn ($p) => strtolower(trim($p->code)));

        $existingEmployees = Employee::where('org_id', $orgId)->get()->keyBy('employee_code');

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1;
                $empCode = trim((string) ($row['employee_code'] ?? ''));
                $firstName = trim((string) ($row['first_name'] ?? ''));

                if ($empCode === '') {
                    $errors[] = "Row #{$rowNumber}: Employee code is required.";

                    continue;
                }

                if ($firstName === '') {
                    $errors[] = "Row #{$rowNumber}: First name is required for employee code [{$empCode}].";

                    continue;
                }

                $existing = $existingEmployees->get($empCode);
                if ($existing && ! $updateExisting) {
                    $errors[] = "Row #{$rowNumber}: Employee code [{$empCode}] already exists in this organization.";

                    continue;
                }

                // Resolve Position
                $posRaw = trim((string) ($row['position_name'] ?? $row['position_code'] ?? ''));
                $positionId = null;
                $positionTitle = $posRaw !== '' ? $posRaw : null;

                if ($posRaw !== '') {
                    $posLower = strtolower($posRaw);
                    $matchedPos = $positionByName->get($posLower) ?? $positionByCode->get($posLower);

                    if ($matchedPos) {
                        $positionId = $matchedPos->id;
                        $positionTitle = $matchedPos->name;
                    } elseif ($autoCreatePosition) {
                        $newPos = Position::create([
                            'org_id' => $orgId,
                            'name' => $posRaw,
                            'code' => strtoupper(Str::slug(Str::limit($posRaw, 15, ''), '')),
                            'is_active' => true,
                        ]);
                        $positions->push($newPos);
                        $positionByName->put($posLower, $newPos);
                        $positionId = $newPos->id;
                        $positionTitle = $newPos->name;
                    }
                }

                // Resolve Branch
                $branchRaw = trim((string) ($row['branch_code'] ?? $row['branch_name'] ?? ''));
                $branchId = null;
                if ($branchRaw !== '') {
                    $bLower = strtolower($branchRaw);
                    $matchedBranch = $branchByCode->get($bLower) ?? $branchByName->get($bLower);
                    $branchId = $matchedBranch?->id;
                }

                // Resolve Division
                $divRaw = trim((string) ($row['division_code'] ?? $row['division_name'] ?? ''));
                $divisionId = null;
                if ($divRaw !== '') {
                    $dLower = strtolower($divRaw);
                    $matchedDiv = $divisionByCode->get($dLower) ?? $divisionByName->get($dLower);
                    $divisionId = $matchedDiv?->id;
                }

                // Resolve Department
                $deptRaw = trim((string) ($row['department_code'] ?? $row['department_name'] ?? ''));
                $departmentId = null;
                if ($deptRaw !== '') {
                    $deptLower = strtolower($deptRaw);
                    $matchedDept = $departmentByCode->get($deptLower) ?? $departmentByName->get($deptLower);
                    $departmentId = $matchedDept?->id;
                }

                // Resolve Manager
                $managerCode = trim((string) ($row['manager_code'] ?? ''));
                $managerId = null;
                if ($managerCode !== '' && $managerCode !== $empCode) {
                    $managerEmp = $existingEmployees->get($managerCode) ?? Employee::where('org_id', $orgId)->where('employee_code', $managerCode)->first();
                    $managerId = $managerEmp?->id;
                }

                // Parse Dates
                $startedOn = $this->parseDate($row['started_on'] ?? null);
                $dob = $this->parseDate($row['date_of_birth'] ?? null);
                $probationEndsOn = $this->parseDate($row['probation_ends_on'] ?? null);
                $resignedOn = $this->parseDate($row['resigned_on'] ?? null);

                // Enums
                $employmentType = $this->normalizeEnum($row['employment_type'] ?? null, ['monthly', 'daily', 'hourly', 'contractor'], 'monthly');
                $employmentStatus = $this->normalizeEnum($row['employment_status'] ?? null, ['probation', 'active', 'suspended', 'resigned', 'terminated'], 'active');
                $paymentMethod = $this->normalizeEnum($row['payment_method'] ?? null, ['bank_transfer', 'cash'], 'bank_transfer');
                $gender = $this->normalizeEnum($row['gender'] ?? null, ['female', 'male', 'non_binary', 'unspecified'], null);

                $payload = [
                    'org_id' => $orgId,
                    'employee_code' => $empCode,
                    'title' => trim((string) ($row['title'] ?? '')) ?: null,
                    'first_name' => $firstName,
                    'last_name' => trim((string) ($row['last_name'] ?? '')) ?: null,
                    'nickname' => trim((string) ($row['nickname'] ?? '')) ?: null,
                    'national_id' => trim((string) ($row['national_id'] ?? '')) ?: null,
                    'tax_id' => trim((string) ($row['tax_id'] ?? '')) ?: null,
                    'email' => trim((string) ($row['email'] ?? '')) ?: null,
                    'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
                    'gender' => $gender,
                    'date_of_birth' => $dob,
                    'position_id' => $positionId,
                    'position_title' => $positionTitle,
                    'branch_id' => $branchId,
                    'division_id' => $divisionId,
                    'department_id' => $departmentId,
                    'manager_id' => $managerId,
                    'employment_type' => $employmentType,
                    'employment_status' => $employmentStatus,
                    'started_on' => $startedOn,
                    'probation_ends_on' => $probationEndsOn,
                    'resigned_on' => $resignedOn,
                    'payment_method' => $paymentMethod,
                    'bank_name' => trim((string) ($row['bank_name'] ?? '')) ?: null,
                    'bank_account_number' => trim((string) ($row['bank_account_number'] ?? '')) ?: null,
                ];

                if ($existing && $updateExisting) {
                    $existing->update($payload);
                    $existingEmployees->put($empCode, $existing);
                    $updated++;
                } else {
                    $newEmp = Employee::create($payload);
                    $existingEmployees->put($empCode, $newEmp);
                    $created++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Audit Logging
        AuditLog::create([
            'org_id' => $orgId,
            'actor_user_id' => $actorUserId,
            'action' => 'hr.employee.import_bulk',
            'entity_type' => 'employee_batch',
            'entity_id' => $batchId,
            'after_json' => [
                'total' => count($rows),
                'created' => $created,
                'updated' => $updated,
                'errors_count' => count($errors),
                'auto_create_position' => $autoCreatePosition,
                'update_existing' => $updateExisting,
            ],
            'request_id' => (string) Str::uuid(),
        ]);

        return [
            'batch_id' => $batchId,
            'total' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Parse CSV string into array of normalized rows.
     */
    public function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        // Detect delimiter (comma, semicolon, tab)
        $firstLine = $lines[0];
        $delimiter = ',';
        if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        } elseif (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }

        $rawHeader = str_getcsv(array_shift($lines), $delimiter);
        $header = array_map([$this, 'normalizeHeaderName'], $rawHeader);

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter);
            $row = [];
            foreach ($header as $idx => $key) {
                if ($key !== '') {
                    $row[$key] = trim((string) ($cells[$idx] ?? ''));
                }
            }
            if (! empty(array_filter($row))) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Parse XLSX file into array of normalized rows.
     */
    public function parseXlsx(string $path): array
    {
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xlsx_emp_'.uniqid();
        @mkdir($tempDir, 0777, true);

        try {
            if (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive;
                if ($zip->open($path) === true) {
                    $zip->extractTo($tempDir);
                    $zip->close();
                }
            } else {
                @exec('tar -xf "'.escapeshellarg($path).'" -C "'.escapeshellarg($tempDir).'" 2>&1');
            }

            $sharedStringsFile = $tempDir.DIRECTORY_SEPARATOR.'xl'.DIRECTORY_SEPARATOR.'sharedStrings.xml';
            $sheetFile = $tempDir.DIRECTORY_SEPARATOR.'xl'.DIRECTORY_SEPARATOR.'worksheets'.DIRECTORY_SEPARATOR.'sheet1.xml';

            if (! file_exists($sheetFile)) {
                return [];
            }

            $sharedStrings = [];
            if (file_exists($sharedStringsFile)) {
                $xml = @simplexml_load_file($sharedStringsFile);
                if ($xml) {
                    foreach ($xml->si as $si) {
                        if (isset($si->t)) {
                            $sharedStrings[] = (string) $si->t;
                        } elseif (isset($si->r)) {
                            $text = '';
                            foreach ($si->r as $r) {
                                $text .= (string) $r->t;
                            }
                            $sharedStrings[] = $text;
                        } else {
                            $sharedStrings[] = '';
                        }
                    }
                }
            }

            $sheetXml = @simplexml_load_file($sheetFile);
            if (! $sheetXml || ! isset($sheetXml->sheetData->row)) {
                return [];
            }

            $header = [];
            $rows = [];

            foreach ($sheetXml->sheetData->row as $row) {
                $cellsByCol = [];
                $maxCol = 0;

                foreach ($row->c as $c) {
                    $ref = (string) $c['r'];
                    $colIdx = $this->colRefToIndex($ref);
                    $maxCol = max($maxCol, $colIdx);

                    $val = (string) $c->v;
                    $type = (string) $c['t'];
                    if ($type === 's' && isset($sharedStrings[(int) $val])) {
                        $val = $sharedStrings[(int) $val];
                    } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                        $val = (string) $c->is->t;
                    }
                    $cellsByCol[$colIdx] = trim($val);
                }

                $cells = [];
                for ($i = 0; $i <= $maxCol; $i++) {
                    $cells[] = $cellsByCol[$i] ?? '';
                }

                if (empty($header)) {
                    $header = array_map([$this, 'normalizeHeaderName'], $cells);

                    continue;
                }

                $rowData = [];
                foreach ($header as $idx => $key) {
                    if ($key !== '') {
                        $rowData[$key] = trim((string) ($cells[$idx] ?? ''));
                    }
                }
                if (! empty(array_filter($rowData))) {
                    $rows[] = $rowData;
                }
            }

            return $rows;
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    /**
     * Parse XML Spreadsheet into array of normalized rows.
     */
    public function parseXmlSpreadsheet(string $content): array
    {
        $xml = @simplexml_load_string($content);
        if (! $xml) {
            return [];
        }

        $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
        $xmlRows = $xml->xpath('//ss:Row') ?: $xml->xpath('//Row');

        if (empty($xmlRows)) {
            return [];
        }

        $header = [];
        $rows = [];

        foreach ($xmlRows as $row) {
            $cells = [];
            foreach ($row->children() as $cell) {
                $data = $cell->Data ?: $cell->children('ss', true)->Data;
                $cells[] = trim((string) $data);
            }

            if (empty($header)) {
                $header = array_map([$this, 'normalizeHeaderName'], $cells);

                continue;
            }

            $rowData = [];
            foreach ($header as $idx => $key) {
                if ($key !== '') {
                    $rowData[$key] = trim((string) ($cells[$idx] ?? ''));
                }
            }
            if (! empty(array_filter($rowData))) {
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    /**
     * Map arbitrary header names to standardized employee model keys.
     */
    public function normalizeHeaderName(string $col): string
    {
        $col = strtolower(trim($col));
        $col = str_replace([' ', '-', '.'], '_', $col);

        return match ($col) {
            'employee_code', 'code', 'emp_code', 'รหัสพนักงาน', 'รหัส' => 'employee_code',
            'title', 'prefix', 'คำนำหน้า', 'คำนำหน้านาม' => 'title',
            'first_name', 'name', 'fname', 'ชื่อ', 'ชื่อจริง' => 'first_name',
            'last_name', 'lname', 'surname', 'นามสกุล' => 'last_name',
            'nickname', 'ชื่อเล่น' => 'nickname',
            'position', 'position_name', 'position_title', 'ตำแหน่ง' => 'position_name',
            'position_code', 'รหัสตำแหน่ง' => 'position_code',
            'department', 'department_code', 'department_name', 'dept', 'แผนก', 'รหัสแผนก' => 'department_code',
            'branch', 'branch_code', 'branch_name', 'สาขา', 'รหัสสาขา' => 'branch_code',
            'division', 'division_code', 'division_name', 'ฝ่าย', 'รหัสฝ่าย' => 'division_code',
            'manager_code', 'manager', 'head', 'หัวหน้า', 'รหัสหัวหน้า' => 'manager_code',
            'employment_type', 'type', 'ประเภทการจ้าง', 'ประเภทพนักงาน' => 'employment_type',
            'employment_status', 'status', 'สถานะ', 'สถานะการทำงาน' => 'employment_status',
            'started_on', 'start_date', 'join_date', 'วันเริ่มงาน', 'วันที่เริ่มงาน' => 'started_on',
            'probation_ends_on', 'probation_date', 'วันผ่านโปร', 'วันสิ้นสุดทดลองงาน' => 'probation_ends_on',
            'resigned_on', 'resign_date', 'วันลาออก', 'วันที่ลาออก' => 'resigned_on',
            'national_id', 'citizen_id', 'id_card', 'เลขบัตรประชาชน', 'เลขประจำตัวประชาชน' => 'national_id',
            'tax_id', 'เลขประจำตัวผู้เสียภาษี' => 'tax_id',
            'date_of_birth', 'birth_date', 'dob', 'วันเกิด' => 'date_of_birth',
            'gender', 'sex', 'เพศ' => 'gender',
            'email', 'personal_email', 'อีเมล' => 'email',
            'phone', 'mobile', 'tel', 'เบอร์โทร', 'เบอร์โทรศัพท์' => 'phone',
            'payment_method', 'pay_method', 'วิธีจ่ายเงิน', 'วิธีรับเงิน' => 'payment_method',
            'bank_name', 'bank', 'ธนาคาร' => 'bank_name',
            'bank_account_number', 'account_number', 'bank_account', 'เลขบัญชี', 'เลขที่บัญชี' => 'bank_account_number',
            default => $col,
        };
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $val = trim($value);
        if ($val === '') {
            return null;
        }

        try {
            return Carbon::parse($val)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeEnum(?string $value, array $allowed, ?string $default): ?string
    {
        if (! $value) {
            return $default;
        }
        $clean = strtolower(trim($value));

        return in_array($clean, $allowed, true) ? $clean : $default;
    }

    private function colRefToIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/i', '', $ref);
        $len = strlen($letters);
        $idx = 0;
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord(strtoupper($letters[$i])) - ord('A') + 1);
        }

        return max(0, $idx - 1);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
