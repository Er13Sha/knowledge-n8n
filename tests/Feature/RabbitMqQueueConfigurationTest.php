<?php

use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

test('rabbitmq connection is configured for document processing', function () {
    $connection = config('queue.connections.rabbitmq');

    expect($connection)
        ->toBeArray()
        ->and($connection['driver'])->toBe('rabbitmq')
        ->and($connection['queue'])->toBeString()->not->toBeEmpty()
        ->and($connection['after_commit'])->toBeTrue()
        ->and($connection['options']['heartbeat'])->toBeGreaterThan(0)
        ->and($connection['options']['read_timeout'])
        ->toBeGreaterThan($connection['options']['heartbeat'] * 2);

    expect(app('queue')->connection('rabbitmq'))->toBeInstanceOf(RabbitMQQueue::class);
});
