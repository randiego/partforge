

$(function () {
    'use strict';

    // Initialize the jQuery File Upload widget:
    $('#fileupload').fileupload({
        // Uncomment the following to send cross-domain cookies:
        //xhrFields: {withCredentials: true},
    	autoUpload: true,
        url: baseUrl + '/utils/qrupload/' + uploadKey + '?initialized=&ajaxaction='
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

	if ($('input[type="file"]').is(':disabled')) {
		$('<p class="errorred">File Uploads might not<br /> work in this browser.</p>').appendTo(".fileupload-buttonbar");
	}

    $('#qruploadclosebutton').on("click",function(event) {
        // close the connection then redirect
        $.getJSON(baseUrl + '/utils/qrupload/' + uploadKey + '?initialized=&ajaxaction=&close_connection=',
        {},
        function(data) {
            //location.reload();
            window.close();
        });

    });

});
