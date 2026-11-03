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


use PHPUnit\Framework\TestCase;

/**
 * Tests of the calendar arithmetic used by the "Compare with" option of graph widgets.
 *
 * Dates are given as local wall-clock strings and converted with the test timezone, so no epoch values are
 * computed by hand. Europe/Berlin cases cover DST (in 2026: summer time from March 29 to October 25), Asia/Jakarta
 * cases cover a timezone without DST.
 */
class CTimePeriodHelperTest extends TestCase {

	private string $default_timezone;

	protected function setUp(): void {
		$this->default_timezone = date_default_timezone_get();
	}

	protected function tearDown(): void {
		date_default_timezone_set($this->default_timezone);
	}

	private static function ts(string $datetime, string $timezone): int {
		return (new DateTime($datetime, new DateTimeZone($timezone)))->getTimestamp();
	}

	public static function dataParseTimeShift(): array {
		return [
			['-1M', ['count' => -1, 'unit' => 'M']],
			['-76d', ['count' => -76, 'unit' => 'd']],
			['2w', ['count' => 2, 'unit' => 'w']],
			['-3600', ['count' => -3600, 'unit' => 's']],
			['-1y', ['count' => -1, 'unit' => 'y']],
			['0', null],
			['-0d', null],
			['', null],
			['+1d', null],
			['1.5d', null],
			['5ms', null],
			['{$SHIFT}', null],
			['-12345678901d', null],
			['d', null]
		];
	}

	/**
	 * @dataProvider dataParseTimeShift
	 */
	public function testParseTimeShift(string $value, ?array $expected): void {
		$this->assertSame($expected, CTimePeriodHelper::parseTimeShift($value));
	}

	public static function dataShiftTimestamp(): array {
		return [
			// The original example: November 1 compared with October 1.
			['Asia/Jakarta', '2026-11-01 00:00:00', -1, 'M', '2026-10-01 00:00:00'],
			// Clamping to the end of a shorter month.
			['Asia/Jakarta', '2026-03-31 10:00:00', -1, 'M', '2026-02-28 10:00:00'],
			['Asia/Jakarta', '2024-03-31 10:00:00', -1, 'M', '2024-02-29 10:00:00'],
			['Asia/Jakarta', '2024-02-29 00:00:00', -1, 'y', '2023-02-28 00:00:00'],
			['Asia/Jakarta', '2026-01-31 00:00:00', 1, 'M', '2026-02-28 00:00:00'],
			// Crossing year boundaries.
			['Asia/Jakarta', '2026-01-31 12:00:00', -1, 'M', '2025-12-31 12:00:00'],
			['Asia/Jakarta', '2026-03-31 00:00:00', -13, 'M', '2025-02-28 00:00:00'],
			['Asia/Jakarta', '2026-11-01 00:00:00', -76, 'd', '2026-08-17 00:00:00'],
			['Asia/Jakarta', '2026-11-01 00:00:00', -1, 'w', '2026-10-25 00:00:00'],
			// Days, weeks, months and years keep the wall-clock time across DST changes.
			['Europe/Berlin', '2026-11-01 00:00:00', -1, 'M', '2026-10-01 00:00:00'],
			['Europe/Berlin', '2026-03-30 00:00:00', -1, 'd', '2026-03-29 00:00:00'],
			['Europe/Berlin', '2026-10-26 00:00:00', -1, 'w', '2026-10-19 00:00:00'],
			// Hours are elapsed time: 04:00 CEST minus 3 hours is 00:00 CET (02:00-03:00 does not exist).
			['Europe/Berlin', '2026-03-29 04:00:00', -3, 'h', '2026-03-29 00:00:00'],
			['Asia/Jakarta', '2026-11-01 00:00:00', -90, 'm', '2026-10-31 22:30:00'],
			['Asia/Jakarta', '2026-11-01 00:00:00', -30, 's', '2026-10-31 23:59:30']
		];
	}

