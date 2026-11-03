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

// Graph drawing classes are not in the unit test autoloader paths.
require_once __DIR__.'/../../../../../include/classes/graphdraw/CGraphDraw.php';
require_once __DIR__.'/../../../../../include/classes/graphdraw/CLineGraphDraw.php';

class CLineGraphDrawTest extends TestCase {

	public static function dataMakeComparisonItem(): array {
		return [
			[DRAWTYPE_LINE, CALC_FNC_AVG, DRAWTYPE_DASHED_LINE, CALC_FNC_AVG],
			[DRAWTYPE_BOLD_LINE, CALC_FNC_MAX, DRAWTYPE_DASHED_LINE, CALC_FNC_MAX],
			[DRAWTYPE_GRADIENT_LINE, CALC_FNC_MIN, DRAWTYPE_DASHED_LINE, CALC_FNC_MIN],
			[DRAWTYPE_FILLED_REGION, CALC_FNC_AVG, DRAWTYPE_DASHED_LINE, CALC_FNC_AVG],
			[DRAWTYPE_DASHED_LINE, CALC_FNC_AVG, DRAWTYPE_DASHED_LINE, CALC_FNC_AVG],
			[DRAWTYPE_DOT, CALC_FNC_AVG, DRAWTYPE_DOT, CALC_FNC_AVG],
			[DRAWTYPE_BOLD_DOT, CALC_FNC_AVG, DRAWTYPE_BOLD_DOT, CALC_FNC_AVG],
			// The min/max band of "all" is not drawn for comparison copies.
			[DRAWTYPE_LINE, CALC_FNC_ALL, DRAWTYPE_DASHED_LINE, CALC_FNC_AVG]
		];
	}

	/**
	 * @dataProvider dataMakeComparisonItem
	 */
	public function testMakeComparisonItem(int $drawtype, int $calc_fnc, int $expected_drawtype,
			int $expected_calc_fnc): void {
		$graph_item = [
			'itemid' => '10001',
			'color' => '1A7C11',
			'drawtype' => $drawtype,
			'calc_fnc' => $calc_fnc,
			'yaxisside' => GRAPH_YAXIS_SIDE_LEFT
		];

		$this->assertSame([
			'itemid' => '10001',
			'color' => '1A7C11',
			'drawtype' => $expected_drawtype,
			'calc_fnc' => $expected_calc_fnc,
			'yaxisside' => GRAPH_YAXIS_SIDE_LEFT,
			'comparison' => true,
			'time_shift' => -2678400
		], CLineGraphDraw::makeComparisonItem($graph_item, -2678400));
	}
}
