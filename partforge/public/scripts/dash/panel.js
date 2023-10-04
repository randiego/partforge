var typeDescriptions = []; // this is an array of array pairs (typeobject_id, description) for parts.
var typeDescByTypeObject = {};
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

/**
 * Create and activate the Dashboard Table editor dialog (popup) for editing a single table.
 * @param {jquery selector object} element - button element that pops this up
 * @param {string} editUrl template URL that is filled out and called when OK is submitted
 */
function activateDashboardTableEditButton(element, editUrl) {
	if ($(element).length) {
		// create the popup link-to-page dialog
		$('<div />').attr('id','linkToPageDialogContainer').attr('title',"Edit Dashboard Table").attr('class',"dashdialog").hide().appendTo('body');

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
				var style = ' style="font-weight: normal; color: #0073EA; font-size: 12px;"';
				if (fn.includes("ref_procedure_typeobject_id")) {
					style = ' style="font-weight: normal; font-size: 12px;"';
				}
				h += '<div><label><input type="checkbox" name="included_fields" value="'+fn+'" '+(fieldnames.includes(fn) ? 'checked="checked"' : '')+' /><span'+style+'>'+caption+'</span></label></div>';
			}
			h += '</div>'


			$('#linkToPageDialogContainer').html(h);
			initColorPicker();
			$('#columnlistdiv').sortable();
			var contentdiv = $('#linkToPageDialogContainer');
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
	h += '<div style="margin-top:5px;"><label><input type="checkbox" id="publicdashboardchk" value="1" '+ischecked+' />Other users can view this Dashboard</label></div>';

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

/**
 * We call this instead of renderDashboardEditButton() directly because we want we want to cache the fetched list of
 * type object descriptions.
 * @param {*} element
 * @param {*} editUrl
 */
function activateDashboardEditButton(element, editUrl) {
	if ($(element).length) {
		// create the popup link-to-page dialog
		$('<div />').attr('id','linkToDashboardOrgContainer').attr('title',"Organize Dashboard").attr('class',"dashdialog").hide().appendTo('body');
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

$(document).ready(function() {
	activateLinkToPageButton('#linkToPageButton', lookupUrl, linkToPageUrl, layoutTitle, canSendLink);
	activeTreeViewLinks();
	activateDashboardTableEditButton('.dashtableeditbtn', editdashboardtableUrl);
	activateDashboardEditButton('#editDashboardButton', editdashboardUrl);
	$('select.dashboardselector').comboboxjumper({hidecurrentvaluewhenchanging: 1});
	// for all the overfull procedure columns, scroll to the bottom so we see the latest ones.
	$('table.listtable tr td div.cellofprocs').each(function() {
		$(this).scrollTop($(this)[0].scrollHeight);
	});
});

