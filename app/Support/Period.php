<?php

namespace App\Support;

use App\Enums\PeriodMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
final readonly class Period implements Arrayable
{
    public const int LONGEST_CUSTOM_MONTHS = 3;

    private function __construct(
        public PeriodMode $mode,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function week(DateTimeInterface $day): self
    {
        $monday = CarbonImmutable::instance($day)->startOfWeek(CarbonInterface::MONDAY);

        return new self(PeriodMode::Week, $monday, $monday->endOfWeek(CarbonInterface::SUNDAY)->startOfDay());
    }

    public static function month(string $month): self
    {
        $first = CarbonImmutable::createFromFormat('!Y-m', $month) ?: throw new InvalidFormatException("Not a month: {$month}");

        return new self(PeriodMode::Month, $first, $first->endOfMonth()->startOfDay());
    }

    public static function custom(DateTimeInterface $from, DateTimeInterface $to): self
    {
        return new self(PeriodMode::Custom, CarbonImmutable::instance($from)->startOfDay(), CarbonImmutable::instance($to)->startOfDay());
    }

    /**
     * A custom period wins over a month, and a month over a week.
     *
     * @param  array<array-key, mixed>  $query
     */
    public static function fromQuery(array $query): ?self
    {
        try {
            return match (true) {
                is_string($query['from'] ?? null) && is_string($query['to'] ?? null) => self::validCustom(self::date($query['from']), self::date($query['to'])),
                is_string($query['month'] ?? null) => self::month($query['month']),
                is_string($query['week'] ?? null) => self::week(self::date($query['week'])),
                default => null,
            };
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /** @param  array<array-key, mixed>  $query */
    public static function holding(array $query, DateTimeInterface $day): self
    {
        $period = self::fromQuery($query);

        return $period !== null && $period->contains($day) ? $period : self::week($day);
    }

    public static function fitsCustom(DateTimeInterface $from, DateTimeInterface $to): bool
    {
        return CarbonImmutable::instance($to)->lessThan(CarbonImmutable::instance($from)->addMonthsNoOverflow(self::LONGEST_CUSTOM_MONTHS));
    }

    private static function validCustom(CarbonImmutable $from, CarbonImmutable $to): ?self
    {
        return ! $to->isBefore($from) && self::fitsCustom($from, $to) ? self::custom($from, $to) : null;
    }

    /** For records that are never in the future. */
    public function notAfterToday(): self
    {
        if ($this->from->isAfter(today())) {
            return $this->mode === PeriodMode::Week ? self::week(today()) : self::month(today()->format('Y-m'));
        }

        if ($this->mode === PeriodMode::Custom && $this->to->isAfter(today())) {
            return self::custom($this->from, today());
        }

        return $this;
    }

    public function contains(DateTimeInterface $day): bool
    {
        $date = CarbonImmutable::instance($day)->startOfDay();

        return ! $date->isBefore($this->from) && ! $date->isAfter($this->to);
    }

    /** @return list<CarbonImmutable> */
    public function days(): array
    {
        $days = [];

        for ($day = $this->from; ! $day->isAfter($this->to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    /** @return array<string, string> */
    public function query(): array
    {
        return match ($this->mode) {
            PeriodMode::Week => ['week' => $this->from->toDateString()],
            PeriodMode::Month => ['month' => $this->from->format('Y-m')],
            PeriodMode::Custom => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
        };
    }

    /** @return array{mode: string, from: string, to: string, query: array<string, string>} */
    public function toArray(): array
    {
        return ['mode' => $this->mode->value, 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'query' => $this->query()];
    }

    private static function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date) ?: throw new InvalidFormatException("Not a date: {$date}");
    }
}
