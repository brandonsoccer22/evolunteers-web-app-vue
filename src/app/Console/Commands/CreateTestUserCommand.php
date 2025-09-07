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
    protected $signature = 'user:create-test {--email=test@example.com} {--password=} {--set-password-for-existing}';

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
        $set_password_for_existing = $this->option('set-password-for-existing');
        if($this->option('password')) {
            $password = $this->option('password');
        } else {
            $password = Str::uuid();
        }

        $user = User::where('email', $email)->first();
        if($user && !$set_password_for_existing){
            $this->info("Test user already exists:");
            $this->line("Email: {$user->email}");
            return 0;
        }

        $info_message = "Test user created:";
        if(!$user){

             $user = User::create([
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $email,
                'password' => Hash::make($password),
            ]);
        } elseif($set_password_for_existing){
            $info_message = "Test user updated:";
            $user->password = Hash::make($password);
            $user->save();
        }

        $this->info($info_message);
        $this->line("Email: {$user->email}");
        $this->line("Password: {$password}");

        return 0;
    }
}
