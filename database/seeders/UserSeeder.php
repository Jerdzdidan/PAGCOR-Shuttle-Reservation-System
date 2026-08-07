<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Back-office accounts. Every administrator here can sign in with the password
 * "password" except the root account, which keeps its historical credentials.
 */
class UserSeeder extends Seeder
{
    public const ROOT_EMAIL = 'root@gmail.com';

    public const OPERATIONS_ADMIN_EMAIL = 'operations@pagcor.example';

    public const DISPATCH_ADMIN_EMAIL = 'dispatch@pagcor.example';

    public const AUDIT_ADMIN_EMAIL = 'audit@pagcor.example';

    public const NON_ADMIN_EMAIL = 'staff@pagcor.example';

    /**
     * Administrators used as the "finalized by" / "corrected by" actors so audit
     * trails are not all attributed to a single person.
     *
     * @return list<string>
     */
    public static function closeoutAdministratorEmails(): array
    {
        return [
            self::OPERATIONS_ADMIN_EMAIL,
            self::DISPATCH_ADMIN_EMAIL,
            self::ROOT_EMAIL,
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Root User',
                'email' => self::ROOT_EMAIL,
                'password' => '123456',
                'user_type' => 'ADMIN',
            ],
            [
                'name' => 'Olivia Ramirez',
                'email' => self::OPERATIONS_ADMIN_EMAIL,
                'password' => 'password',
                'user_type' => 'ADMIN',
            ],
            [
                'name' => 'Dennis Aguilar',
                'email' => self::DISPATCH_ADMIN_EMAIL,
                'password' => 'password',
                'user_type' => 'ADMIN',
            ],
            [
                'name' => 'Aileen Bustamante',
                'email' => self::AUDIT_ADMIN_EMAIL,
                'password' => 'password',
                'user_type' => 'ADMIN',
            ],
            [
                'name' => 'Marco Silva',
                'email' => self::NON_ADMIN_EMAIL,
                'password' => 'password',
                'user_type' => 'EMPLOYEE',
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make($user['password']),
                    'user_type' => $user['user_type'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
