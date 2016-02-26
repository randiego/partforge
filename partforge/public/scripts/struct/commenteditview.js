$(function () {
    'use strict';

    // Initialize the jQuery File Upload widget:
    $('#fileupload').fileupload({
        // Uncomment the following to send cross-domain cookies:
        //xhrFields: {withCredentials: true},
    	autoUpload: true,
        url: baseUrl + '/struct/documentsajax/'
    });

    // Enable iframe cross-domain access via redirect option:
    $('#fileupload').fileupload(
        'option',
        'redirect',
         baseUrl + '/jqueryextras/jquery-file-upload/cors/result.html?%s'
    );

    // Load existing files:
    $.ajax({
        // Uncomment the following to send cross-domain cookies:
        //xhrFields: {withCredentials: true},
        url: $('#fileupload').fileupload('option', 'url'),
        dataType: 'json',
        context: $('#fileupload')[0]
    }).done(function (result) {
        $(this).fileupload('option', 'done')
            .call(this, null, {result: result});
    });

    $('#CancelBtnID').click(function() {
    	var numAttached = $('#fileupload .files tr td.name').length;
    	if ( ($('input[name="comment_id"]').val()=='new') && ($('#fileupload .files tr td.name').length > 0)) {
    		return confirm('Your attachments ('+numAttached+' items) will be lost if you continue.  Are you sure you want to continue?');
    	}
    	return true;
    });
    
	if ($('input[type="file"]').is(':disabled')) {
		$('<p class="errorred">File Uploads might not<br /> work in this browser.</p>').appendTo(".fileupload-buttonbar");
	}

});