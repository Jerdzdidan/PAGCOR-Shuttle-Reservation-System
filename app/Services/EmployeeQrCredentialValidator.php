<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Validation\ValidationException;

class EmployeeQrCredentialValidator
{
    public function __construct(
        private EmployeeQrCredential $credentials,
        private EmployeeIdentifier $identifiers,
    ) {}

    public function resolve(string $credential): Employee
    {
        $parts = parse_url(trim($credential));

        if ($parts === false || ! isset($parts['path'])) {
            $this->invalidCredential();
        }

        $path = '/'.ltrim((string) $parts['path'], '/');

        if (preg_match('#^/employee/login/qr/(\d{2}-\d{5})$#', $path, $matches) !== 1) {
            $this->invalidCredential();
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        if (
            array_keys($query) !== ['signature']
            || ! is_string($query['signature'])
            || mb_strlen($query['signature']) !== 64
        ) {
            $this->invalidCredential();
        }

        $employee = $this->identifiers->resolve($matches[1]);

        if ($employee === null) {
            $this->invalidCredential();
        }

        $expectedParts = parse_url($this->credentials->loginUrl($employee));

        if ($expectedParts === false) {
            $this->invalidCredential();
        }

        parse_str((string) ($expectedParts['query'] ?? ''), $expectedQuery);

        if (
            '/'.ltrim((string) ($expectedParts['path'] ?? ''), '/') !== $path
            || ! isset($expectedQuery['signature'])
            || ! hash_equals((string) $expectedQuery['signature'], $query['signature'])
        ) {
            $this->invalidCredential();
        }

        return $employee;
    }

    private function invalidCredential(): never
    {
        throw ValidationException::withMessages([
            'credential' => 'This employee QR code is invalid.',
        ]);
    }
}
