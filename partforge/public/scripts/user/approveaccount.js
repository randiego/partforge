function displayRefresh() {
	if ($("#send_welcome_email_1").is(":checked")) {
		$('#email').closest("tr").show();
		$('textarea[name="message"]').closest("tr").show();
	} else {
		$('#email').closest("tr").hide();
		$('textarea[name="message"]').closest("tr").hide();
	}	
}

$(document).ready(function() {
	displayRefresh();
	$("input[name='send_welcome_email']").click(function(){
		displayRefresh();
	});
	var msgfont = $('textarea[name="message"]').css('font-family');
	$('textarea[name="message"]').closest("td").css('font-family',msgfont);
});
