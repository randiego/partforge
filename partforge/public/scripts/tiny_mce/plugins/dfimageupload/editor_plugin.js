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


(function() {
	// Load plugin specific language pack
	tinymce.PluginManager.requireLangPack('dfimageupload');

	tinymce.create('tinymce.plugins.DFImageUploadPlugin', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('dfImageUpload');
			ed.addCommand('mceDfimageupload', function() {
				ed.windowManager.open({
					file : url + '/dialog.htm',
					width : 350 + parseInt(ed.getLang('dfimageupload.delta_width', 0)),
					height : 160 + parseInt(ed.getLang('dfimageupload.delta_height', 0)),
					inline : 1
				}, {
					plugin_url : url, // Plugin absolute URL
					upload_url: ed.settings.dfimageupload_upload_url,
					typeobject_id: ed.settings.dfimageupload_typeobject_id,
					some_custom_arg : 'custom arg' // Custom argument
				});
			});

			// Register button
			ed.addButton('dfimageupload', {
				title : 'dfimageupload.desc',
				cmd : 'mceDfimageupload'
			});

			// Add a node change handler, selects the button in the UI when a image is selected
			ed.onNodeChange.add(function(ed, cm, n) {
				cm.setActive('dfimageupload', n.nodeName == 'IMG');
			});
		},

		/**
		 * Creates control instances based in the incomming name. This method is normally not
		 * needed since the addButton method of the tinymce.Editor class is a more easy way of adding buttons
		 * but you sometimes need to create more complex controls like listboxes, split buttons etc then this
		 * method can be used to create those.
		 *
		 * @param {String} n Name of the control to create.
		 * @param {tinymce.ControlManager} cm Control manager to use inorder to create new control.
		 * @return {tinymce.ui.Control} New control instance or null if no control was created.
		 */
		createControl : function(n, cm) {
			return null;
		},

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
				longname : 'PartForge file upload',
				author : 'Randall Black',
				authorurl : 'http://www.partforge.com',
				infourl : 'http://www.partforge.com',
				version : "1.0"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('dfimageupload', tinymce.plugins.DFImageUploadPlugin);
})();