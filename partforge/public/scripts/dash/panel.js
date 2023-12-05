var typeDescriptions = []; // this is an array of array pairs (typeobject_id, description) for parts.
var typeDescByTypeObject = {};
var typeObjectsAndSerNums = {};
var dellink = '<IMG style="vertical-align:middle;" src="'+baseUrl+'/images/deleteicon.png" width="16" height="16" border="0" alt="Delete this table">';
var typeselobj = null;  // need global reference to this.

function initColorPicker() {
	var colorList = [ '000000', '993300', '333399', 'FF6633', '666699', '666666', 'CC3333', 'FF9933', '99CC33', '669966', '66CCCC', '3366FF', '663366', '999999', 'CC66FF', 'FFCC33', 'FFFF66', '99FF66', '99CCCC', '66CCFF', 'FF0000', 'FF1493', 'FFA500', 'FFFF00', '00FF00', '0092FF', '00FFFF', '9900FF', 'CC99FF', 'FFFFFF' ];
	var picker = $("#color-picker");
	for (var i = 0; i < colorList.length; i++ ) {
		picker.append('<li class="color-item" data-hex="' + '' + colorList[i] + '" style="background-color:' + '#' + colorList[i] + ';"></li>');
	}
	$('body').on("click", function () {
		picker.hide(0);
	});
	$('.call-picker').on("click",function(event) {
		event.stopPropagation();
		picker.show(0);
		picker.children('li').hover(function() {
			var codeHex = $(this).data('hex');
			$('.color-holder').css('background-color', '#' + codeHex);
			$('#tablecolor_id').val(codeHex);
		});
	});
}

function selectHtml2(selectTagId, classId, keyValueArray, component_value) {
	var html = "";
	var selected;
	html += '<select id="'+selectTagId+'" class="'+classId+'">';
	for(var i=0; i<keyValueArray.length; i++) {
		var key = keyValueArray[i][0];
		var val = keyValueArray[i][1];
		selected = (key == component_value) ? " selected=selected" : "";
		html += '<option value="'+key+'"'+selected+'>'+val+'</option>';
	}
	html += '</select>';
	return html;
}

var dashtabledialogdiv = null;  // this will force only one a time
/**
 * Create and activate the Dashboard Table editor dialog (popup) for editing a single table.
 * @param {jquery selector object} element - button element that pops this up
 * @param {string} editUrl template URL that is filled out and called when OK is submitted
 */
function activateDashboardTableEditButton(element, editUrl) {
	if ($(element).length) {
		// create the popup link-to-page dialog
		if (dashtabledialogdiv===null) {
			dashtabledialogdiv = $('<div />').attr('id','linkToDashboardTableEditDialogContainer').attr('title',"Edit Dashboard Table").attr('class',"dashdialog").hide().appendTo('body');
		}

		// now connect the on click handler that will override the normal link
		$(element).on("click",function(link) {
			var h = '';
			var idx = $(this).closest('.dashheaddiv')[0].id.split('_')[1]; // looks to parents for first one with class.
			var fieldnames = $('input[name="tablefields['+idx+']"]').val().split(',');
			h += '<div class="db-label">Title</div>';
			h += '<div style="margin-top:5px;"><input style="width:100%;" type="text" value="'+$('input[name="tabletitle['+idx+']"]').val()+'" id="tabletitle_id"></div>';
			h += '<div class="db-label">Sidebar Color</div>';
			h += '<div class="color-wrapper">';
			h += '<input type="text" value="'+$('input[name="tablecolor['+idx+']"]').val()+'" id="tablecolor_id" class="call-picker">';
			h += '<div class="color-holder call-picker" style="background-color:' + '#' + $('input[name="tablecolor['+idx+']"]').val() + ';"></div><div class="color-picker" id="color-picker" style="display: none"></div>';
			h += '</div>';
			h += '<div class="db-label">Include Columns<br /><span class="paren"> (drag to change order)</span></div>';
			h += '<div id="columnlistdiv">';
			for (var i = 0; i < tabledata[idx]['names'].length; i++) {
				var fn = tabledata[idx]['names'][i];
				var caption = tabledata[idx]['data'][fn]['display'];				// need to check for fn.contains("ref_procedure_typeobject_id") to know if procedure
				var style = ' class="dictionary_field"';
				if (fn.includes("ref_procedure_typeobject_id")) {
					style = ' class="proc_field"';
				} else if (fn.includes("column_notes_")) {
						style = ' class="note_field"';
				} else if (fn.includes("__comments__")) {
						style = ' class="comments_field"';
				}
				h += '<div><label><input type="checkbox" name="included_fields" value="'+fn+'" '+(fieldnames.includes(fn) ? 'checked="checked"' : '')+' /><span'+style+'>'+caption+'</span></label></div>';
			}
			h += '</div>'


			$('#linkToDashboardTableEditDialogContainer').html(h);
			initColorPicker();
			$('#columnlistdiv').sortable();
			var contentdiv = $('#linkToDashboardTableEditDialogContainer');
			var buttonslist = {};
			buttonslist["OK"] = function() {
				var filledUrl = editUrl;
				filledUrl = filledUrl.replace('_DASHBOARDTABLEID_',idx);
				filledUrl = filledUrl.replace('_TITLE_',$('#tabletitle_id').val());
				filledUrl = filledUrl.replace('_COLOR_',$('#tablecolor_id').val());
				var fields = [];
				$('input[type=checkbox]:checked').each(function () {
					fields.push($(this).val());
				});
				filledUrl = filledUrl.replace('_TABLEFIELDS_',fields.join(","));
				window.location.href = filledUrl;
				$( this ).dialog( "close" );
			};
			buttonslist["Close"] = function(event,ui) {
				$(this).dialog('destroy');
			};
			dialogdiv = contentdiv.dialog({
				position: { my: "left top", at: "right bottom", of: link },
				width: 400,
				height: 'auto',
				buttons: buttonslist,
				close: function(event,ui) {
					$(this).dialog('destroy');
				}
			});
			return false; // prevents the default link
		});
	}
}

