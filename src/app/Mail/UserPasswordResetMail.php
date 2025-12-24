<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
    ) {
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $this->user->email,
        ], false));

        return $this->subject('Welcome - Please reset your password')
            ->markdown('emails.users.reset-password', [
                'user' => $this->user,
                'resetUrl' => $resetUrl,
            ]);
    }
}
