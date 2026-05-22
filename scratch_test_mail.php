<?php

require_once __DIR__ . '/vendor/autoload_runtime.php';

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Dotenv\Dotenv;

return function (array $context) {
    $dotenv = new Dotenv();
    $dotenv->load(__DIR__ . '/.env');

    // Manually create the mailer to see the error
    $dsn = $_ENV['MAILER_DSN'];
    $sender = $_ENV['MAILER_SENDER'];

    try {
        $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        $email = (new Email())
            ->from($sender)
            ->to('test@example.com') // Change to a real email if needed for testing, but here we just want to see if it connects
            ->subject('Test Gmail SMTP')
            ->text('Ceci est un test.');

        $mailer->send($email);
        return new \Symfony\Component\HttpFoundation\Response('Email envoyé !');
    } catch (\Exception $e) {
        return new \Symfony\Component\HttpFoundation\Response('Erreur : ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
};
