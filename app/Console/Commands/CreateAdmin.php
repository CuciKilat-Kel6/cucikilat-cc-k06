<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'cucikilat:admin {email}';

    protected $description = 'Buat akun petugas CuciKilat dengan password tersembunyi';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->secret('Password (minimal 10 karakter)');
        $v = Validator::make(['email' => $email, 'password' => $password], ['email' => 'required|email|unique:users,email', 'password' => 'required|string|min:10']);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }
        User::create(['name' => 'Petugas CuciKilat', 'email' => $email, 'password' => Hash::make($password)]);
        $this->info('Akun petugas dibuat.');

        return self::SUCCESS;
    }
}
