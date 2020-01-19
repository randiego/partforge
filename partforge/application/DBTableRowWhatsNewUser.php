<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2020 Randall C. Black <randy@blacksdesign.com>
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

class DBTableRowWhatsNewUser extends DBTableRow {

	public function __construct() {
		parent::__construct('whats_new_user');
	}

	public function getRecordByKeyAndUser($key,$user_id) {
		$DBTableRowQuery = new DBTableRowQuery($this);
		$DBTableRowQuery->setLimitClause('LIMIT 1')->addSelectors(array('message_key' => $key, 'user_id' => $user_id));
		return $this->getRecord($DBTableRowQuery->getQuery());
	}
	

	public static function fetchWhatsNew($key,$message,$date_added,$expires_in_day=30,$max_shows=10) {
		$show = false;
		$update_count = !LoginStatus::getInstance()->returnLoginExists();
		// this makes the window show only between the date_added (one day earlier actually) and the expiration. 
		if ((script_time() < strtotime($date_added) + 3600*24*$expires_in_day) && (strtotime($date_added) > strtotime($_SESSION['account']->account_created)) && (script_time() > strtotime($date_added) - 24*3600)) {
			$Tip = new self();
			if ($Tip->getRecordByKeyAndUser($key, $_SESSION['account']->user_id)) {
				if (($Tip->view_count <= $max_shows) && !$Tip->hide) {
					$Tip->view_count++;
					if ($update_count) $Tip->save();
					$show = true;
				}
			} else {
				$Tip = new self();
				$Tip->message_key = $key;
				$Tip->user_id = $_SESSION['account']->user_id;
				$Tip->view_count = 0;
				$Tip->hide = 0;
				if ($update_count) $Tip->save();
				$show = true;
			}
		}
		$clear_link = $update_count ? '<div class="close_link"><a href="#" class="ok_i_got_it" data-key="'.$key.'">OK, I got it.</a></div>' : '';
		return $show ? '<div class="whats_new">New! '.$clear_link.$message.'</div>' : '';
	
	}
	
	public static function clearWhatsNew($key) {
		$ok = false;
		$update = !LoginStatus::getInstance()->returnLoginExists();
		$Tip = new self();
		if ($Tip->getRecordByKeyAndUser($key, $_SESSION['account']->user_id)) {
			if (!$Tip->hide) {
				$Tip->hide = 1;
				if ($update) {
					$Tip->save();
					$ok = true;
				}
			}
		}
		return $ok;
	}	

}
