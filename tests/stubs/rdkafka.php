<?php

declare(strict_types=1);

namespace RdKafka;

// The part of ext-rdkafka the tests need, for machines without the extension.
class ProducerTopic
{
    public function produce(int $partition, int $msgflags, ?string $payload = null, ?string $key = null): void {}

    public function getName(): string
    {
        return '';
    }
}
