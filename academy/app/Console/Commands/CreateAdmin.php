<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'academy:create-admin {--name=} {--email=} {--password= : Avoid on shared shells; you will be prompted if omitted}';

    protected $description = 'Interactively create (or promote) an administrator account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('الاسم الكامل / Full name');
        $email = mb_strtolower(trim($this->option('email') ?: $this->ask('البريد الإلكتروني / Email')));
        $password = $this->option('password') ?: $this->secret('كلمة المرور / Password (min 10 chars)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user && ! $this->confirm("A user with {$email} exists. Promote to administrator and reset the password?", false)) {
            return self::FAILURE;
        }
        $user ??= new User(['email' => $email]);
        $user->fill(['name' => $name, 'password' => $password]);
        $user->role = 'admin';
        $user->is_active = true;
        $user->email_verified_at ??= now();
        $user->save();

        $this->info("Administrator ready: {$email}");
        $this->line('Dashboard: '.route('admin.dashboard'));

        return self::SUCCESS;
    }
}
