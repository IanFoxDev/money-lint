<?php

declare(strict_types=1);

namespace TxClients;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use RdKafka\ProducerTopic;
use Stripe\StripeClient;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

function refund(
    EntityManagerInterface $em,
    Connection $connection,
    ClientInterface $psr,
    RequestInterface $request,
    HttpClientInterface $symfony,
    MailerInterface $mailer,
    Email $email,
    Client $guzzle,
    AMQPChannel $channel,
    AMQPMessage $message,
    ProducerTopic $topic,
    StripeClient $stripe,
): void {
    $em->wrapInTransaction(function () use ($psr, $request, $symfony, $mailer, $email, $guzzle, $channel, $message, $topic, $stripe): void {
        $psr->sendRequest($request);
        $symfony->request('POST', 'https://psp.test/refunds');
        $mailer->send($email);
        $guzzle->post('https://psp.test/refunds');
        $channel->basic_publish($message, 'payments');
        $topic->produce(-1, 0, 'refunded');
        $stripe->refunds->create(['payment_intent' => 'pi_1']);

        // Not reported: no request leaves the process.
        $guzzle->getConfig('base_uri');
        $topic->getName();
    });

    $connection->transactional(function () use ($guzzle): void {
        $guzzle->request('POST', 'https://psp.test/refunds');
    });

    // Not reported: outside the transaction.
    $guzzle->post('https://psp.test/refunds');
}
