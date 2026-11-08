<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\ProductionDatabaseSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('app:create-admin')]
#[Description('Create the initial administrator and required settings on an empty users table')]
class CreateInitialAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProductionDatabaseSeeder $seeder): int
    {
        if (User::query()->exists()) {
            $this->error('An account already exists. Use User Accounts to manage users.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively to enter administrator credentials.');

            return self::FAILURE;
        }

        $username = $this->ask('Username');
        $email = $this->ask('Email');
        $this->line('Use at least 16 characters with uppercase, lowercase, numbers, and symbols.');
        $password = $this->secret('Password');

        if ($password !== $this->secret('Confirm password')) {
            $this->error('Passwords do not match. No account was created.');

            return self::FAILURE;
        }

        try {
            $seeder->createAdmin(compact('username', 'email', 'password'));
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->info('Initial administrator created. You can now log in with your username and password.');

        return self::SUCCESS;
    }
}