	/**
	 * @dataProvider dataShiftTimestamp
	 */
	public function testShiftTimestamp(string $timezone, string $datetime, int $count, string $unit,
			string $expected): void {
		$this->assertSame(self::ts($expected, $timezone),
			CTimePeriodHelper::shiftTimestamp(self::ts($datetime, $timezone), $count, $unit,
				new DateTimeZone($timezone)
			)
		);
	}

	/**
	 * A local time inside the spring DST gap does not exist; PHP moves it forward by the gap length.
	 */
	public function testShiftTimestampIntoDstGap(): void {
		$timezone = new DateTimeZone('Europe/Berlin');

		$this->assertSame(self::ts('2026-03-29 03:30:00', 'Europe/Berlin'),
			CTimePeriodHelper::shiftTimestamp(self::ts('2026-04-29 02:30:00', 'Europe/Berlin'), -1, 'M', $timezone)
		);
	}

	public function testShiftTimestampUsesUserTimezoneByDefault(): void {
		date_default_timezone_set('Asia/Jakarta');

		$this->assertSame(self::ts('2026-10-01 00:00:00', 'Asia/Jakarta'),
			CTimePeriodHelper::shiftTimestamp(self::ts('2026-11-01 00:00:00', 'Asia/Jakarta'), -1, 'M')
		);
	}

