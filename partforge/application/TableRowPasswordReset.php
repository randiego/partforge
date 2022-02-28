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

class TableRowPasswordReset extends TableRow {

    public function __construct()
    {
        parent::__construct();
        $this->setFieldTypeParams('password', 'varchar', '', false, 'New Password');
        $this->setFieldTypeParams('show_password', 'boolean', '', false, 'Show Password');
        $this->setFieldTypeParams('password2', 'varchar', '', false, 'Confirm Password');
        $this->setFieldTypeParams('has_temporary_password', 'boolean', '', false, 'Is this a temporary Password');
        $this->setFieldTypeParams('email_password', 'boolean', '', false, 'Email the new password to the user');
        $this->setFieldTypeParams('email', 'varchar', '', false, 'Send Email To Address');
        $this->setFieldTypeParams('user_id', 'varchar', '', false, 'User ID');
        $this->setFieldAttribute('email', 'input_cols', '80');
        $this->setFieldTypeParams('message', 'text', '', false, 'Message');
    }

    static public function getPasswordResetMessageTextForUserId($user_id, $is_new_user, $message, $password)
    {
        $User = new DBTableRowUser();
        $User->getRecordById($user_id);
        $url = getAbsoluteBaseUrl().'/user/login';
        if ($is_new_user) {
            return "{$message}\n\nYour account has been set up and is ready for you to login.\n\nUsername: {$User->login_id}\nPassword: {$password}\n\nYou can login here:\n".$url;
        } else {
            return "{$message}\n\nYour password has been reset to:\n{$password}\n\nYou can login here:\n".$url;
        }
    }

    public function getPasswordResetMessageText($message, $password)
    {
        return self::getPasswordResetMessageTextForUserId($this->user_id, $this->is_new_user, $message, $password);
    }

    public function formatInputTag($fieldname, $display_options = array())
    {
        switch ($fieldname) {
            case 'password' :
                return parent::formatInputTag($fieldname, $display_options).'<br />'.parent::formatInputTag('show_password', array('UseCheckForBoolean')).' <label for="show_password_1">Show Password</label>';
                break;
            case 'message' :
                return parent::formatInputTag($fieldname, $display_options).'<br />'.text_to_unwrappedhtml($this->getPasswordResetMessageText('', '[PASSWORD]'));
                break;
        }
        return parent::formatInputTag($fieldname, $display_options);
    }

}

?>
