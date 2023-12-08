<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2023 Randall C. Black <randy@blacksdesign.com>
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

class TableRowCalculatedFieldTester extends TableRow {

    public function __construct()
    {
        parent::__construct();
        $this->setFieldTypeParams('bool_1', 'boolean', '', false);
        $this->setFieldTypeParams('float_1', 'float', '', false);
        $this->setFieldTypeParams('float_2', 'float', '', false);
        $this->setFieldTypeParams('calculated_result', 'calculated', '', false, 'Calculated Results');
        $this->setFieldTypeParams('string_1', 'varchar', '', false);
        $this->setFieldTypeParams('string_2', 'varchar', '', false);
        $this->setFieldTypeParams('datetime_1', 'datetime', '', false);
        $this->setFieldTypeParams('datetime_2', 'datetime', '', false);
        $this->_fieldtypes['calculated_result']['expression'] = ' sqrt([float_1]^2 + [float_2]^2)';

    }

    public function evalAndCompare($expression, $vararray, $expected, &$messages)
    {
        foreach ($vararray as $name => $value) {
            $this->{$name} = $value;
        }
        $this->_fieldtypes['calculated_result']['expression'] = $expression;
        $this->processCalculatedFields();
        $result_var = $this->calculated_result;
        $issame = gettype($result_var)=='double' ? abs($expected - $result_var) < 1.e-12 : $expected === $result_var;

        $messages[] = array('expression' => $expression, 'inputs' => $vararray,
                            'value' => $result_var, 'error' => !$issame ? "value does not match {$expected}" : '');
    }

    public function test_validate_1()
    {
        $messages = array();
        $this->evalAndCompare('sqrt([float_1]^2 + [float_2]^2)', array('float_1' => 1.567, 'float_2' => "5.4356"), 5.6569635282544, $messages);
        $this->evalAndCompare('sqrt([float_1]^2 + [float_2]^2)', array('float_1' => 1.567e-3, 'float_2' => "5.4356e-4"), 0.0016585977431553, $messages);
        $this->evalAndCompare('[float_1] == [float_2]', array('float_1' => 1.567e-3, 'float_2' => "5.4356e-4"), 0, $messages);
        $this->evalAndCompare('[float_1] != [float_2]', array('float_1' => 1.567e-3, 'float_2' => "5.4356e-4"), 1, $messages);
        $this->evalAndCompare('([datetime_1] - [datetime_2])/3600', array('datetime_1' => "2023-10-02 08:06:12", 'datetime_2' => "2023-10-02 07:06:12"), 1, $messages);
        return $messages;
    }


}
