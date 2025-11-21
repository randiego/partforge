/*
  Bootstrap JS for all application pages
*/

//this function includes all necessary js files for the application

var a_pop_dialogdiv_mutex = null;

function include(file)
{

  var script  = document.createElement('script');
  script.src  = file;
  script.type = 'text/javascript';
  script.defer = true;

  document.getElementsByTagName('head').item(0).appendChild(script);

}

// from http://stackoverflow.com/questions/901115/how-can-i-get-query-string-values
function getParameterByName(name) {
    name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(location.search);
    return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

function timeSelectHtmlForWatches(selectTagId, classId, timevalueHHMM) {
	var keyValueArray = [['00:00','00:00'],['01:00','01:00'],['02:00','02:00'],['03:00','03:00'],['04:00','04:00'],['05:00','05:00'],['06:00','06:00'],['07:00','07:00'],['08:00','08:00'],['09:00','09:00'],
	             ['10:00','10:00'],['11:00','11:00'],['12:00','12:00'],['13:00','13:00'],['14:00','14:00'],['15:00','15:00'],
	             ['16:00','16:00'],['17:00','17:00'],['18:00','18:00'],['19:00','19:00'],['20:00','20:00'],['21:00','21:00'],['22:00','22:00'],['23:00','23:00']];
	var component_value = timevalueHHMM;

	// generic select HTML processing
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

function fetchExcludeCheckHtml(changeCodeListing, followExcludeChangeCodes, is_for_definitions, affects_released_definitions, heading, classnm)
{
	var h = '';
	h += '<h2 class="'+classnm+'">'+heading+'</h2>';
	h += '<div class="ex-checks-div2 '+classnm+'">';
	for (var i = 0; i < changeCodeListing.length; i++) {
		var is_checked = followExcludeChangeCodes.indexOf(changeCodeListing[i]['change_code']) != -1;
		if (changeCodeListing[i]['is_for_definitions']==is_for_definitions && changeCodeListing[i]['affects_released_definitions']==affects_released_definitions) {
			h += '<label><input type="checkbox" class="chkexcludecls" id="exclude_'+i+'" value="'+changeCodeListing[i]['change_code']+'" '+(!is_checked ? 'checked="checked"' : '')+' />'+changeCodeListing[i]['change_code_name']+'</label><br />';
		}
	}
	h += '</div>';
	return h;
}

function renderExcludeChecks(changeCodeListing, this_is_a_definition)
{
	var h = '';
	var followExcludeChangeCodes = $("input[name='exclude_change_codes']").val();
	if (this_is_a_definition) {
		h += fetchExcludeCheckHtml(changeCodeListing, followExcludeChangeCodes, 1, 1, "changes affecting released definitions", '');
		h += fetchExcludeCheckHtml(changeCodeListing, followExcludeChangeCodes, 1, 0, "other changes to definitions", '');
	}
	h += fetchExcludeCheckHtml(changeCodeListing, followExcludeChangeCodes, 0, 0, "changes to items", 'itemsonlycheckcls');
	$('#ExcludeCodeDiv').html(h);
	showHideChecks(this_is_a_definition);
	saveCheckToField(changeCodeListing, this_is_a_definition);
	$('.chkexcludecls').on("click", function() {
		saveCheckToField(changeCodeListing, this_is_a_definition);
	});

}

function showHideChecks(this_is_a_definition)
{
	if (this_is_a_definition) {
		if ($('#followDialogContainer input[name="follow_items_too"]:checked').val()) {
			$('.itemsonlycheckcls').show();
		} else {
			$('.itemsonlycheckcls').hide();
		}
	}
}

function saveCheckToField(changeCodeListing, this_is_a_definition)
{
	var val = [];
	$('.chkexcludecls:checkbox:checked').each(function(i){
		val[i] = $(this).val();
	});

	var follow_items_too = $('#followDialogContainer input[name="follow_items_too"]:checked').val();
	var notval = [];
	for(var i = 0; i < changeCodeListing.length; i++) {
		var considerthecheck = ((changeCodeListing[i]['is_for_definitions']==1) && this_is_a_definition)
			|| ((changeCodeListing[i]['is_for_definitions']==0) && follow_items_too && this_is_a_definition)
			|| ((changeCodeListing[i]['is_for_definitions']==0) && !this_is_a_definition);
		if (considerthecheck && (val.indexOf(changeCodeListing[i]['change_code']) == -1)) {
			notval.push(changeCodeListing[i]['change_code']);
		}
	}
	$("input[name='exclude_change_codes']").val(notval.join(','));
}

/**
 * Used whereever a followButton id is located to construct dialog and add click handler to.
 * @param followUrl string url with constants _FOLLOWNOTIFYTIMEHHMM_, _NOTIFYINSTANTLY_, _NOTIFYDAILY_ to be substituted with the form results
 */
function activatefollowButton(followUrl, dialog_title, footnote_text, this_is_a_definition, forbid_item_watching, changeCodeListing) {
	if ($('#followButton').length) {
		// create the popup follow dialog
		$('<div />').attr('id','followDialogContainer').attr('title', dialog_title).hide().appendTo('body');
		var h = '';
		h += '<label><input type="checkbox" name="notify_instantly" value="1" '+(followInstantly==1 ? 'checked="checked"' : '')+' />Email Me Instantly</label><br />';
		h += '<label><input type="checkbox" name="notify_daily" value="1" '+(followDaily==1 ? 'checked="checked"' : '')+' />Send Me a Daily Summary at </label>'+timeSelectHtmlForWatches('timevalueHHMM', '', followNotifyTimeHHMM)+'<br /><div style="margin-left: 20px;"><span class="paren">(time is same for all your daily watches.)</span></div>';
		h += '<label><input type="checkbox" name="no_notify" value="1" checked="checked" disabled="disabled" />Show on my Watchlist (Activity Tab)</label><br />';
		if (this_is_a_definition && !forbid_item_watching) h += '<label><input type="checkbox" name="follow_items_too" value="1" '+(followItemsToo==1 ? 'checked="checked"' : '')+' />Also Include Any Items of This Type</label><br />';
		h += '<input type="hidden" name="exclude_change_codes" value="'+followExcludeChangeCodes+'" />';
		h += '<div class="ex-checks-div">';
		h += '<div id="ExcludeCodeDiv" />'
		h += '</div>';
		if (footnote_text!='') h += '<div class="watchboxfootnote"><span class="paren">'+footnote_text+'</span></div>';
		if (followNotifyEmailMsg!='') h += '<div class="watchboxfootnote"><span class="paren_red">Please fix the following problem before you can receive notifications: '+followNotifyEmailMsg+'</span></div>';
		$('#followDialogContainer').html(h);
		renderExcludeChecks(changeCodeListing, this_is_a_definition);

		$("input[name='follow_items_too']").on("click", function() {
			showHideChecks(this_is_a_definition);
		});
		// now connect the on click handler that will override the normal link
		$('#followButton').click(function(link) {
			var contentdiv = $('#followDialogContainer');
			var buttonsarr = {
					"OK": function() {
						var filledUrl = followUrl;
						filledUrl = filledUrl.replace('_FOLLOWNOTIFYTIMEHHMM_',$('#timevalueHHMM').val());
						filledUrl = filledUrl.replace('_NOTIFYINSTANTLY_',$('#followDialogContainer input[name="notify_instantly"]:checked').val() ? '1' : '0');
						filledUrl = filledUrl.replace('_NOTIFYDAILY_',$('#followDialogContainer input[name="notify_daily"]:checked').val() ? '1' : '0');
						filledUrl = filledUrl.replace('_EXCLUDECHANGECODES_',$('#followDialogContainer input[name="exclude_change_codes"]').val());
						if (this_is_a_definition && !forbid_item_watching) {
							filledUrl = filledUrl.replace('_ALLITEMS_',$('#followDialogContainer input[name="follow_items_too"]:checked').val() ? '1' : '0');
						}
						window.location.href = filledUrl;
						$( this ).dialog( "close" );
					}
			};
			if (unFollowUrl !== '') {
				buttonsarr['Stop Following'] = function() {
							window.location.href = unFollowUrl;
							$( this ).dialog( "close" );
						};
			}
			buttonsarr["Cancel"] = function() {
						$( this ).dialog( "close" );
					};
			pdfdialogdiv = contentdiv.dialog({
				position: { my: "left top", at: "right bottom", of: link },
				width: 300,
				height: 'auto',
				buttons: buttonsarr,
				close: function(event,ui) {$(this).dialog('destroy');}
			});
			return false; // prevents the default link
		});
	}
}

/**
 * Used to configure a popup dialog for showing the link to the current page
 */
function activateLinkToPageButton(element, lookupUrl, linkToPageUrl, layoutTitle, canSendLink) {
	if ($(element).length) {
		// create the popup link-to-page dialog
		$('<div />').attr('id','linkToPageDialogContainer').attr('title',"Link to This Page").hide().appendTo('body');
		var h = '';
		h += '<div style="margin-top:10px;">Copy to Clipboard with Ctrl+C:</div>';
		h += '<div style="margin-top:5px;"><input style="width:100%;" id="linktourlbox" name="urlbox" type="text" value="'+linkToPageUrl+'"></div>';
		h += '<div style="margin-top:10px; width: 50%; display: block; margin-left: auto; margin-right: auto;" id="qrcode"></div>';
		if (canSendLink) {
			h += '<div style="margin-top:10px;">Or, Send Page Link To:</div>';
			h += '<div style="margin-top:5px;"><input style="width:100%;" name="send_to_names" type="text" value="" id="send_to_names"></div>';
			h += '<div style="margin-top:10px;">Message:</div>';
			h += '<div style="margin-top:5px;"><textarea id="send_to_message" name="message" style="width:100%;"></textarea></div>';
		}
		$('#linkToPageDialogContainer').html(h);
		$('#qrcode').qrcode({width: 128, height: 128, text: linkToPageUrl});
		// now connect the on click handler that will override the normal link
		$(element).click(function(link) {
			var contentdiv = $('#linkToPageDialogContainer');
			var buttonslist = {};
			if (canSendLink) {
				buttonslist["Send"] = function() {
						$.getJSON(baseUrl+'/user/sendpagelink',
							{send_to_names : $('#send_to_names').val(),
							 send_to_message : $('#send_to_message').val(),
							 abs_page_url : linkToPageUrl,
							 page_title : layoutTitle},
							function(data) {
								if (data['error']) {
									alert('Error: ' + data['error']);
								} else {
									$('#send_to_message').val('');
									$('#send_to_names').val('');
									$( dialogdiv ).dialog( "close" );
								}
							});
					};
			}
			buttonslist["Close"] = function(event,ui) {
					$("#send_to_names").autocomplete("destroy");
					$(this).dialog('destroy');
				};
			dialogdiv = contentdiv.dialog({
				position: { my: "left top", at: "right bottom", of: link },
				width: 300,
				height: 'auto',
				buttons: buttonslist,
				close: function(event,ui) {
					$("#send_to_names").autocomplete("destroy");
					$(this).dialog('destroy');
				}
			});
			$('input[name="urlbox"]').select();  // preselects the text for easy copy
			attachNameSearchAutoComplete("#send_to_names", lookupUrl);
			return false; // prevents the default link
		});


	}
}

var treedialogdiv = null;  // this will force only one a time
function activeTreeViewLinks()
{
	$('.tree_pop_link').click(function(link){
		if (treedialogdiv!==null) {treedialogdiv.dialog('destroy'); treedialogdiv = null;}; // remove and show the new dialog. But only one at a time.
		var contentdiv = $(this).next();
		treedialogdiv = contentdiv.dialog({
			position: { my: "left+20 top-21", at: "left bottom", of: link },
			width: 400,
			height: 'auto',
			close: function(event,ui) {$(this).dialog('destroy'); treedialogdiv = null;}
		});
		var parts = contentdiv.attr('id').match(/tree_pop_([0-9]+)/);
		var itemobject_id = parts[1];
		$.getJSON(baseUrl + '/struct/jsontreeoflinks',
			{"itemobject_id" : itemobject_id},
			function(data) {
				if (typeof data != "undefined") {
					var html = data["html"];
					contentdiv.prev().children("span.ui-dialog-title").html(data["title"]);
					contentdiv.html(html);
				} else {
					alert("Did not get back response for itemobject_id = " +itemobject_id);
				}
			})
		.fail(function( jqxhr, textStatus, error ) {
			window.location.reload();
		});
		return false;

	});
}

var jsonfetchdata = {};


function attachNameSearchAutoComplete(element, autocomplete_lookupUrl)
{

	function split(val) {
		return val.split(/,\s*/);
	}
	function extractLast(term) {
		return split(term).pop();
	}


	$(element)
		// don't navigate away from the field on tab when selecting an item
		.on("keydown", function (event) {
			if (event.keyCode === $.ui.keyCode.TAB &&
				$(this).autocomplete("instance").menu.active) {
				event.preventDefault();
			}
		})
		.autocomplete({
			minLength: 0,
			source: function (request, response) {
				$.getJSON(autocomplete_lookupUrl, {
					term: extractLast(request.term)
				}, response);
			},
			focus: function () {
				// prevent value inserted on focus
				return false;
			},
			select: function (event, ui) {
				var terms = split(this.value);
				// remove the current input
				terms.pop();
				// add the selected item
				terms.push(ui.item.label);
				// add placeholder to get the comma-and-space at the end
				terms.push("");
				this.value = terms.join(", ");
				return false;
			}
		}).watermark('start typing name(s)...');


}


/** Open dialog and contact the indicated server to fetch key-value data in JSON format to populate form fields
 *
 * @param string url to call
 * @returns false
 */
function fetchJsonIntoInputs(url) {
	// setup the dialog or bail if we are trying this from something other than an input form.
	var contentdiv = $('#jsonFetchDialog');
	if (contentdiv.length==0) {
		alert('You are trying to import data, but this only works when you have an edit form open.');
		return false;
	}
	contentdiv.html('<p>Contacting Server at '+url+'...');
	dialogdiv = contentdiv.dialog({
		title: "Import data into form?",
		width: 600,
		height: 'auto',
		buttons: {
			"OK": function() {
				for(key in jsonfetchdata) {
					var target = $('#theform :input[name="'+key+'"]');
					if (target.length) {
						if (target.is(':radio')) {
							target.val([jsonfetchdata[key]]);
						} else {
							target.val(jsonfetchdata[key]);
						}
					}
				}
				contentdiv.html('');
				$( this ).dialog( "close" );
			},
			Cancel: function() {
				contentdiv.html('');
				$( this ).dialog( "close" );
			}
		},
		close: function(event,ui) {
			contentdiv.html('');
			$(this).dialog('destroy');
		}
	});

	$.getJSON(url, {},function(data) {
		var html = '<table class="json_import_tbl listtable"><tr><th>Import Property</th><th>Import Value</th><th>Field Match</th></tr>';
		jsonfetchdata = {};
		for(key in data) {
			var target = $('#theform :input[name="'+key+'"]');
			var matched = target.length ? '<span class="disposition Pass">Yes</span>' : '';
			if (target.length) {
				jsonfetchdata[key] = data[key];
			}
			html += "<tr><td>"+key+"</td><td>"+data[key]+"</td><td>"+matched+"</td></tr>";
		}
		html += "</table>";
		$('#jsonFetchDialog').html(html);
		return false; // prevents the default link
	}).fail(function(jqXHR, textStatus, errorThrown){
		contentdiv.append('<p>Failed to get a valid JSON string back from server.</p>');
		contentdiv.append('<p>Status: '+textStatus+'</p>');
		contentdiv.append('<p>Server Response: '+jqXHR.responseText+'</p>');
	});
	return false;
}

function handleCommentSentPopup(targ, theposition, thedialogdiv)
{
	var contentdiv = $(targ).next();
	if (thedialogdiv!==null) return false;
	thedialogdiv = contentdiv.dialog({
		position: theposition,
		width: 600,
		height: 'auto',
		close: function(event,ui) {$(this).dialog('destroy'); thedialogdiv = null;}
	});
	var parts = contentdiv.attr('id').match(/comment_sent_pop_([0-9]+)/);
	var comment_id = parts[1];
	$.getJSON(baseUrl + '/struct/commentsentlist',
			{"comment_id" : comment_id},
			function(data) {
				if (typeof data != "undefined") {
					var html = '';
					html += '<div class="bd-list-container"><ul class="bd-stream-list">';
					for(var i=0; i < data.length; i++) {
						html += '<li class="bd-event-row bd-type-message-sent">';
						html += '<div class="bd-event-content"><div class="bd-event-type"></div>';
						html += '<div class="bd-event-whowhen"><div class="bd-byline">'+data[i].from+'</div><div class="bd-dateline">'+data[i].sent_on+'</div></div>';
						html += '<div class="bd-event-message"><span style="font-weight: bold; margin-right: 1em;">Comment Sent To:</span>' + data[i].to.join(', ') + '</div>';
						html += '</div></li>';
					}
					html += '</ul></div>';
					contentdiv.html(html);
				} else {
					alert("Did not get back response for comment_id = " +comment_id);
				}
			});

}


/* include any js files here */

$(document).ready(function() {
    $('.last_select, .bd-event-row.event_afterglow_c .bd-event-content').animate({
      backgroundColor: "#FFF"
    }, 5000, function() {
      // Animation complete.
    });

    $('.bd-event-row.event_afterglow_r .bd-event-content').animate({
        backgroundColor: "#EEEEFF"
      }, 5000, function() {
        // Animation complete.
    });

    $("input.jq_datepicker").datepicker({
    });

    $("input.jq_datetimepicker").datetimepicker({
    });

    // autocomplete is really distracting with the date time picker
    $("input.jq_datetimepicker, input.jq_datepicker").attr('autocomplete','off');


	if ( $('table.edittable th span.req_field').length > 0) {
		$("p.req_field_para").show();
	}

	if ( $('table.edittable th span.locked_field').length > 0) {
		$("p.locked_field_para").show();
	}

	// ajax call to dismis a particular what's new dialog
    $(".ok_i_got_it").click( function (event) {
    	element = $(this).closest('div.whats_new');
    	var dataKey = $(this).attr('data-key');
	    $.getJSON(baseUrl+'/struct/tipsokigotit',
		{key : dataKey},
		function(data) {
			if (data['ok']==1) {
				element.hide();
			}
		})
		return false;
	});

    // do this automatically for everything.
	$('.a_pop_link').click(function(link){
		var contentdiv = $(this).next();
		if (a_pop_dialogdiv_mutex!==null) return false;
		a_pop_dialogdiv_mutex = contentdiv.dialog({
			position: { my: "left top", at: "right bottom", of: link },
			width: 600,
			height: 'auto',
			close: function(event,ui) {$(this).dialog('destroy'); a_pop_dialogdiv_mutex = null;}
		});
	});

});



$.fn.scrollTo = function( target, options, callback ){
	  if(typeof options == 'function' && arguments.length == 2){ callback = options; options = target; }
	  var settings = $.extend({
	    scrollTarget  : target,
	    offsetTop     : 50,
	    duration      : 500,
	    easing        : 'swing'
	  }, options);
	  return this.each(function(){
	    var scrollPane = $(this);
	    var scrollTarget = (typeof settings.scrollTarget == "number") ? settings.scrollTarget : $(settings.scrollTarget);
	    var scrollY = (typeof scrollTarget == "number") ? scrollTarget : scrollTarget.offset().top + scrollPane.scrollTop() - parseInt(settings.offsetTop);
	    scrollPane.animate({scrollTop : scrollY }, parseInt(settings.duration), settings.easing, function(){
	      if (typeof callback == 'function') { callback.call(this); }
	    });
	  });
	}