/**
 * Create and activate the full Dashboard editor dialog (popup) for editing the layout of the whole dashboard.
 * @param {string} editUrl template URL that is filled out and called when OK is submitted
 * @param {jquery selector object} link - the button the user has clickd to get here.
 */
function renderDashboardEditButton(editUrl, link) {
	var h = '';
	h += '<div class="db-label">Dashboard Title</div>';
	h += '<div style="margin-top:5px;"><input style="width:100%;" type="text" value="'+$('input[name="dashboardtitle"]').val()+'" id="dashboardtitle_id"></div>';
	h += '<div class="db-label">Visibility</div>';
	var ischecked = $('input[name="dashboardispublic"]').val() == 1 ? 'checked="checked" ' : '';
	h += '<div style="margin-top:5px;"><label><input type="checkbox" id="publicdashboardchk" value="1" '+ischecked+' />Public (others can view this dashboard)</label></div>';

	h += '<div class="db-label">Tables<br /><span class="paren"> (drag to change order)</span></div>';
	h += '<div id="tablelistdiv">';
	$('.dashheaddiv').each(function( index ) {
		var idx = $(this)[0].id.split('_')[1];
		var colorcode = $('input[name="tablecolor['+idx+']"]').val();
		var typeobject_id = $(this).data('typeobject_id');
		h += '<div class="edittableorderrow" data-idx="'+idx+'" data-typeobject_id="'+typeobject_id+'" style="border-left: 5px solid #'+colorcode+'; padding-left:5px;"><span>'+typeDescByTypeObject[typeobject_id]+'</span><a href="#" class="duprowbtn bd-linkbtn">copy</a><a href="#" class="delrowbtn">'+dellink+'</a></div>';
	});

	var copyDesc = [["","-- add new table to dashboard --"]];
	copyDesc = copyDesc.concat(typeDescriptions);
	var valueinput = selectHtml2('addTypeObjectSelId','compsel', copyDesc, "");
	h += '<div class="edittableorderrow" data-idx="new" data-typeobject_id="" style="z-index:200; border-left: 5px solid #FFF; padding-left:5px;"><span>'+valueinput+'</span></div>';
	h += '</div>';

	$('#linkToDashboardOrgContainer').html(h);

	typeselobj = $('#addTypeObjectSelId').comboboxjumper({hidecurrentvaluewhenchanging: 1, containersel : "#linkToDashboardOrgContainer", myclassname : "typeselinput"});
	var contentdiv = $('#linkToDashboardOrgContainer');
	// $( ".minibutton2" ).button();  // format the button we just created above
	$("#tablelistdiv").sortable();
	$(".duprowbtn").on("click",function(){
		var src = $(this).closest(".edittableorderrow")[0].outerHTML;
		src = src.split("<a ")[0] + src.split("</a>")[1]; // can't think of slicker way to get rid of <a> at the end.
		var ins = $(src).insertAfter($(this).closest(".edittableorderrow"));
		ins.find('a.delrowbtn').on("click",function(){
			if (confirm("Are you sure you want to delete this?")) {
				$(this).closest(".edittableorderrow").remove();
				return false;
			}
			return false;
		});
		return false;
	});
	$(".delrowbtn").on("click",function(){
		if (confirm("Are you sure you want to delete this?")) {
			$(this).closest(".edittableorderrow").remove();
		}
		return false;
	});
	$("#addTypeObjectSelId").on("change",function(event){
		var typeobject_id = $(this).val();
		var colorcode = 'EEE';
		var src = '<div class="edittableorderrow" data-idx="new" data-typeobject_id="'+typeobject_id+'" style="border-left: 5px solid #'+colorcode+'; padding-left:5px;"><span>'+typeDescByTypeObject[typeobject_id]+'</span><a href="#" class="delrowbtn">'+dellink+'</a></div>';
		var ins = $(src).insertBefore($(this).closest(".edittableorderrow"));
		ins.find('a.delrowbtn').on("click",function(){
			if (confirm("Are you sure you want to delete this?")) {
				$(this).closest(".edittableorderrow").remove();
				return false;
			}
			return false;
		});
		var comboboxinst = $('#addTypeObjectSelId').data('custom-comboboxjumper');
		setTimeout(function() {
			$('#addTypeObjectSelId').next().find('input').val('');
		}, 500);
		return true;
	});
	var buttonslist = {};
	buttonslist["OK"] = function() {
		var filledUrl = editUrl;
		filledUrl = filledUrl.replace('_TITLE_',$('#dashboardtitle_id').val());
		filledUrl = filledUrl.replace('_ISPUBLIC_',$('#publicdashboardchk:checked').val() ? '1' : '0');
		var tables = [];
		$('#tablelistdiv .edittableorderrow').each(function () {
			if ($(this).data("typeobject_id") != "") {
				tables.push($(this).data("idx")+"|"+$(this).data("typeobject_id"));
			}
		});
		filledUrl = filledUrl.replace('_TABLEIDS_',tables.join(","));
		window.location.href = filledUrl;
		$( this ).dialog( "close" );
	};
	buttonslist["Close"] = function(event,ui) {
		$(this).dialog('destroy');
	};
	dialogdiv = contentdiv.dialog({
		position: { my: "left top", at: "right bottom", of: link },
		width: 400,
		height: 'auto',
		buttons: buttonslist,
		close: function(event,ui) {
			$(this).dialog('destroy');
		}
	});
	return false; // prevents the default link
}

