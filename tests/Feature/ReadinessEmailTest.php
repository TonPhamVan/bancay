<?php

namespace Tests\Feature;

use Exceedone\Exment\Notifications\Mail\MailChannel;
use Exceedone\Exment\Notifications\Mail\MailInfo;
use Exceedone\Exment\Notifications\Mail\MailMessage;
use Exceedone\Exment\Validator\ExmentCustomValidator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Tests\TestCase;

final class ReadinessEmailTest extends TestCase
{
    public function test_default_email_rule_rejects_folded_crlf_addresses(): void
    {
        foreach (["\"first\r\n second\"@example.test", "buyer(\r\n comment)@example.test", "buyer@example.test\r\nBcc: other@example.test"] as $value) {
            $this->assertTrue(Validator::make(['email' => $value], ['email' => ['email']])->fails());
        }
    }

    public function test_native_exment_validator_extensions_and_plain_email_still_work(): void
    {
        $validator = Validator::make(['email' => 'buyer+readiness@example.test'], ['email' => ['email:rfc']]);
        $this->assertInstanceOf(ExmentCustomValidator::class, $validator);
        $this->assertTrue($validator->passes());
        $validator->appendMessages(['native' => ['Native extension error']]);
        $this->assertFalse($validator->passes());
        $this->assertContains('Native extension error', $validator->getMessageStrings());
        $this->assertTrue(Validator::make(['email' => null], ['email' => ['nullable', 'email']])->passes());
        $this->assertTrue(Validator::make(['email' => ['not-a-string']], ['email' => ['email']])->fails());
    }

    public function test_native_exment_mail_channel_sends_valid_mail_to_memory_only(): void
    {
        config(['mail.default' => 'array']);
        Mail::purge();
        $info = new MailInfo;
        $info->setFrom('sender@example.test');
        $info->setTo(['recipient@example.test']);
        $info->setSubject('Readiness synthetic mail');
        $info->setBody('Synthetic body');
        $info->setBodyType('text/plain');
        $message = (new MailMessage)->setMailInfo($info);
        $channel = new class extends MailChannel
        {
            public function sendWithoutHistory(MailMessage $message): void
            {
                $this->sendMail($message);
            }
        };
        $channel->sendWithoutHistory($message);
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('recipient@example.test', $email->getTo()[0]->getAddress());
        $this->assertSame('Readiness synthetic mail', $email->getSubject());
        $this->assertSame('Synthetic body', $email->getTextBody());
    }

    public function test_native_mail_channel_rejects_folded_recipient_before_transport(): void
    {
        config(['mail.default' => 'array']);
        Mail::purge();
        $info = new MailInfo;
        $info->setFrom('sender@example.test');
        $info->setTo(["\"first\r\n second\"@example.test"]);
        $info->setSubject('Readiness invalid recipient');
        $info->setBody('Synthetic body');
        $info->setBodyType('text/plain');
        $message = (new MailMessage)->setMailInfo($info);
        $channel = new class extends MailChannel
        {
            public function sendWithoutHistory(MailMessage $message): void
            {
                $this->sendMail($message);
            }
        };
        try {
            $channel->sendWithoutHistory($message);
            $this->fail('An unsafe recipient must not reach transport');
        } catch (RfcComplianceException|\InvalidArgumentException) {
            $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
        }
    }
}