	public static function dataGetComparisonPeriod(): array {
		return [
			'1 day period, 1 month earlier' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_MONTH, '',
				['2026-10-01 00:00:00', '2026-10-01 23:59:59', '2026-10-01 00:00 – 23:59']
			],
			'1 day period, 1 day earlier' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_DAY, '',
				['2026-10-31 00:00:00', '2026-10-31 23:59:59', '2026-10-31 00:00 – 23:59']
			],
			'1 day period, 1 week earlier' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_WEEK, '',
				['2026-10-25 00:00:00', '2026-10-25 23:59:59', '2026-10-25 00:00 – 23:59']
			],
			'1 day period, 1 year earlier' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_YEAR, '',
				['2025-11-01 00:00:00', '2025-11-01 23:59:59', '2025-11-01 00:00 – 23:59']
			],
			'shorter comparison month ends early' => [
				'Asia/Jakarta', '2026-03-01 00:00:00', '2026-03-31 23:59:59', GRAPH_COMPARE_MONTH, '',
				['2026-02-01 00:00:00', '2026-02-28 23:59:59', '2026-02-01 00:00 – 2026-02-28 23:59']
			],
			'longer comparison month is clipped to the main period length' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-30 23:59:59', GRAPH_COMPARE_MONTH, '',
				['2026-10-01 00:00:00', '2026-10-30 23:59:59', '2026-10-01 00:00 – 2026-10-30 23:59']
			],
			'days clamped to the same month end give a one day comparison' => [
				'Asia/Jakarta', '2026-03-29 00:00:00', '2026-03-31 23:59:59', GRAPH_COMPARE_MONTH, '',
				['2026-02-28 00:00:00', '2026-02-28 23:59:59', '2026-02-28 00:00 – 23:59']
			],
			'collapsed comparison falls back to the main period length' => [
				'Asia/Jakarta', '2026-03-30 23:30:00', '2026-03-31 00:30:00', GRAPH_COMPARE_MONTH, '',
				['2026-02-28 23:30:00', '2026-03-01 00:30:00', '2026-02-28 23:30 – 2026-03-01 00:30']
			],
			'custom shift' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_CUSTOM_SHIFT, '-76d',
				['2026-08-17 00:00:00', '2026-08-17 23:59:59', '2026-08-17 00:00 – 23:59']
			],
			'custom absolute start date' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_CUSTOM_DATE,
				'2026-08-17 00:00:00',
				['2026-08-17 00:00:00', '2026-08-17 23:59:59', '2026-08-17 00:00 – 23:59']
			],
			'DST: 1 month earlier keeps local midnight' => [
				'Europe/Berlin', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_MONTH, '',
				['2026-10-01 00:00:00', '2026-10-01 23:59:59', '2026-10-01 00:00 – 23:59']
			],
			'none' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_NONE, '', null
			],
			'unknown mode' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', 99, '', null
			],
			'invalid custom shift' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_CUSTOM_SHIFT, 'abc', null
			],
			'custom shift out of range' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_CUSTOM_SHIFT, '-100y', null
			],
			'invalid custom date' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_CUSTOM_DATE, 'abc', null
			],
			'custom date equal to the main period start' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_CUSTOM_DATE,
				'2026-11-01 00:00:00', null
			],
			'comparison before 1970' => [
				'UTC', '1970-01-15 00:00:00', '1970-01-15 23:59:59', GRAPH_COMPARE_MONTH, '', null
			],
			'comparison starting in the future' => [
				'Asia/Jakarta', '2026-11-01 00:00:00', '2026-11-01 23:59:59', GRAPH_COMPARE_CUSTOM_SHIFT, '100y', null
			]
		];
	}

	/**
	 * @dataProvider dataGetComparisonPeriod
	 */
	public function testGetComparisonPeriod(string $timezone, string $from, string $to, int $mode, string $value,
			?array $expected): void {
		date_default_timezone_set($timezone);

		$from_ts = self::ts($from, $timezone);
		$result = CTimePeriodHelper::getComparisonPeriod($from_ts, self::ts($to, $timezone), $mode, $value);

		if ($expected === null) {
			$this->assertNull($result);

			return;
		}

		[$expected_from, $expected_to, $expected_label] = $expected;

		$this->assertSame([
			'from_ts' => self::ts($expected_from, $timezone),
			'to_ts' => self::ts($expected_to, $timezone),
			'shift' => self::ts($expected_from, $timezone) - $from_ts,
			'label' => $expected_label
		], $result);
	}

	public function testGetComparisonPeriodRelativeCustomDate(): void {
		date_default_timezone_set('Asia/Jakarta');

		$from_ts = self::ts('today', 'Asia/Jakarta');
		$result = CTimePeriodHelper::getComparisonPeriod($from_ts, $from_ts + SEC_PER_DAY - 1,
			GRAPH_COMPARE_CUSTOM_DATE, 'now-7d/d'
		);

		$this->assertSame(self::ts('today -7 days', 'Asia/Jakarta'), $result['from_ts']);
		$this->assertSame($result['from_ts'] + SEC_PER_DAY - 1, $result['to_ts']);
	}

	public static function dataValidateComparisonValue(): array {
		return [
			[GRAPH_COMPARE_NONE, '', true],
			[GRAPH_COMPARE_MONTH, '', true],
			[GRAPH_COMPARE_CUSTOM_SHIFT, '-1M', true],
			[GRAPH_COMPARE_CUSTOM_SHIFT, '-76d', true],
			[GRAPH_COMPARE_CUSTOM_SHIFT, '', false],
			[GRAPH_COMPARE_CUSTOM_SHIFT, 'abc', false],
			[GRAPH_COMPARE_CUSTOM_SHIFT, '5.2', false],
			[GRAPH_COMPARE_CUSTOM_SHIFT, '0', false],
			[GRAPH_COMPARE_CUSTOM_SHIFT, '-5ms', false],
			[GRAPH_COMPARE_CUSTOM_SHIFT, '-100y', false],
			[GRAPH_COMPARE_CUSTOM_DATE, '2026-08-17 00:00:00', true],
			[GRAPH_COMPARE_CUSTOM_DATE, 'now-1M/d', true],
			[GRAPH_COMPARE_CUSTOM_DATE, '', false],
			[GRAPH_COMPARE_CUSTOM_DATE, 'abc', false]
		];
	}

	/**
	 * @dataProvider dataValidateComparisonValue
	 */
	public function testValidateComparisonValue(int $mode, string $value, bool $valid): void {
		$error = CTimePeriodHelper::validateComparisonValue($mode, $value, 'Compare with');

		if ($valid) {
			$this->assertNull($error);
		}
		else {
			$this->assertIsString($error);
		}
	}
}