var dashdialogdiv = null;  // this will force only one a time
/**
 * We call this instead of renderDashboardEditButton() directly because we want we want to cache the fetched list of
 * type object descriptions.
 * @param {*} element
 * @param {*} editUrl
 */
function activateDashboardEditButton(element, editUrl) {
	if ($(element).length) {
		// create the popup link-to-page dialog
		if (dashdialogdiv===null) {
			dashdialogdiv = $('<div />').attr('id','linkToDashboardOrgContainer').attr('title',"Organize Dashboard").attr('class',"dashdialog").hide().appendTo('body');
		}
		// now connect the on click handler that will override the normal link
		$(element).on("click",function(link) {
			if (typeDescriptions.length == 0) {
				$.getJSON(baseUrl + '/struct/jsonlistoftypedescriptions',
						{"typecategory_id" : "2"},
						function(data) {
							typeDescriptions = data;
							for (var tdi=0; tdi<typeDescriptions.length; tdi++) {
								typeDescByTypeObject[typeDescriptions[tdi][0]] = typeDescriptions[tdi][1];
							}
							renderDashboardEditButton(editUrl, link);
						});
			} else {
				renderDashboardEditButton(editUrl, link);
			}
			return false;
		});
	}
}

function renderSerNumEditButton(editUrl, link, idx) {
	var h = '';
	var typeobject_id = $('input[name="tabletypeobject['+idx+']"]').val();
	var autoadd_new_items = $('input[name="tableautoadd['+idx+']"]').val();
	var sel_sernums = $('input[name="tablesernums['+idx+']"]').val().split(',');
	if (sel_sernums.length==1 && ("" in sel_sernums)) {
		sel_sernums = {}
	}
	h += '<div class="db-label">Auto-add</div>';
	var ischecked = autoadd_new_items == 1 ? 'checked="checked" ' : '';
	h += '<div style="margin-top:5px;"><label><input type="checkbox" id="autoadd_new_items_chk" value="1" '+ischecked+' />When a new serial number is created, automatically add it to this list.</label></div>';
	h += '<div class="db-label">Serial Number to Include in Table<br /><span class="paren">(<a id="uncheckall">uncheck all</a> for no filtering)</span></div>';
	h += '<div id="tablelistdiv" style="">';
	for (var ii in typeObjectsAndSerNums[typeobject_id]) {
		var sernum = typeObjectsAndSerNums[typeobject_id][ii][1];
		var ioid = typeObjectsAndSerNums[typeobject_id][ii][0];
		var style = ' style="font-weight: normal; color: #0073EA; font-size: 12px;"';
		h += '<div><label><input type="checkbox" name="included_sernums" value="'+ioid+'" '+(sel_sernums.includes(ioid) ? 'checked="checked"' : '')+' /><span'+style+'>'+sernum+'</span></label></div>';
	}
	h += '</div>';

	$('#linkToDashboardSerNumContainer').html(h);
	var contentdiv = $('#linkToDashboardSerNumContainer');
	$('#uncheckall').on('click', function(event){
		$('#tablelistdiv').find('input[type=checkbox]').prop('checked', false);
		return false;
	});
	var buttonslist = {};
	buttonslist["OK"] = function() {
		var filledUrl = editUrl;
		filledUrl = filledUrl.replace('_DASHBOARDTABLEID_',idx);
		var ios = [];
		$('#tablelistdiv').find('input[type=checkbox]:checked').each(function () {
			ios.push($(this).val());
		});
		filledUrl = filledUrl.replace('_ITEMOBJIDS_', ios.join(','));
		filledUrl = filledUrl.replace('_AUTOADD_', $('#autoadd_new_items_chk:checked').val() && (ios.length > 0) ? '1' : '0');
		window.location.href = filledUrl;
		$( this ).dialog( "close" );
	};
	buttonslist["Close"] = function(event,ui) {
		$(this).dialog('destroy');
	};
	dialogdiv = contentdiv.dialog({
		position: { my: "left top", at: "right bottom", of: link },
		width: 300,
		height: 500,
		buttons: buttonslist,
		close: function(event,ui) {
			$(this).dialog('destroy');
		}
	});
	return false; // prevents the default link
}

