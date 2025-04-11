<?php

namespace c975L\ShopBundle\Message;

class ProcessMediaMessage
{
    public function __construct(
        private readonly string $entityClass,
        private readonly int $entityId,
        private readonly string $operation = 'resize', // 'resize', 'delete', 'rename'
    ) {
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }
}