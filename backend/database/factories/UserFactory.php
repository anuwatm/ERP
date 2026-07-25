<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $organization = Organization::create([
            'name' => fake()->company(),
            'currency' => 'THB',
            'timezone' => 'Asia/Bangkok',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'org_id' => $organization->id,
            'code' => '000001',
            'name' => 'Head Office',
            'is_head_office' => true,
            'status' => 'active',
        ]);

        $division = Division::create([
            'org_id' => $organization->id,
            'branch_id' => $branch->id,
            'code' => '000001',
            'name' => 'Default Division',
            'status' => 'active',
        ]);

        $department = Department::create([
            'org_id' => $organization->id,
            'branch_id' => $branch->id,
            'division_id' => $division->id,
            'code' => '000001',
            'name' => 'Default Department',
            'status' => 'active',
        ]);

        $name = fake()->name();

        return [
            'org_id' => $organization->id,
            'branch_id' => $branch->id,
            'division_id' => $division->id,
            'department_id' => $department->id,
            'name' => $name,
            'display_name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'auth_provider' => 'local',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'invited',
            'password' => null,
            'invited_at' => now(),
        ]);
    }
}
