<?php

namespace Spatie\CalendarLinks\Generators;

use Spatie\CalendarLinks\Generator;
use Sabre\VObject\Component;
use Spatie\CalendarLinks\Link;
use Sabre\VObject\TimeZoneUtil;
use Sabre\VObject\Component\VCalendar;

/**
 * @see https://icalendar.org/RFC-Specifications/iCalendar-RFC-5545/
 */
class Ics implements Generator
{
    /** @var string {@see https://www.php.net/manual/en/function.date.php} */
    protected $dateFormat = 'Ymd';
    /** @var string */
    protected $dateTimeFormat = 'Ymd\THis\Z';

    /** @var array<non-empty-string, non-empty-string> */
    protected $options = [];

    /**
     * @param array<non-empty-string, non-empty-string> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /** {@inheritDoc} */
    public function generate(Link $link): string
    {
        $timeZones = [];

        $vcalendar = new VCalendar();
        $vevent = $vcalendar->createComponent('VEVENT', [
            'UID' => $this->generateEventUid($link),
            'SUMMARY' => $link->title,
        ]);

        $dateTimeFormat = $link->allDay ? $this->dateFormat : $this->dateTimeFormat;

        $vevent->add('DTSTAMP', $link->from->format($dateTimeFormat));
        $vevent->add('DTSTART', $link->from->format($dateTimeFormat));
        $timeZones[$link->from->getTimezone()->getName()] = $link->from->getTimezone();

        if ($link->allDay) {
            $vevent->add('DURATION', 'P'.(max(1, $link->from->diff($link->to)->days)).'D');
        } else {
            $vevent->add('DTEND', $link->to->format($dateTimeFormat));
            $timeZones[$link->to->getTimezone()->getName()] = $link->to->getTimezone();
        }

        if ($link->description) {
            $vevent->add('DESCRIPTION', $this->escapeString($link->description));
        }

        if ($link->address) {
            $vevent->add('LOCATION', $this->escapeString($link->address));
        }

        if (isset($this->options['URL'])) {
            $vevent->add('URL;VALUE=URI', $this->options['URL']);
        }

        $this->addVTimezoneComponents($vcalendar, $timeZones, $link->from, $link->to);

        $vcalendar->add($vevent);
        return $this->buildLink($vcalendar->serialize());
    }

    protected function buildLink(array $propertiesAndComponents): string
    {
        return 'data:text/calendar;charset=utf8;base64,'.base64_encode(implode("\r\n", $propertiesAndComponents));
    }

    /** @see https://tools.ietf.org/html/rfc5545.html#section-3.3.11 */
    protected function escapeString(string $field): string
    {
        return addcslashes($field, "\r\n,;");
    }

    /** @see https://tools.ietf.org/html/rfc5545#section-3.8.4.7 */
    protected function generateEventUid(Link $link): string
    {
        return md5(sprintf(
            '%s%s%s%s',
            $link->from->format(\DateTimeInterface::ATOM),
            $link->to->format(\DateTimeInterface::ATOM),
            $link->title,
            $link->address
        ));
    }

    protected function addVTimezoneComponents(VCalendar $vcalendar, array $timeZones, \DateTimeInterface $from, \DateTimeInterface $to)
    {
        foreach ($timeZones as $timeZone) {
            /* @var \DateTimeZone $timeZone */
            if ($timeZone->getName() !== 'UTC') {
                $vcalendar->add(
                    $this->generateVTimeZoneComponent(
                        $vcalendar,
                        $timeZone,
                        $from->getTimestamp(),
                        $to->getTimestamp()
                    )
                );
            }
        }
    }

    /**
     * Returns a VTIMEZONE component for a Olson timezone identifier
     * with daylight transitions covering the given date range.
     *
     * Kinldy inspired from https://gist.github.com/thomascube/47ff7d530244c669825736b10877a200
     * and https://stackoverflow.com/a/25971680
     *
     * @param VCalendar $vcalendar
     * @param \DateTimeZone $timeZone Timezone
     * @param int $from Unix timestamp with first date/time in this timezone
     * @param int $to Unix timestap with last date/time in this timezone
     *
     * @return Component A Sabre\VObject\Component object representing a VTIMEZONE definition
     */
    protected function generateVTimeZoneComponent(VCalendar $vcalendar, \DateTimeZone $timeZone, int $from = 0, int $to = 0)
    {
        if (! $from) {
            $from = time();
        }
        if (! $to) {
            $to = $from;
        }

        // get all transitions for one year back/ahead
        $year = 86400 * 360;
        $transitions = $timeZone->getTransitions($from - $year, $to + $year);

        $vTimeZone = $vcalendar->createComponent('VTIMEZONE');
        $vTimeZone->add('TZID', $timeZone->getName());

        $std = null;
        $dst = null;
        foreach ($transitions as $i => $trans) {
            $component = null;

            if ($i === 0) {
                // remember the offset for the next TZOFFSETFROM value
                $tzfrom = $trans['offset'] / 3600;
                continue;
            }

            // daylight saving time definition
            if ($trans['isdst']) {
                $t_dst = $trans['ts'];
                $dst = $vcalendar->createComponent('DAYLIGHT');
                $component = $dst;
            } // standard time definition
            else {
                $t_std = $trans['ts'];
                $std = $vcalendar->createComponent('STANDARD');
                $component = $std;
            }

            $dt = new \DateTime($trans['time']);
            $offset = $trans['offset'] / 3600;

            $component->add('DTSTART', $dt->format('Ymd\THis'));
            $component->add('TZOFFSETFROM', sprintf('%s%02d%02d', $tzfrom >= 0 ? '+' : '', floor($tzfrom),
                ($tzfrom - floor($tzfrom)) * 60));
            $component->add('TZOFFSETTO', sprintf('%s%02d%02d', $offset >= 0 ? '+' : '', floor($offset),
                ($offset - floor($offset)) * 60));

            // add abbreviated timezone name.
            $component->add('TZNAME', $trans['abbr']);

            $tzfrom = $offset;
            $vTimeZone->add($component);

            // we covered the entire date range
            if ($std && $dst && min($t_std, $t_dst) < $from && max($t_std, $t_dst) > $to) {
                break;
            }
        }

        // add X-MICROSOFT-CDO-TZID if available
        $microsoftExchangeMap = array_flip(TimeZoneUtil::$microsoftExchangeMap);
        if (array_key_exists($timeZone->getName(), $microsoftExchangeMap)) {
            $vTimeZone->add('X-MICROSOFT-CDO-TZID', $microsoftExchangeMap[$timeZone->getName()]);
        }

        return $vTimeZone;
    }
}
