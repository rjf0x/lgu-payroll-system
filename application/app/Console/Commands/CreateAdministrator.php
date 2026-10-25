<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Rules\SupportedPassword;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create the initial administrator using interactive, hidden password input';

    public function handle(): int
    {
        if (User::where('role', Role::Administrator)->exists()) {
            $this->error('An administrator already exists. Manage accounts through the application.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Administrator name'),
            'email' => Str::lower(trim((string) $this->ask('Administrator email'))),
            'password' => $this->secret('Password (12+ characters, upper/lowercase and numbers)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['bail', 'required', 'string', new SupportedPassword, 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // Do not hold the shared lock while waiting for interactive input.
        $lock = Cache::lock('initial-administrator-provisioning', 30);
        if (! $lock->get()) {
            $this->error('Another administrator setup is in progress. Try again.');

            return self::FAILURE;
        }

        try {
            if (User::where('role', Role::Administrator)->exists()) {
                $this->error('An administrator already exists. Manage accounts through the application.');

                return self::FAILURE;
            }
            $values = $validator->safe()->except('password_confirmation');
            $values['password'] = Hash::make($values['password']);
            $account = new User($values);
            $account->role = Role::Administrator;
            $account->is_active = true;
            $account->save();
        } catch (UniqueConstraintViolationException) {
            $this->error('This email address is already in use.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
        $this->info('Administrator created. Sign in to manage other accounts.');

        return self::SUCCESS;
    }
}
