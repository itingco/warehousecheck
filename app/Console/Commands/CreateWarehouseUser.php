<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateWarehouseUser extends Command
{
    protected $signature =
        'warehouse:user
        {username : Username login}
        {role : picker|checker|supervisor|admin}
        {--name= : Nama lengkap user}';

    protected $description =
        'Create warehouse checking user';

    public function handle(): int
    {
        $role = strtolower(
            (string) $this->argument('role')
        );

        if (
            !in_array(
                $role,
                [
                    'picker',
                    'checker',
                    'supervisor',
                    'admin',
                ],
                true
            )
        ) {
            $this->error(
                'Role harus picker, checker, supervisor, atau admin.'
            );

            return self::FAILURE;
        }

        $username = trim(
            (string) $this->argument('username')
        );

        if (
            User::query()
                ->where('username', $username)
                ->exists()
        ) {
            $this->error('Username sudah ada.');

            return self::FAILURE;
        }

        $name = trim(
            (string) (
                $this->option('name')
                ?: $this->ask('Nama lengkap')
            )
        );

        if ($name === '') {
            $this->error('Nama wajib diisi.');

            return self::FAILURE;
        }

        $password = $this->secret(
            'Password minimal 8 karakter'
        );

        if (
            !$password
            || strlen($password) < 8
        ) {
            $this->error(
                'Password minimal 8 karakter.'
            );

            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'name' => $name,
            'role' => $role,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $this->info('User berhasil dibuat.');

        return self::SUCCESS;
    }
}