var sernumdialogdiv = null;
function activateDashboardSerNumEditButton(element, editUrl) {
	if ($(element).length) {
		// create the popup link-to-page dialog
		// only want to create this div once
		if (sernumdialogdiv===null) {
			sernumdialogdiv = $('<div />').attr('id','linkToDashboardSerNumContainer').attr('title',"Select Serial Numbers").attr('class',"dashdialog").hide().appendTo('body');
		}
		// now connect the on click handler that will override the normal link
		$(element).on("click",function(link) {
			var idx = $(this).closest('.dashheaddiv')[0].id.split('_')[1]; // looks to parents for first one with class.
			var typeobject_id = $('input[name="tabletypeobject['+idx+']"]').val();
			if (!(typeobject_id in typeObjectsAndSerNums)) {
				$.getJSON(baseUrl + '/api/getserialnumbers',
						{"typeobject_id" : typeobject_id, "json_array" : "pairs", "sort_order" : "created_desc"},
						function(data) {
							typeObjectsAndSerNums[typeobject_id] = data;
							renderSerNumEditButton(editUrl, link, idx);
						});
			} else {
				renderSerNumEditButton(editUrl, link, idx);
			}
			return false;
		});
	}
}

// keep pinging the server when I'm editing a note
var editing_keep_alive = false;
function startKeepAliveProcess() {
	if (editing_keep_alive) {
		$.getJSON(baseUrl + '/struct/keepalive',
			{},
			function(data) {
				var not_valid_user = typeof data['is_valid_user'] == 'undefined';
			});
		setTimeout('startKeepAliveProcess()', sessionTimeoutInterval);
	}
}

