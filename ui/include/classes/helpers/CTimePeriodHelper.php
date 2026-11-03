<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CTimePeriodHelper {

	/**
	 * Get minimal supported time period.
	 *
	 * @return int
	 */
	public static function getMinPeriod(): int {
		return ZBX_MIN_PERIOD;
	}

	/**
	 * Get maximal supported time period.
	 *
	 * @param DateTimeZone|null $timezone
	 *
	 * @return int
	 */
	public static function getMaxPeriod(?DateTimeZone $timezone = null): int {
		static $max_period;

		if ($max_period === null) {
			$range_time_parser = new CRangeTimeParser();
			$range_time_parser->parse('now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD));
			$max_period = time() - $range_time_parser->getDateTime(true, $timezone)->getTimestamp();
		}

		return $max_period;
	}

	/**
	 * Increment the time period.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 */
	public static function increment(array &$time_period): void {
		$period = $time_period['to_ts'] - $time_period['from_ts'] + 1;
		$offset = min($period, time() - $time_period['to_ts']);

		$time_period['from_ts'] += $offset;
		$time_period['to_ts'] += $offset;

		$time_period['from'] = date(ZBX_FULL_DATE_TIME, $time_period['from_ts']);
		$time_period['to'] = date(ZBX_FULL_DATE_TIME, $time_period['to_ts']);
	}

	/**
	 * Decrement the time period.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 */
	public static function decrement(array &$time_period): void {
		$period = $time_period['to_ts'] - $time_period['from_ts'] + 1;
		$offset = min($period, $time_period['from_ts']);

		$time_period['from_ts'] -= $offset;
		$time_period['to_ts'] -= $offset;

		$time_period['from'] = date(ZBX_FULL_DATE_TIME, $time_period['from_ts']);
		$time_period['to'] = date(ZBX_FULL_DATE_TIME, $time_period['to_ts']);
	}

	/**
	 * Zoom out the time period.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 */
	public static function zoomOut(array &$time_period): void {
		$period = $time_period['to_ts'] - $time_period['from_ts'] + 1;

		$to_offset = min((int) ($period / 2), time() - $time_period['to_ts']);
		$from_offset = min($period - $to_offset, $time_period['from_ts']);

		$time_period['from_ts'] -= $from_offset;
		$time_period['to_ts'] += $to_offset;

		$max_period = self::getMaxPeriod();

		if ($time_period['to_ts'] - $time_period['from_ts'] + 1 > $max_period) {
			$time_period['from_ts'] = $time_period['to_ts'] - $max_period;
		}

		$time_period['from'] = date(ZBX_FULL_DATE_TIME, $time_period['from_ts']);
		$time_period['to'] = date(ZBX_FULL_DATE_TIME, $time_period['to_ts']);
	}

	/**
	 * Apply correct formatting to the time period after modifications made by user.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 */
	public static function rangeChange(array &$time_period): void {
		$absolute_time_parser = new CAbsoluteTimeParser();

		foreach (['from' => 'from_ts', 'to' => 'to_ts'] as $field => $field_ts) {
			if ($absolute_time_parser->parse($time_period[$field]) === CParser::PARSE_SUCCESS) {
				$time_period[$field] = date(ZBX_FULL_DATE_TIME, $time_period[$field_ts]);
			}
		}
	}

	/**
	 * Apply relative offsets to the time period.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 *
	 * @param int    $from_offset             Relative offset in seconds from the start date time.
	 * @param int    $to_offset               Relative offset in seconds from the end date time.
	 */
	public static function rangeOffset(array &$time_period, int $from_offset, int $to_offset): void {
		$time_period['from_ts'] += $from_offset;
		$time_period['to_ts'] -= $to_offset;

		$time_period['from'] = date(ZBX_FULL_DATE_TIME, $time_period['from_ts']);
		$time_period['to'] = date(ZBX_FULL_DATE_TIME, $time_period['to_ts']);
	}

	/**
	 * Get the "Compare with" options for graph widgets.
	 *
	 * @return array  Labels indexed by GRAPH_COMPARE_* constants.
	 */
	public static function getComparisonModes(): array {
		return [
			GRAPH_COMPARE_NONE => _('None'),
			GRAPH_COMPARE_DAY => _('1 day earlier'),
			GRAPH_COMPARE_WEEK => _('1 week earlier'),
			GRAPH_COMPARE_MONTH => _('1 month earlier'),
			GRAPH_COMPARE_YEAR => _('1 year earlier'),
			GRAPH_COMPARE_CUSTOM_SHIFT => _('Custom shift'),
			GRAPH_COMPARE_CUSTOM_DATE => _('Custom start date')
		];
	}

	/**
	 * Parse a time shift like "-76d" or "-1M" into a count and a unit.
	 *
	 * Uses the same syntax as other time units in Zabbix (optional leading "-", no "+"), but restricted to whole
	 * seconds and larger units, because a comparison shift below one second is meaningless. The count is limited to
	 * 10 digits, so the value always fits into an integer; the caller checks the actual range.
	 *
	 * @param string $value
	 *
	 * @return array|null  ['count' => int, 'unit' => string] or null if the value is not a non-zero time shift.
	 */
	public static function parseTimeShift(string $value): ?array {
		if (!preg_match('/^(?<count>-?\d{1,10})(?<unit>[smhdwMy]?)$/', $value, $matches)) {
			return null;
		}

		$count = (int) $matches['count'];

		if ($count == 0) {
			return null;
		}

		return ['count' => $count, 'unit' => $matches['unit'] === '' ? 's' : $matches['unit']];
	}

	/**
	 * Shift a timestamp by the given number of time units.
	 *
	 * Seconds, minutes and hours are shifted by elapsed time. Days and weeks keep the local wall-clock time, so that
	 * "1 day earlier" of 00:00 is 00:00 also across a DST change. Months and years use calendar arithmetic and clamp
	 * the day of month to the length of the target month (March 31 minus one month is February 28 or 29), which
	 * matches how Zabbix server shifts time periods (zbx_tm_sub). DateTime::modify('-1 month') is deliberately not
	 * used, because it overflows into the next month instead (March 31 minus one month would be March 3).
	 *
	 * @param int               $timestamp
	 * @param int               $count     Number of units; negative values shift into the past.
	 * @param string            $unit      One of s, m, h, d, w, M, y.
	 * @param DateTimeZone|null $timezone  Timezone for calendar arithmetic; the current user timezone by default.
	 *
	 * @return int
	 */
	public static function shiftTimestamp(int $timestamp, int $count, string $unit,
			?DateTimeZone $timezone = null): int {
		if (!in_array($unit, ['d', 'w', 'M', 'y'], true)) {
			return $timestamp + $count * ZBX_TIME_SUFFIX_MULTIPLIERS[$unit];
		}

		$datetime = (new DateTimeImmutable('@'.$timestamp))
			->setTimezone($timezone ?? new DateTimeZone(date_default_timezone_get()));

		$year = (int) $datetime->format('Y');
		$month = (int) $datetime->format('n');
		$day = (int) $datetime->format('j');

		// Days overflowing the month are normalized by setDate(); the time of day is kept.
		if ($unit === 'd' || $unit === 'w') {
			$days = ($unit === 'w') ? $count * 7 : $count;

			return $datetime->setDate($year, $month, $day + $days)->getTimestamp();
		}

		// Count in absolute months, so that crossing a year boundary needs no special handling.
		$months = $year * 12 + ($month - 1) + ($unit === 'y' ? $count * 12 : $count);
		$target_year = intdiv($months, 12);
		$target_month = $months % 12 + 1;

		$last_day = (int) $datetime->setDate($target_year, $target_month, 1)->format('t');

		return $datetime->setDate($target_year, $target_month, min($day, $last_day))->getTimestamp();
	}

	/**
	 * Get the comparison period for a graph time period.
	 *
	 * The comparison period starts at the shifted start of the main period and is never longer than the main
	 * period, because both are drawn on the x-axis of the main period. For calendar shifts it ends at the shifted
	 * end of the main period, if that is earlier: "1 month earlier" of March 1-31 is February 1-28 (ends early), and
	 * of November 1-30 it is October 1-30 (October 31 is not shown). If clamping to the month end collapses the
	 * period, the main period length is used.
	 *
	 * @param int               $from_ts   Start of the main period.
	 * @param int               $to_ts     End of the main period.
	 * @param int               $mode      One of GRAPH_COMPARE_* constants.
	 * @param string            $value     Time shift for GRAPH_COMPARE_CUSTOM_SHIFT, absolute or relative time for
	 *                                     GRAPH_COMPARE_CUSTOM_DATE.
	 * @param DateTimeZone|null $timezone  Timezone for calendar arithmetic; the current user timezone by default.
	 *
	 * @return array|null  Null, if there is nothing to compare with (mode "None", invalid value, zero shift,
	 *                     comparison before 1970 or starting in the future). Otherwise:
	 *         int    ['from_ts']  Start of the comparison period.
	 *         int    ['to_ts']    End of the comparison period.
	 *         int    ['shift']    Difference between the comparison and the main period start, in seconds.
	 *         string ['label']    Absolute comparison period for legends, e.g. "2026-10-01 00:00 – 23:59".
	 */
	public static function getComparisonPeriod(int $from_ts, int $to_ts, int $mode, string $value = '',
			?DateTimeZone $timezone = null): ?array {
		$period = $to_ts - $from_ts;

		switch ($mode) {
			case GRAPH_COMPARE_DAY:
				$time_shift = ['count' => -1, 'unit' => 'd'];
				break;

			case GRAPH_COMPARE_WEEK:
				$time_shift = ['count' => -1, 'unit' => 'w'];
				break;

			case GRAPH_COMPARE_MONTH:
				$time_shift = ['count' => -1, 'unit' => 'M'];
				break;

			case GRAPH_COMPARE_YEAR:
				$time_shift = ['count' => -1, 'unit' => 'y'];
				break;

			case GRAPH_COMPARE_CUSTOM_SHIFT:
				$time_shift = self::parseTimeShift($value);

				if ($time_shift === null) {
					return null;
				}

				$seconds = $time_shift['count'] * ZBX_TIME_SUFFIX_MULTIPLIERS[$time_shift['unit']];

				if ($seconds < ZBX_MIN_TIMESHIFT || $seconds > ZBX_MAX_TIMESHIFT) {
					return null;
				}
				break;

			case GRAPH_COMPARE_CUSTOM_DATE:
				$time_shift = null;
				break;

			default:
				return null;
		}

		if ($time_shift !== null) {
			$comparison_from_ts = self::shiftTimestamp($from_ts, $time_shift['count'], $time_shift['unit'],
				$timezone
			);
			$comparison_to_ts = self::shiftTimestamp($to_ts, $time_shift['count'], $time_shift['unit'], $timezone);

			$comparison_period = $comparison_to_ts > $comparison_from_ts
				? min($period, $comparison_to_ts - $comparison_from_ts)
				: $period;
		}
		else {
			$range_time_parser = new CRangeTimeParser();

			if ($value === '' || $range_time_parser->parse($value) != CParser::PARSE_SUCCESS) {
				return null;
			}

			$comparison_from_ts = $range_time_parser->getDateTime(true, $timezone)->getTimestamp();
			$comparison_period = $period;
		}

		if ($comparison_from_ts == $from_ts || $comparison_from_ts < 0 || $comparison_from_ts > time()) {
			return null;
		}

		$comparison_to_ts = $comparison_from_ts + $comparison_period;

		return [
			'from_ts' => $comparison_from_ts,
			'to_ts' => $comparison_to_ts,
			'shift' => $comparison_from_ts - $from_ts,
			'label' => self::getComparisonLabel($comparison_from_ts, $comparison_to_ts, $timezone)
		];
	}

	/**
	 * Validate the value of a "Compare with" mode.
	 *
	 * @param int    $mode   One of GRAPH_COMPARE_* constants.
	 * @param string $value  Time shift or start date, depending on the mode.
	 * @param string $name   Field name used in the error message.
	 *
	 * @return string|null  Error message or null if the value is valid.
	 */
	public static function validateComparisonValue(int $mode, string $value, string $name): ?string {
		if ($mode == GRAPH_COMPARE_CUSTOM_SHIFT) {
			$rule = [
				'type' => API_TIME_UNIT,
				'flags' => API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR,
				'in' => implode(':', [ZBX_MIN_TIMESHIFT, ZBX_MAX_TIMESHIFT])
			];

			if (!CApiInputValidator::validate($rule, $value, $name, $error)) {
				return $error;
			}

			// The API rule also accepts zero and sub-second units, which cannot be used as a comparison shift.
			if (self::parseTimeShift($value) === null) {
				return _s('Invalid parameter "%1$s": %2$s.', $name, _('a time unit is expected'));
			}
		}
		elseif ($mode == GRAPH_COMPARE_CUSTOM_DATE) {
			$range_time_parser = new CRangeTimeParser();

			if ($value === '' || $range_time_parser->parse($value) != CParser::PARSE_SUCCESS) {
				return _s('Invalid parameter "%1$s": %2$s.', $name, _('a time is expected'));
			}
		}

		return null;
	}

	/**
	 * Format a comparison period for legends: "Y-m-d H:i – H:i" if it starts and ends on the same day, otherwise
	 * "Y-m-d H:i – Y-m-d H:i".
	 *
	 * @param int               $from_ts
	 * @param int               $to_ts
	 * @param DateTimeZone|null $timezone
	 *
	 * @return string
	 */
	public static function getComparisonLabel(int $from_ts, int $to_ts, ?DateTimeZone $timezone = null): string {
		$timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());

		$from = (new DateTimeImmutable('@'.$from_ts))->setTimezone($timezone);
		$to = (new DateTimeImmutable('@'.$to_ts))->setTimezone($timezone);

		$to_format = $from->format('Y-m-d') === $to->format('Y-m-d') ? 'H:i' : 'Y-m-d H:i';

		return $from->format('Y-m-d H:i').' – '.$to->format($to_format);
	}
}
