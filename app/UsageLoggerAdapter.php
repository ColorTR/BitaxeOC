<?php

declare(strict_types=1);

namespace BitaxeOc\App;

interface UsageLoggerAdapter
{
    public function append(array $record): void;

    public function readLatest(int $limit): array;

    public function summarizeAll(): array;
}

final class UsageLoggerFileAdapter implements UsageLoggerAdapter
{
    /** @var callable(array):void */
    private $appendFn;
    /** @var callable(int):array */
    private $readLatestFn;
    /** @var callable():array */
    private $summarizeAllFn;

    public function __construct(callable $appendFn, callable $readLatestFn, callable $summarizeAllFn)
    {
        $this->appendFn = $appendFn;
        $this->readLatestFn = $readLatestFn;
        $this->summarizeAllFn = $summarizeAllFn;
    }

    public function append(array $record): void
    {
        ($this->appendFn)($record);
    }

    public function readLatest(int $limit): array
    {
        return ($this->readLatestFn)($limit);
    }

    public function summarizeAll(): array
    {
        return ($this->summarizeAllFn)();
    }
}

final class UsageLoggerDbAdapter implements UsageLoggerAdapter
{
    /** @var callable(array):void */
    private $appendFn;
    /** @var callable(int):array */
    private $readLatestFn;
    /** @var callable():array */
    private $summarizeAllFn;

    public function __construct(callable $appendFn, callable $readLatestFn, callable $summarizeAllFn)
    {
        $this->appendFn = $appendFn;
        $this->readLatestFn = $readLatestFn;
        $this->summarizeAllFn = $summarizeAllFn;
    }

    public function append(array $record): void
    {
        ($this->appendFn)($record);
    }

    public function readLatest(int $limit): array
    {
        return ($this->readLatestFn)($limit);
    }

    public function summarizeAll(): array
    {
        return ($this->summarizeAllFn)();
    }
}
