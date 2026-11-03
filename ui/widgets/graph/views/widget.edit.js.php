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


?>

window.widget_graph_form = new class {

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	init() {
		this.#form = document.getElementById('widget-dialogue-form');

		document.getElementById('source_type').addEventListener('change', () => this.#updateForm());
		document.getElementById('compare_with').addEventListener('change', () => this.#updateForm());

		this.#updateForm();
	}

	#updateForm() {
		const is_graph = this.#form.querySelector('#source_type input[name="source_type"]:checked').value
			== <?= ZBX_WIDGET_FIELD_RESOURCE_GRAPH ?>;

		this.#form.querySelectorAll('.js-row-graphid').forEach(element => {
			element.style.display = is_graph ? '' : 'none';
		});

		this.#form.querySelectorAll('.js-row-itemid').forEach(element => {
			element.style.display = is_graph ? 'none' : '';
		});

		$('#graphid').multiSelect(is_graph ? 'enable' : 'disable');
		$('#itemid').multiSelect(is_graph ? 'disable' : 'enable');

		// "Compare with": only the input of the selected custom mode is shown and submitted.
		const compare_with = document.getElementById('compare_with').value;
		const is_compare_shift = compare_with == <?= GRAPH_COMPARE_CUSTOM_SHIFT ?>;
		const is_compare_date = compare_with == <?= GRAPH_COMPARE_CUSTOM_DATE ?>;

		this.#form.querySelectorAll('.js-row-compare-shift').forEach(element => {
			element.style.display = is_compare_shift ? '' : 'none';
		});

		this.#form.querySelectorAll('.js-row-compare-date').forEach(element => {
			element.style.display = is_compare_date ? '' : 'none';
		});

		document.getElementById('compare_shift').disabled = !is_compare_shift;

		for (const element of document.getElementById('compare_date').closest('.<?= CDateSelector::ZBX_STYLE_CLASS ?>')
				.querySelectorAll('input, button')) {
			element.disabled = !is_compare_date;
		}
	}
};
