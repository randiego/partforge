function focusOnComment() {
	$('#commentTextId').focus();
}

// delete
function getScrollKeyName() {
	return "itemviewrightpanelscrolly" + getParameterByName('itemversion_id');
}

function myScrollToCookiePosition() {
	$('#rightpanel').scrollTop($.cookie(scrollPosKeyRight));
	$('#leftpanel').scrollTop($.cookie(scrollPosKeyLeft));
}

function myScrollStartScrollKeeper() {
	setInterval("$.cookie(scrollPosKeyRight,$('#rightpanel').scrollTop())",500);
	setInterval("$.cookie(scrollPosKeyLeft,$('#leftpanel').scrollTop())",500);
}

function initPanelScrollSaver() {
	if ((getParameterByName('resetview')!="1")) {
	    window.setTimeout("myScrollToCookiePosition(); myScrollStartScrollKeeper();",1); // needed to do this so the setfocus message didn't come after this.
	} else {
		window.setTimeout("$('#rightpanel').scrollTop(0); $('#leftpanel').scrollTop(0); myScrollStartScrollKeeper();",1);  // sometimes the setfocus scrolls the top out of view.  This beings it back
	}
}

function scrollRightSideToVersionId(version_id) {
	//$('#itemversion_ck_' + version_id).parent().parent().get(0).scrollIntoView();
	$container = $('#rightpanel');
	$scrollTo = $('#itemversion_ck_' + version_id).parent().parent();
	$container.animate({
	    scrollTop: $scrollTo.offset().top - $container.offset().top + $container.scrollTop()
	});
	return false;
}

$(document).ready(function() {

	activeTreeViewLinks();

	$('#AddAnotherID').effect("highlight", {color:"#3F3"}, 10000);

	$(".jumpmenu").button({
		icons : {
			secondary : "ui-icon-triangle-1-s"
		}
	}).click(function() {
		var menu = $(this).next().show().position({
			my : "left top",
			at : "left bottom",
			of : this
		});
		$(document).one("click", function() {
			menu.hide();
		});
		return false;
	}).next().hide().menu();

	$(".used-on-button").button({icons: {primary: "ui-icon-arrowthick-1-nw"}});
	$(".used-on-button.jumpmenu").button({icons: {primary: "ui-icon-arrowthick-1-nw", secondary:"ui-icon-triangle-1-s"}});

	function moveFloatMenu() {
		var menuOffset = menuYloc.top + $(this).scrollTop() + 'px';
		$('#toc').animate({top:menuOffset},{duration:300,queue:false});
	}

    initPanelScrollSaver();

    $('#search_string').watermark('part number, SN, or locator');

    var dialogdiv = null;  // this will force only one a time
	$('.unversioned_pop_link').click(function(link){
		var contentdiv = $(this).next();
		if (dialogdiv!==null) return false;
		dialogdiv = contentdiv.dialog({
			position: { my: "right top", at: "left bottom", of: link },
			width: 600,
			height: 'auto',
			close: function(event,ui) {$(this).dialog('destroy'); dialogdiv = null;}
		});
		var parts = contentdiv.attr('id').match(/unversioned_pop_([0-9]+)/);
		var itemversion_id = parts[1];
		$.getJSON(baseUrl + '/struct/jsonarchivechanges',
				{"itemversion_id" : itemversion_id},
				function(data) {
					if (typeof data != "undefined") {
						var html = '';
						html += '<div class="bd-list-container"><ul class="bd-stream-list">';
						for(var i=0; i < data.length; i++) {
							html += '<li class="bd-event-row bd-type-change">';
							html += '<div class="bd-event-content"><div class="bd-event-type"></div>';
							html += '<div class="bd-event-whowhen"><div class="bd-byline">'+data[i].name+'</div><div class="bd-dateline-edited">'+data[i].date+'</div></div>';
							html += '<div class="bd-event-message">' + data[i].differences + '</div>';
							html += '</div></li>';
						}
						html += '</ul></div>';
						contentdiv.html(html);
					} else {
						alert("Did not get back response for itemversion_id = " +itemversion_id);
					}
				});

	});

	$('.comment_sent_pop_link').click(function(link){
		handleCommentSentPopup(this, { my: "right top", at: "left bottom", of: link }, dialogdiv);
	});

	// create the popup PDF printing dialog
	$('<div />').attr('id','pdfPrintContainer').attr('title',"Save to PDF").hide().appendTo('body');
	var h = '';
	h += '<label><input type="checkbox" name="show_form_fields" value="1" checked="checked" />Show Data Form (left panel)</label><br />';
	h += '<label><input style="margin-left:30px;" type="checkbox" name="show_text_fields" value="1" checked="checked" />Show Text and Photos</label><br />';
	h += '<label><input type="checkbox" name="show_event_stream" value="1" checked="checked" />Show Event Stream (right panel)</label><br />';
	h += '<label><input type="checkbox" name="nested_tree_view" value="1"/>Full Nested Tree View (all pdfs in one)</label><br />';
	$('#pdfPrintContainer').html(h);
	$('#pdfPrintContainer input[name="show_form_fields"]').click(function(){
		$('#pdfPrintContainer input[name="show_text_fields"]').prop("disabled", !$('#pdfPrintContainer input[name="show_form_fields"]:checked').val());
	});

	// now connect the on click handler that will override the normal link
	$('#pdfButton').click(function(link) {
		var contentdiv = $('#pdfPrintContainer');
		pdfdialogdiv = contentdiv.dialog({
			position: { my: "left top", at: "right bottom", of: link },
			width: 300,
			height: 'auto',
			buttons: {
				"OK": function() {
					var filledUrl = pdfViewUrl;
					filledUrl = filledUrl.replace('_SHOWFORM_',$('#pdfPrintContainer input[name="show_form_fields"]:checked').val() ? '1' : '0');
					filledUrl = filledUrl.replace('_SHOWTEXT_',$('#pdfPrintContainer input[name="show_text_fields"]:checked').val() ? '1' : '0');
					filledUrl = filledUrl.replace('_SHOWEVENTS_',$('#pdfPrintContainer input[name="show_event_stream"]:checked').val() ? '1' : '0');
					filledUrl = filledUrl.replace('_NESTEDVIEW_',$('#pdfPrintContainer input[name="nested_tree_view"]:checked').val() ? '1' : '0');
					window.open(filledUrl, '_blank');
					$( this ).dialog( "close" );
				},
				Cancel: function() {
					$( this ).dialog( "close" );
				}
			},
			close: function(event,ui) {$(this).dialog('destroy');}
		});
		return false; // prevents the default link
	});

	activatefollowButton(followUrl, "Watch this item...", "If you want to watch all items of this type, click Watch from the definition page.",false, false, changeCodesListing);
	activateLinkToPageButton('#linkToPageButton', lookupUrl, linkToPageUrl, layoutTitle, canSendLink);

	$("#monthsHistoryId").selectmenu({
	      change: function( event, data ) {
	  		document.theform.btnOnChange.value='monthschange';
			document.theform.submit();
			return false;
	      }
	 });
	 $(".showAllHistoryButton").on("click", function() {
		document.theform.btnOnChange.value='monthschange';
		$("#monthsHistoryId").val("ALL");
		document.theform.submit();
		return false;
	 });

});

