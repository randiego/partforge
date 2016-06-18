

$(document).ready(function() {
	// ajax call to create a comment.
    $("#commentSendButtonId").click( function (event) {

        $(this).fadeTo("fast", .5).unbind('click');
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
		$('#pdfButton').click(function(link) {
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
    	
	activatefollowButton(followUrl,"");
    
	/*
    $('#followButton').button( "option", "icons", { primary: "ui-icon-signal-diag"} );
    $('#previewButton').button( "option", "icons", { primary: "ui-icon-zoomin"} );
    $('#moveComponentsButton').button( "option", "icons", { primary: "ui-icon-wrench"} );
    $('#makeActiveButton').button( "option", "icons", { primary: "ui-icon-play"} );
    $('#makeObsoleteButton').button( "option", "icons", { primary: "ui-icon-cancel"} );
    */
    
    
});