<?php

namespace Pos\Services;

use Illuminate\Support\Facades\Hash;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

final readonly class ManagerApproval
{
    private function __construct(private string $staffId, private string $branchId, private string $commandHash, private int $expiresAt) {}

    public static function verify(Staff $staff, string $password, array $command): self
    {
        $current = Staff::findOrFail($staff->id);
        abort_unless($current->active && $current->role === 'manager' && Hash::check($password, $current->password), 403, 'Manager re-authentication required.');

        return new self($current->id, $current->branch_id, hash('sha256', CanonicalJson::encode($command)), time() + 300);
    }

    public function assertFor(Staff $staff, array $command): void
    {
        $current = Staff::findOrFail($staff->id);
        abort_unless($current->active && $current->role === 'manager' && $current->id === $this->staffId && $current->branch_id === $this->branchId && $current->branch_id === $staff->branch_id && time() <= $this->expiresAt && hash_equals($this->commandHash, hash('sha256', CanonicalJson::encode($command))), 403, 'Manager approval does not match this operation.');
    }
}
