function displayRefresh() {
	// show the password confirm only if we are hiding the password
	if ($("input[name='show_password']").is(":checked")) {
		$("input[name='password']").attr('type','text');
		$('#password2').closest("tr").hide();
	} else {
		$("input[name='password']").attr('type','password');
		$("input[name='password2']").attr('type','password');
		$('#password2').closest("tr").show();
	}
	
	if ($("#email_password_1").is(":checked")) {
		$('#email').closest("tr").show();
		$('textarea[name="message"]').closest("tr").show();
	} else {
		$('#email').closest("tr").hide();
		$('textarea[name="message"]').closest("tr").hide();
	}	
}

$(document).ready(function() {
	displayRefresh();
	$("input[name='show_password']").click(function(){
		displayRefresh();
	});
	$("input[name='email_password']").click(function(){
		displayRefresh();
	});
	var msgfont = $('textarea[name="message"]').css('font-family');
	$('textarea[name="message"]').closest("td").css('font-family',msgfont);
});
