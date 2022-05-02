<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2022 Randall C. Black <randy@blacksdesign.com>
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

class DBTableRowProcedureSortOrder extends DBTableRow {

    public function __construct()
    {
        parent::__construct('proceduresortorder');
    }

    static public function sortProcedures($when_viewed_by_typeobject_id, $of_typeobject_ids)
    {
        $records = DbSchema::getInstance()->getRecords('proceduresortorder_id',  "SELECT * FROM proceduresortorder
                    WHERE when_viewed_by_typeobject_id='".addslashes($when_viewed_by_typeobject_id)."'
                    ORDER BY sort_order");
        // only save stuff if there is a change.
        if (implode(',', array_keys($records)) != implode(',', $of_typeobject_ids)) {
            DbSchema::getInstance()->mysqlQuery("DELETE FROM proceduresortorder WHERE when_viewed_by_typeobject_id='".addslashes($when_viewed_by_typeobject_id)."'");
            $sort_order = 1;
            foreach ($of_typeobject_ids as $of_typeobject_id) {
                $Record = new self();
                $Record->sort_order = $sort_order++;
                $Record->of_typeobject_id = $of_typeobject_id;
                $Record->when_viewed_by_typeobject_id = $when_viewed_by_typeobject_id;
                $Record->save();
            }
            // add record to history
            $History = new DBTableRow('proceduresorthistory');
            $History->when_viewed_by_typeobject_id = $when_viewed_by_typeobject_id;
            $History->to_user_id = $_SESSION['account']->user_id;
            $History->sort_order_typeobject_ids = implode(',', $of_typeobject_ids);
            $History->save();
        }
    }

}
