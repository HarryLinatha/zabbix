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


namespace Widgets\Graph\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldDatePicker,
	CWidgetFieldMultiSelectGraph,
	CWidgetFieldMultiSelectItem,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSelect,
	CWidgetFieldTextBox,
	CWidgetFieldTimePeriod
};

use CTimePeriodHelper,
	CWidgetsData;

/**
 * Graph (classic) widget form.
 */
class WidgetForm extends CWidgetForm {

	private int $compare_with = GRAPH_COMPARE_NONE;

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		if ($this->getFieldValue('source_type') == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH
				&& !$this->getFieldValue('itemid')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Item'), _('cannot be empty'));
		}

		if ($this->getFieldValue('source_type') == ZBX_WIDGET_FIELD_RESOURCE_GRAPH
				&& !$this->getFieldValue('graphid')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Graph'), _('cannot be empty'));
		}

		// "Compare with": the custom shift is validated here; an invalid custom date is reported by its own field.
		if ($this->compare_with == GRAPH_COMPARE_CUSTOM_SHIFT
				|| ($this->compare_with == GRAPH_COMPARE_CUSTOM_DATE && $this->getFieldValue('compare_date') === '')) {
			$value = $this->compare_with == GRAPH_COMPARE_CUSTOM_SHIFT
				? $this->getFieldValue('compare_shift')
				: $this->getFieldValue('compare_date');

			$error = CTimePeriodHelper::validateComparisonValue($this->compare_with, $value, _('Compare with'));

			if ($error !== null) {
				$errors[] = $error;
			}
		}

		return $errors;
	}

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		if (array_key_exists('compare_with', $values)) {
			$this->compare_with = (int) $values['compare_with'];
		}

		if ($this->compare_with != GRAPH_COMPARE_CUSTOM_SHIFT) {
			unset($values['compare_shift']);
		}

		if ($this->compare_with != GRAPH_COMPARE_CUSTOM_DATE) {
			unset($values['compare_date']);
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('source_type', _('Source'), [
					ZBX_WIDGET_FIELD_RESOURCE_GRAPH => _('Graph'),
					ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH => _('Simple graph')
				]))
					->setDefault(ZBX_WIDGET_FIELD_RESOURCE_GRAPH)
			)
			->addField(
				(new CWidgetFieldMultiSelectItem('itemid', _('Item')))
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				(new CWidgetFieldMultiSelectGraph('graphid', _('Graph')))
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				(new CWidgetFieldTimePeriod('time_period', _('Time period')))
					->setDefault([
						CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
							CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
						)
					])
					->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldSelect('compare_with', _('Compare with'), CTimePeriodHelper::getComparisonModes()))
					->setDefault(GRAPH_COMPARE_NONE)
			)
			->addField(
				(new CWidgetFieldTextBox('compare_shift', _('Custom shift')))
					->setFlags($this->compare_with != GRAPH_COMPARE_CUSTOM_SHIFT ? CWidgetField::FLAG_DISABLED : 0x00)
					->setMaxLength(255)
			)
			->addField(
				(new CWidgetFieldDatePicker('compare_date', _('Custom start date')))
					->setFlags($this->compare_with != GRAPH_COMPARE_CUSTOM_DATE ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_legend', _('Show legend')))->setDefault(1)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}
}
