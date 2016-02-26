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