function renderColumnNoteEditButton(editUrl, link, data) {
	var comment_id = data['dashboardcolumnnote_id'];
	var comment_value = data['value'];
	var dashboardtable_id = data['dashboardtable_id'];
	var itemobject_id = data['itemobject_id'];
	var h = '';
	var id_name = 'comment_id_'+comment_id;
	h += '<textarea class="columnnotetext" id="'+id_name+'">'+comment_value+'</textarea>';
	$('#linkToCommentNotesContainer').html(h);
	var contentdiv = $('#linkToCommentNotesContainer');
	var targetdiv = $("div.dash-column-container[data-itemobject_id='"+itemobject_id+"'][data-dashboard_id='"+dashboardtable_id+"'] .dash-column-content");
	var buttonslist = {};
	buttonslist['OK'] = function() {
		$.getJSON(baseUrl + '/dash/updatecolumnnote',
			{'dashboardTableId' : dashboardtable_id, 'itemobjectId' : itemobject_id, 'commentValue' : $('#comment_id_'+comment_id).val()},
			function(commentdata) {
				targetdiv.html(commentdata['html_value']);
				if (commentdata['html_value'].length > 0) {
					targetdiv.closest('td').addClass('notebkg');
				} else {
					targetdiv.closest('td').removeClass('notebkg');
				}

			});
		$(this).dialog('destroy');
		editing_keep_alive = false;
	}
	buttonslist["Close"] = function(event,ui) {
		$(this).dialog('destroy');
		editing_keep_alive = false;
	};
	editing_keep_alive = true;
	startKeepAliveProcess();
	dialogdiv = contentdiv.dialog({
		position: { my: "left top", at: "left top", of: targetdiv },
		width: 500,
		height: 500,
		buttons: buttonslist,
		close: function(event,ui) {
			$(this).dialog('destroy');
			startKeepAliveProcess();
		}
	});
	textAreaScrollToBottom('#'+id_name);
	return false; // prevents the default link
}

function textAreaScrollToBottom(selector) {
	// weird gyrations to put cursor at end of input.
	var box = $(selector);
	box.focus();
	var tmpStr = box.val();
	box.val('');
	box.val(tmpStr);
	box.scrollTop(box[0].scrollHeight);  // chrome
}

var notedialogdiv = null;  // this will force only one a time
function activateColumnNoteEditButton(element, editUrl) {
	if ($(element).length) {
		// create the popup link-to-page dialog
		$(element).on("click",function(link) {
			// only want to create this div once
			if (notedialogdiv===null) {
				var dial_title = $('input[name="dashboardispublic"]').val() == 1 ? "My Notes (public)" : "My Notes (private)";
				notedialogdiv = $('<div />').attr('id','linkToCommentNotesContainer').attr('title',dial_title).attr('class',"dashdialog").hide().appendTo('body');
			}
			var dashboardtable_id = $(this).closest('.dash-column-container').data('dashboard_id');
			var itemobject_id = $(this).closest('.dash-column-container').data('itemobject_id');
			$.getJSON(baseUrl + '/dash/jsongetcolumnnote',
					{"dashboardtable_id" : dashboardtable_id, "itemobject_id" : itemobject_id},
					function(data) {
						renderColumnNoteEditButton(editUrl, link, data);
					});
			return false;
		});
	}
}

$(document).ready(function() {
	activateLinkToPageButton('#linkToPageButton', lookupUrl, linkToPageUrl, layoutTitle, canSendLink);
	activeTreeViewLinks();
	activateDashboardTableEditButton('.dashtableeditbtn', editdashboardtableUrl);
	activateDashboardSerNumEditButton('.dashsernumeditbtn', editsernumsUrl);
	activateDashboardEditButton('#editDashboardButton', editdashboardUrl);
	activateColumnNoteEditButton('.columnnoteeditbtn', editcolumnnoteUrl);
	$('select.dashboardselector').comboboxjumper({hidecurrentvaluewhenchanging: 1});
	// for all the overfull procedure columns, scroll to the bottom so we see the latest ones.
	$('table.listtable tr td div.cellofprocs, div.dash-column-content').each(function() {
		$(this).scrollTop($(this)[0].scrollHeight);
	});
});

