<?php

namespace App\Support;

use App\Enums\PeriodMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;

/**
 * The days the Work Entries week page shows, from one date to another: a
 * week from Monday to Sunday, a calendar month, or a custom period. It is
 * kept in the page's URL as week=YYYY-MM-DD (any day of the week),
 * month=YYYY-MM, or from=YYYY-MM-DD&to=YYYY-MM-DD.
 */
final readonly class Period
{
    /**
     * A custom period is at most this many months long.
     */
    public const int LONGEST_CUSTOM_MONTHS = 3;

    private function __construct(
        public PeriodMode $mode,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /**
     * The week, Monday to Sunday, holding a day.
     */
    public static function week(DateTimeInterface $day): self
    {
        $monday = CarbonImmutable::instance($day)->startOfWeek(CarbonInterface::MONDAY);

        return new self(PeriodMode::Week, $monday, $monday->endOfWeek(CarbonInterface::SUNDAY)->startOfDay());
    }

    /**
     * A calendar month, given as YYYY-MM.
     */
    public static function month(string $month): self
    {
        $first = CarbonImmutable::createFromFormat('!Y-m', $month) ?: throw new InvalidFormatException("Not a month: {$month}");

        return new self(PeriodMode::Month, $first, $first->endOfMonth()->startOfDay());
    }

    /**
     * The days from one date to another, both included.
     */
    public static function custom(DateTimeInterface $from, DateTimeInterface $to): self
    {
        return new self(PeriodMode::Custom, CarbonImmutable::instance($from)->startOfDay(), CarbonImmutable::instance($to)->startOfDay());
    }

    /**
     * The period a URL's query asks for: a custom period over a month over a
     * week. Null when it asks for none, or for one that is malformed, or a
     * custom period that ends before it starts or is too long.
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

    /**
     * The period a URL's query asks for while it holds a day, or else the
     * week holding the day.
     *
     * @param  array<array-key, mixed>  $query
     */
    public static function holding(array $query, DateTimeInterface $day): self
    {
        $period = self::fromQuery($query);

        return $period !== null && $period->contains($day) ? $period : self::week($day);
    }

    /**
     * Whether a custom period from one date to another is short enough.
     */
    public static function fitsCustom(DateTimeInterface $from, DateTimeInterface $to): bool
    {
        return CarbonImmutable::instance($to)->lessThan(CarbonImmutable::instance($from)->addMonthsNoOverflow(self::LONGEST_CUSTOM_MONTHS));
    }

    private static function validCustom(CarbonImmutable $from, CarbonImmutable $to): ?self
    {
        return ! $to->isBefore($from) && self::fitsCustom($from, $to) ? self::custom($from, $to) : null;
    }

    /**
     * Whether a day falls within the period.
     */
    public function contains(DateTimeInterface $day): bool
    {
        $date = CarbonImmutable::instance($day)->startOfDay();

        return ! $date->isBefore($this->from) && ! $date->isAfter($this->to);
    }

    /**
     * Every day of the period, first to last.
     *
     * @return list<CarbonImmutable>
     */
    public function days(): array
    {
        $days = [];

        for ($day = $this->from; ! $day->isAfter($this->to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * The URL query asking for this period.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return match ($this->mode) {
            PeriodMode::Week => ['week' => $this->from->toDateString()],
            PeriodMode::Month => ['month' => $this->from->format('Y-m')],
            PeriodMode::Custom => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
        };
    }

    /**
     * The period as the page gets it, with the URL query asking for it.
     *
     * @return array{mode: string, from: string, to: string, query: array<string, string>}
     */
    public function toArray(): array
    {
        return ['mode' => $this->mode->value, 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'query' => $this->query()];
    }

    private static function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date) ?: throw new InvalidFormatException("Not a date: {$date}");
    }
}
