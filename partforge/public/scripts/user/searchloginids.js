function formSubmit() {
	return $('form[name=theform]').submit();
}


$(document).ready(function() {
	// autocomplete name select
	$( "#full_name" ).autocomplete({
		source: lookupUrl,
		minLength: 1,
		focus: function( event, ui ) {event.preventDefault();},
		select: function( event, ui ) {
			if (ui.item) {
				 $('input[name="login_id"]').val(ui.item.value);
				 formSubmit();
			}
		}
	}).watermark('start typing Last Name...');	
});
