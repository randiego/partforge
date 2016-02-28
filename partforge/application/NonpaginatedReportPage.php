<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2016 Randall C. Black <randy@blacksdesign.com>
 *
 * This file is part of PartForge
 *
 * PartForge is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * PartForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PartForge.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @license GPL-3.0+ <http://spdx.org/licenses/GPL-3.0+>
 */

class NonpaginatedReportPage extends PaginatedReportPage {

	protected function rows_select_tag_if_enough_rows($numrows, $rows_per_page) {
		return '';
	}
	
	protected function build_limit_and_pagination_params($current_pageno, $rows_per_page, $numrows, &$top_record_number, &$limit, &$paginationline_html, &$right_pagination_url, $navigator) {
		$lastpage = ceil($numrows/$rows_per_page);
		$pageno = make_unknown_a_number_between($current_pageno, 1, $lastpage);
		$top_record_number = ($pageno - 1) * $rows_per_page;
	
		$limit = 'LIMIT ' .$top_record_number.',' .$rows_per_page;
			
		$paginationline_html = '';
		$right_pagination_url = '';
	}
	
}


?>
