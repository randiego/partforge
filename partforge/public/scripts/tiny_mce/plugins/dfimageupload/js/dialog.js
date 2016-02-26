/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2015 Randall C. Black <randy@blacksdesign.com>
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

tinyMCEPopup.requireLangPack();

var DFImageUploadDialog = {
	init : function() {
		var f = document.forms[0];

		// Get the selected contents as text and place it in the input
		f.upload_url.value = tinyMCEPopup.getWindowArg('upload_url');
		f.typeobject_id.value = tinyMCEPopup.getWindowArg('typeobject_id');
		if (f.typeobject_id.value=='new') {
			alert('Sorry.  In order to upload photos you must first save your definition.');
			tinyMCEPopup.close();
		}
	},

	insert : function(teststring) {
		// Insert the contents from the input into the document
		tinyMCEPopup.editor.execCommand('mceInsertContent', false, teststring);
		tinyMCEPopup.close();
	}

};

tinyMCEPopup.onInit.add(DFImageUploadDialog.init, DFImageUploadDialog);
