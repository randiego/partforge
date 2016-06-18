/*
  Bootstrap JS for all application pages
*/

//this function includes all necessary js files for the application

var a_pop_dialogdiv_mutex = null;

function include(file)
{

  var script  = document.createElement('script');
  script.src  = file;
  script.type = 'text/javascript';
  script.defer = true;

  document.getElementsByTagName('head').item(0).appendChild(script);

}

function timeSelectHtmlForWatches(selectTagId, classId, timevalueHHMM) {
	var keyValueArray = [['00:00','00:00'],['01:00','01:00'],['02:00','02:00'],['03:00','03:00'],['04:00','04:00'],['05:00','05:00'],['06:00','06:00'],['07:00','07:00'],['08:00','08:00'],['09:00','09:00'],
	             ['10:00','10:00'],['11:00','11:00'],['12:00','12:00'],['13:00','13:00'],['14:00','14:00'],['15:00','15:00'],
	             ['16:00','16:00'],['17:00','17:00'],['18:00','18:00'],['19:00','19:00'],['20:00','20:00'],['21:00','21:00'],['22:00','22:00'],['23:00','23:00']];
	var component_value = timevalueHHMM;
	
	// generic select HTML processing
	var html = "";
	var selected;
	html += '<select id="'+selectTagId+'" class="'+classId+'">';
	for(var i=0; i<keyValueArray.length; i++) {
		var key = keyValueArray[i][0];
		var val = keyValueArray[i][1];
		selected = (key == component_value) ? " selected=selected" : "";
		html += '<option value="'+key+'"'+selected+'>'+val+'</option>';
	}
	html += '</select>';
	return html;
}

/**
 * Used whereever a followButton id is located to construct dialog and add click handler to.  
 * @param followUrl string url with constants _FOLLOWNOTIFYTIMEHHMM_, _NOTIFYINSTANTLY_, _NOTIFYDAILY_ to be substituted with the form results
 */
function activatefollowButton(followUrl,footnote_text) {
	if ($('#followButton').length) {
		// create the popup follow dialog
		$('<div />').attr('id','followDialogContainer').attr('title',"When something changes...").hide().appendTo('body');
		var h = '';
		h += '<label><input type="checkbox" name="notify_instantly" value="1" '+(followInstantly==1 ? 'checked="checked"' : '')+' />Email Me Instantly</label><br />';
		h += '<label><input type="checkbox" name="notify_daily" value="1" '+(followDaily==1 ? 'checked="checked"' : '')+' />Send Me a Daily Summary at </label>'+timeSelectHtmlForWatches('timevalueHHMM', '', followNotifyTimeHHMM)+'<br /><div style="margin-left: 20px;"><span class="paren">(time is same for all your daily watches.)</span></div>';
		h += '<label><input type="checkbox" name="no_notify" value="1" checked="checked" disabled="disabled" />Show on my Watchlist (Activity Tab)</label><br />';
		if (footnote_text!='') h += '<div style="margin-top:10px;"><span class="paren">'+footnote_text+'</span></div>';
		if (followNotifyEmailMsg!='') h += '<div style="margin-top:10px;"><span class="paren_red">Please fix the following problem before you can receive notifications: '+followNotifyEmailMsg+'</span></div>';
		$('#followDialogContainer').html(h);
		// now connect the on click handler that will override the normal link
		$('#followButton').click(function(link) {
			var contentdiv = $('#followDialogContainer');
			pdfdialogdiv = contentdiv.dialog({
				position: { my: "left top", at: "right bottom", of: link },
				width: 300,
				height: 'auto',
				buttons: {
					"OK": function() {
						var filledUrl = followUrl;
						filledUrl = filledUrl.replace('_FOLLOWNOTIFYTIMEHHMM_',$('#timevalueHHMM').val());
						filledUrl = filledUrl.replace('_NOTIFYINSTANTLY_',$('#followDialogContainer input[name="notify_instantly"]:checked').val() ? '1' : '0');
						filledUrl = filledUrl.replace('_NOTIFYDAILY_',$('#followDialogContainer input[name="notify_daily"]:checked').val() ? '1' : '0');
						window.location.href = filledUrl;
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
}


/* include any js files here */

$(document).ready(function() {
    $('.last_select, .bd-event-row.event_afterglow_c .bd-event-content').animate({
      backgroundColor: "#FFF"
    }, 5000, function() {
      // Animation complete.
    });
    
    $('.bd-event-row.event_afterglow_r .bd-event-content').animate({
        backgroundColor: "#EEEEFF"
      }, 5000, function() {
        // Animation complete.
    });    
    
    $("input.jq_datepicker").datepicker({
    });
    
    $("input.jq_datetimepicker").datetimepicker({
    });    
    
    // autocomplete is really distracting with the date time picker
    $("input.jq_datetimepicker, input.jq_datepicker").attr('autocomplete','off');


	if ( $('table.edittable th span.req_field').length > 0) {
		$("p.req_field_para").show();
	}

	if ( $('table.edittable th span.locked_field').length > 0) {
		$("p.locked_field_para").show();
	}
	
	// ajax call to dismis a particular what's new dialog
    $(".ok_i_got_it").click( function (event) {
    	element = $(this).closest('div.whats_new');
    	var dataKey = $(this).attr('data-key');
	    $.getJSON(baseUrl+'/struct/tipsokigotit',
		{key : dataKey},
		function(data) {
			if (data['ok']==1) {
				element.hide();
			}
		})
	});	
    
    // do this automatically for everything.
	$('.a_pop_link').click(function(link){
		var contentdiv = $(this).next();
		if (a_pop_dialogdiv_mutex!==null) return false;
		a_pop_dialogdiv_mutex = contentdiv.dialog({
			position: { my: "left top", at: "right bottom", of: link },
			width: 600,
			height: 'auto',
			close: function(event,ui) {$(this).dialog('destroy'); a_pop_dialogdiv_mutex = null;}
		});
	}); 
    
    
});



$.fn.scrollTo = function( target, options, callback ){
	  if(typeof options == 'function' && arguments.length == 2){ callback = options; options = target; }
	  var settings = $.extend({
	    scrollTarget  : target,
	    offsetTop     : 50,
	    duration      : 500,
	    easing        : 'swing'
	  }, options);
	  return this.each(function(){
	    var scrollPane = $(this);
	    var scrollTarget = (typeof settings.scrollTarget == "number") ? settings.scrollTarget : $(settings.scrollTarget);
	    var scrollY = (typeof scrollTarget == "number") ? scrollTarget : scrollTarget.offset().top + scrollPane.scrollTop() - parseInt(settings.offsetTop);
	    scrollPane.animate({scrollTop : scrollY }, parseInt(settings.duration), settings.easing, function(){
	      if (typeof callback == 'function') { callback.call(this); }
	    });
	  });
	}

