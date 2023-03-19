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

$(function() {
	// ajax call to create a comment.
    $("#commentSendButtonId").on("click", function (event) {

        $(this).fadeTo("fast", .5).off('click');
	    $.getJSON(baseUrl + '/struct/addcommentitem',
		{"typeobject_id" : typeObjectId,
		 "comment_text" : $("#commentTextId").val()},
		function(data) {
			window.location = thisDefinitionViewUrl;
		});
		return false;
	});

    // we only offer the options dialog if this is a part with linked procedures (ID=linkedProcedureUL exists)
    if (isAPart && ($('#linkedProcedureUL').length>0)) {
		// create the popup PDF printing dialog
		$('<div />').attr('id','pdfPrintContainer').attr('title',"Save to PDF").hide().appendTo('body');
		var h = '';
		h += '<label><input type="checkbox" name="show_linked_procs" value="1" checked="checked" />Include Linked Procedures</label><br />';
		$('#pdfPrintContainer').html(h);

		// now connect the on click handler that will override the normal link
		$('#pdfButton').on("click", function(link) {
			var contentdiv = $('#pdfPrintContainer');
			pdfdialogdiv = contentdiv.dialog({
				position: { my: "left top", at: "right bottom", of: link },
				width: 300,
				height: 'auto',
				buttons: {
					"OK": function() {
						var filledUrl = pdfViewUrl;
						filledUrl = filledUrl.replace('_LINKEDPROCS_',$('#pdfPrintContainer input[name="show_linked_procs"]:checked').val() ? '1' : '0');
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
    }

	activatefollowButton(followUrl,"",true, changeCodesListing);
	activateLinkToPageButton('#linkToPageButton', lookupUrl, linkToPageUrl, layoutTitle, canSendLink);
	initPanelScrollSaver();

	$(".startSortLink").on("click", function() {
		$(".startSortLink").hide();
		$( "#linkedProcedureUL" ).sortable();
		$( ".sortingNoticeBanner").show();
		$( "#linkedProcedureUL" ).addClass('bd-sorting');
		var sortingNoticeBannerTxt = 'Drag and drop the fields below, then click "Done Moving"';
		var html = "";
		html += '<div class="sortingNoticeBanner" style="margin-top: 15px; margin-bottom:5px;">'+sortingNoticeBannerTxt+'</div>';
		html += '<div class="sortNoticeButtons"><span> <a href="#" class="bd-linkbtn saveNewProcedureSortOrder" title="save new procedure sort order">Done Moving</a></span>';
		html +=	'<span><a href="#" class="bd-linkbtn cancelProcdureSortOrder" title="cancel changes">Cancel</a></span>';
		html +=	'<span><a href="#" class="bd-linkbtn alphabeticalSortOrder" title="revert to alphabetical sort order">Revert to Alphabetical</a></span></div>';
		$(html).insertBefore("#linkedProcedureUL");
		$("#linkedProcedureUL a").css({"color":"#000", cursor: "default"}).click(function(e) {
			e.preventDefault();
		});

		$(".saveNewProcedureSortOrder").on("click", function(event) {
			toids = [];
			$("#linkedProcedureUL").children("li").each(function(idx,elm) {
				var typeobject_id = elm.id.split('typeobjectli_')[1];
				toids.push(typeobject_id);
			});
			$.getJSON(baseUrl+'/struct/reorderprocedures',
				{when_viewed_by_typeobject_id : typeObjectId,
					of_typeobject_ids : toids.join(',')},
				function(data) {
					if (data['error']) {
						alert('Error: ' + data['error']);
					} else {
						window.location = thisDefinitionViewUrl;

					}
				}
			);
			return false;
		});
		$(".alphabeticalSortOrder").on("click", function(event) {
			$.getJSON(baseUrl+'/struct/reorderprocedures',
				{when_viewed_by_typeobject_id : typeObjectId,
					of_typeobject_ids : ''},
				function(data) {
					if (data['error']) {
						alert('Error: ' + data['error']);
					} else {
						window.location = thisDefinitionViewUrl;

					}
				}
			);
			return false;
		});
		$(".cancelProcdureSortOrder").on("click", function(event) {
			window.location = thisDefinitionViewUrl;
			return false;
		});

	});


});
