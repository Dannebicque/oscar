<?php

namespace App\Tests\Support;

use App\Entity\MailAccount;
use App\Mail\OutgoingMailer;
use Symfony\Component\Mime\Email;

final class FakeOutgoingMailer implements OutgoingMailer
{
    /** @var list<Email> */
    public array $sent = [];
    public bool $fail = false;

    public function send(MailAccount $account, Email $email): void
    {
        if ($this->fail) {
            throw new \RuntimeException('smtp down');
        }
        $this->sent[] = $email;
    }
}
