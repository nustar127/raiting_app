<?php

namespace App\Services\Import;

final class ImportResult
{
    public int $saved = 0;
    public int $failed = 0;

    /**
     * @var array<array{row: int, field: string|null, message: string}>
     */
    public array $errors = [];

    /**
     * @var array<int, array{row: int, login: string, temporary_password: string, full_name: string, role: string}>
     */
    public array $created = [];

    public function addError(int $row, ?string $field, string $message): void
    {
        $this->failed++;
        $this->errors[] = ['row' => $row, 'field' => $field, 'message' => $message];
    }

    public function addCreated(int $row, string $login, string $password, string $fullName, string $role): void
    {
        $this->saved++;
        $this->created[] = [
            'row' => $row,
            'login' => $login,
            'temporary_password' => $password,
            'full_name' => $fullName,
            'role' => $role,
        ];
    }

    public function status(): string
    {
        if ($this->failed === 0 && $this->saved > 0) {
            return 'success';
        }
        if ($this->saved === 0) {
            return 'failed';
        }
        return 'partial';
    }

    public function toArray(): array
    {
        return [
            'saved' => $this->saved,
            'failed' => $this->failed,
            'status' => $this->status(),
            'errors' => $this->errors,
            'created' => $this->created,
        ];
    }
}
