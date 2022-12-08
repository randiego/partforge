
function sessionTimeoutAction() {
    document.theform.form_action.value='emergencysave';formSubmit();
}

function formSubmit() {
	return $('form[name=theform]').submit();
}

function clear_field(fname) {
	$('input[name="'+fname+'"]').filter('input[type="radio"]').prop('checked', false);
}

function set_bool_clearer() {
	$('a.boolean_clearer').each(function(){
		var fname = $(this).data('fname');
		var radioschecked = $('input[name="'+fname+'"]').filter('input[type="radio"]:checked').length > 0;
		if (radioschecked) {
			$(this).show();
		} else {
			$(this).hide();
		}
	});
	setTimeout('set_bool_clearer()',1000);
}

$(document).ready(function() {

	$('td > select.inputboxclass.compsel').parent().addClass('compsel');
	$('select.inputboxclass.compsel').comboboxjumper({hidecurrentvaluewhenchanging: 0, allowempty : true});

	// refresh the display whenever the effective_date changes. This is
	// needed because the selection
	// boxes will have different options depending on the effective_date
	$('input[name="effective_date"]').attr('autocomplete','off').datetimepicker({
		onClose: function(dateText,inst) {
			document.theform.btnOnChange.value='effectivedatechange';document.theform.submit();return false;
        }
    });

	// ajax call to get the next available serial number for this
	// typeversion_id.
    $("#serialNumberNew").click( function (event) {
	    $.getJSON(baseUrl+'/struct/nextserialnumber',
		{typeversion_id : thisTypeVersion},
		function(data) {
			$('input[name="item_serial_number"]').val(data["next_serial_number"]);
		})
	});

	$("#effectiveDateNow").click( function(event) {
	    $.getJSON(baseUrl+'/struct/getdatetimenow',
	    		{typeversion_id : thisTypeVersion},
	    		function(data) {
	    			$('input[name="effective_date"]').val(data["datetimenow"]);
	    			$('input[name="btnOnChange"]').val('effectivedatechange');
	    			$('form').submit();
	    })
	});

	// Set the focus to the first field that is not one of the known reloaders.  This gives us a fighting chance to do mouse free submission.
	$("#theform :input:visible:enabled").not("input[name='effective_date']").not("select[name='typeversion_id']").first().focus();

	// for most input fields, this makes the Enter key move to the next field.
	$('body').on('keydown', 'input, select', function(e) {
	    if (e.which == 13) {
	    	// if this is a submit button, then do the normal behavior (submit the form)
	    	if ($(this).filter('input[type="submit"').length) {
	    		return true;
	    	}

	        var form = $('form[name=theform]');
	        var focusable = form.find('input,select,button,textarea').filter(':visible');
	        var next = focusable.eq(focusable.index(this)+1);
	        if (next.length) {
	            next.focus();
	        }
	        return false;
	    }
	});

	// autosave
	setTimeout('sessionTimeoutAction()', sessionTimeout);
	set_bool_clearer();  // starts looping


});
