<?php

namespace Spatie\CalendarLinks\Tests;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Spatie\CalendarLinks\Link;
use Spatie\Snapshots\MatchesSnapshots;

abstract class TestCase extends BaseTestCase
{
    use MatchesSnapshots;

    protected function createShortEventLink(bool $immutable = false): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog';

        /** @var \DateTimeInterface $dateTimeClass */
        $dateTimeClass = $immutable ? DateTimeImmutable::class : DateTime::class;

        return Link::create(
            'Birthday',
            $dateTimeClass::createFromFormat('Y-m-d H:i', '2018-02-01 09:00', new DateTimeZone('UTC')),
            $dateTimeClass::createFromFormat('Y-m-d H:i', '2018-02-01 18:00', new DateTimeZone('UTC'))
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createMultipleDaysEventLink(bool $immutable = false): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog';

        /** @var \DateTimeInterface $dateTimeClass */
        $dateTimeClass = $immutable ? DateTimeImmutable::class : DateTime::class;

        return Link::create(
            'New Year',
            $dateTimeClass::createFromFormat('Y-m-d H:i', '2019-12-31 23:00', new DateTimeZone('UTC')),
            $dateTimeClass::createFromFormat('Y-m-d H:i', '2020-01-01 1:00', new DateTimeZone('UTC'))
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createSingleDayAllDayEventLink(bool $immutable = false): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog';

        /** @var \DateTimeInterface $dateTimeClass */
        $dateTimeClass = $immutable ? DateTimeImmutable::class : DateTime::class;

        return Link::createAllDay(
            'Birthday',
            $dateTimeClass::createFromFormat('Y-m-d', '2018-02-01', new DateTimeZone('UTC'))
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createMultipleDaysAllDayEventLink(bool $immutable = false): Link
    {
        $description = 'With balloons, clowns and stuff
Bring a dog, bring a frog';

        /** @var \DateTimeInterface $dateTimeClass */
        $dateTimeClass = $immutable ? DateTimeImmutable::class : DateTime::class;

        return Link::createAllDay(
            'Birthday',
            $dateTimeClass::createFromFormat('Y-m-d', '2018-02-01', new DateTimeZone('UTC')),
            5
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createTimezonedAllDayLink(): Link
    {
        $description = <<<'EOF'
With balloons, clowns and stuff
Bring a dog, bring a frog
EOF;

        return Link::createAllDay(
            'Birthday in Paris',
            DateTime::createFromFormat('Y-m-d', '2018-02-01', new DateTimeZone('Europe/Paris'))
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }

    protected function createTimezonedLink(): Link
    {
        $description = <<<'EOF'
With balloons, clowns and stuff
Bring a dog, bring a frog
EOF;

        return Link::create(
            'Flight from Paris to Tokyo',
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00', new DateTimeZone('Europe/Paris')),
            DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00', new DateTimeZone('Asia/Tokyo'))
        )->description($description)->address('Party Lane 1A, 1337 Funtown');
    }
}
