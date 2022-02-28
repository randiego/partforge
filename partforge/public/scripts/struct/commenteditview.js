function startQRUploadPolling() {
	$.getJSON(baseUrl + '/struct/qruploadrefresh',
    {qruploadkey : $('input[name="qruploadkey_value"]').val()},
    function(data) {
        var status = (typeof data['status'] == 'undefined') ? "" : data['status'];
        if (status=="changed") {
            $('#updatebannerId').html('Updating...').fadeIn();
            loadExistingFiles();
        } else if (status=="connected") {
            $('#updatebannerId').html('Connected.').fadeIn();
        } else if (status=="reload") {
            $('#theform').submit();
        } else {
            $('#updatebannerId').fadeOut();
        }
    });
    setTimeout( 'startQRUploadPolling()', 5000);
};

function loadExistingFiles()
{
    $.ajax({
        // Uncomment the following to send cross-domain cookies:
        //xhrFields: {withCredentials: true},
        url: $('#fileupload').fileupload('option', 'url'),
        dataType: 'json',
        context: $('#fileupload')[0]
    }).done(function (result) {
        $('#fileupload table.table-striped tbody').empty();
        $(this).fileupload('option', 'done')
            .call(this, null, {result: result});
    });
}

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
    if ($('input[name="qruploadkey_value"]').val()) {
        startQRUploadPolling();
        $('#qrcode').qrcode(qrUploadUrl);
    }

    function split( val ) {
      return val.split( /,\s*/ );
    }
    function extractLast( term ) {
      return split( term ).pop();
    }

    $("#send_to_names")
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
                $.getJSON(lookupUrl, {
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

    // for most input fields, this makes the Enter key move to the next field.
    $('body').on('keydown', 'input, select', function (e) {
        if (e.which == 13) {
            // if this is a submit button, then do the normal behavior (submit the form)
            if ($(this).filter('input[type="submit"').length) {
                return true;
            }

            var form = $('form[name=theform]');
            var focusable = form.find('input,select,button,textarea').filter(':visible');
            var next = focusable.eq(focusable.index(this) + 1);
            if (next.length) {
                next.focus();
            }
            return false;
        }
    });
});
