
function tickleupdatebanner() {
	$('#updatebannerId').fadeIn().fadeOut(1000);
}

$(document).ready(function() {
	
	$('.notifyInstantly').change( function (event) {
		var key = $(this).attr('data-key');
		var ischecked = $(this).prop("checked") ? 1 : 0;
		var thiscontrol = $(this);
		thiscontrol.parent().attr("class","checkbox_pending");
	    $.getJSON(baseUrl+'/struct/setsubscriptionnotify',
			{changesubscription_id : key, notify_instantly: ischecked},
			function(data) {
				tickleupdatebanner();
				thiscontrol.parent().removeClass("checkbox_pending");
				thiscontrol.prop("checked",data["notify_instantly"]);
			})
	});
	
	$('.notifyDaily').change( function (event) {
		var key = $(this).attr('data-key');
		var ischecked = $(this).prop("checked") ? 1 : 0;
		var thiscontrol = $(this);
		thiscontrol.parent().attr("class","checkbox_pending");
	    $.getJSON(baseUrl+'/struct/setsubscriptionnotify',
			{changesubscription_id : key, notify_daily: ischecked},
			function(data) {
				tickleupdatebanner();
				thiscontrol.parent().removeClass("checkbox_pending");
				thiscontrol.prop("checked",data["notify_daily"]);
			})
	});	
	
	$('#followNotifyTimeHHMMId').change( function() {
		var thiscontrol = $(this);
	    $.getJSON(baseUrl+'/struct/watchlistview',
				{"form" : "", "btnSetDailyNotifyTime" : "", followNotifyTimeHHMM : thiscontrol.val()},
				function(data){tickleupdatebanner()})
			.fail(function() {alert('Something wrong.  Try again or refresh your browser.');});
	});
	
    
});

