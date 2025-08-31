<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class CreateTestUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-test {--email=test@example.com} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('email');
        if($this->option('password')) {
            $password = $this->option('password');
        } else {
            $password = Str::uuid();
        }

        $user = User::where('email', $email)->first();
        if($user){
            $this->info("Test user already exists:");
            $this->line("Email: {$user->email}");
            return 0;
        }

        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Test user created:");
        $this->line("Email: {$user->email}");
        $this->line("Password: {$password}");

        return 0;
    }
}
