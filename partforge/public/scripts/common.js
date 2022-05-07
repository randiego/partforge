function set_default_focus() {
	$("#theform :input:visible:enabled:first").focus();
}

/*
    Scroll Postion Saver Logic: Randy Black - Apr 2009
    To use this, put initScrollSaver("key") in body onload for the page you want to remember the scroll postion in.
    This call will place a marker in cookies that stores the scroll positions using three identifiers:
    "key" value, page view, and pageno.  Upon returning to this page, a call to initScrollSaver("key")
    will see if the page view and pageno are the same as the last visit.  If so, the scroll position will
    be set to the value that existed when we were last on this page.  The page view is the query parameter "view".
    If that is not present, then the none-query part of the url is used instead (for Zend).  The pageno is also
    a query variable.
*/

var docscrollkeyname = "";

function formDataPageNo() {
	return ((FORM_DATA['pageno']==undefined) || FORM_DATA['pageno']=='') ? '' : FORM_DATA['pageno'];
}

function currentViewName() {
    return (typeof(FORM_DATA['view'])=='undefined') ? FORM_DATA['baselocation'] : FORM_DATA['view'];
}

function OnScrollPosSave() {
	document.cookie = "docscrollx" + docscrollkeyname + "=" + getScrollX() + "; SameSite=Lax";
	document.cookie = "docscrolly" + docscrollkeyname + "=" + getScrollY() + "; SameSite=Lax";
	document.cookie = "docscrollview" + docscrollkeyname + "=" + currentViewName() + "; SameSite=Lax";
	document.cookie = "docscrollpageno" + docscrollkeyname + "=" + formDataPageNo() + "; SameSite=Lax";
}

function scrollToCookiePositon() {
	window.scrollTo(cookies['docscrollx'+docscrollkeyname],cookies['docscrolly'+docscrollkeyname]);
}

function scrollToTopPosition() {
	window.scrollTo(0,0);
}

function initScrollSaver(keyname) {
	docscrollkeyname = keyname;
	document.cookie = "arecookiesenabled=yes; SameSite=Lax";
	extractCookies();
	if ((cookies['docscrollview'+docscrollkeyname] == currentViewName()) && (FORM_DATA['resetview']!="1") && (cookies['docscrollpageno'+docscrollkeyname] == formDataPageNo())) {
	    window.setTimeout("scrollToCookiePositon()",1); // needed to do this so the setfocus message didn't come after this.
	} else {
            window.setTimeout("scrollToTopPosition()",1);  // sometimes the setfocus scrolls the top out of view.  This beings it back
        }
	if (cookies['arecookiesenabled'] == 'yes') {
	    setInterval("OnScrollPosSave()",500);
	}
}

function getScrollY() {
	if (window.pageYOffset)
	{
	  	pos = window.pageYOffset
	}
	else if (document.documentElement && document.documentElement.scrollTop)
	{
		pos = document.documentElement.scrollTop
	}
	else if (document.body)
	{
	  pos = document.body.scrollTop
	}
	return pos;
}

function getScrollX() {
	if (window.pageXOffset)
	{
	  	pos = window.pageXOffset
	}
	else if (document.documentElement && document.documentElement.scrollTop)
	{
		pos = document.documentElement.scrollLeft
	}
	else if (document.body)
	{
	  pos = document.body.scrollLeft
	}
	return pos;
}




function MM_jumpMenu(targ,selObj,restore){ //v3.0
  eval(targ+".location='"+selObj.options[selObj.selectedIndex].value+"'");
  if (restore) selObj.selectedIndex=0;
}

function jumpToValueInBox(targ,formObject){
  eval(targ+".location='"+formObject.value+"'");
}

/**  modified from phpMyAdmin
 * marks all rows and selects its first checkbox inside the given element
 * the given element is usaly a table or a div containing the table or tables
 *
 * @param    container    DOM element
 */
function markAllRows( container_id ) {
	var rows = document.getElementById(container_id).getElementsByTagName('tr');
    var checkbox;

	for ( var i = 0; i < rows.length; i++ ) {

        checkbox = rows[i].getElementsByTagName( 'input' )[0];

        if ( checkbox && checkbox.type == 'checkbox' ) {
			unique_id = checkbox.name + checkbox.value;
            if ( checkbox.disabled == false ) {
                checkbox.checked = true;
                if ( typeof(marked_row[unique_id]) == 'undefined' || !marked_row[unique_id] ) {
                    rows[i].className += ' marked';
                    marked_row[unique_id] = true;
                }
	            }
	    }
	}

	return true;
}

/**
 * marks all rows and selects its first checkbox inside the given element
 * the given element is usaly a table or a div containing the table or tables
 *
 * @param    container    DOM element
 */
function unMarkAllRows( container_id ) {
	var rows = document.getElementById(container_id).getElementsByTagName('tr');
    var checkbox;

	for ( var i = 0; i < rows.length; i++ ) {

        checkbox = rows[i].getElementsByTagName( 'input' )[0];

        if ( checkbox && checkbox.type == 'checkbox' ) {
            unique_id = checkbox.name + checkbox.value;
            checkbox.checked = false;
            rows[i].className = rows[i].className.replace(' marked', '');
            marked_row[unique_id] = false;
        }
	}

	return true;
}


/**
 * This array is used to remember mark status of rows in browse mode
 */
var marked_row = new Array;
var focused_textbox = new Array;

/** Copied directly from phpMyAdmin.  This file is subject to the license terms and conditions of phpMyAdmin distribution.
 * enables highlight and marking of rows in data tables
 *
 * each tr should be of class odd or even.  In IE, must also define "hover" and "marked" second classes.
 *
 */
function attachCheckListTableHandlers() {
	var tables = document.getElementsByTagName('table');
	for ( var i = 0; i < tables.length; i++ ) {
		if ('checklisttable' == tables[i].className) {
			markRowsInit( tables[i] );
		}
	}
}

function rowMouseDownFunction(thisEl) {
    var unique_id;
    var checkbox;
    var checkbox_found = false;
    var textinput;
    var textinput_found = false;
    var inputs = thisEl.getElementsByTagName( 'input' );
    for ( var i=0; i<inputs.length; i++ ) {
	if (inputs[i].type == 'checkbox') {
		checkbox = inputs[i];
		checkbox_found = true;
		break;
	}
    }
    if (!checkbox_found) {
	    return;
    }
    for ( var i=0; i<inputs.length; i++ ) {
	if (inputs[i].type == 'text') {
		textinput = inputs[i];
		textinput_found = true;
		break;
	}
    }

    unique_id = checkbox.name + checkbox.value;

    if ( typeof(marked_row[unique_id]) == 'undefined' || !marked_row[unique_id] ) {
	marked_row[unique_id] = true;
    } else {
	if (textinput_found && (typeof(focused_textbox[textinput.name]) != 'undefined') && focused_textbox[textinput.name]) {
		// don't turn it off if we're sitting in an input box
		return;
	} else {
		marked_row[unique_id] = false;
	}
    }

    if ( marked_row[unique_id] ) {
	thisEl.className += ' marked';
    } else {
	thisEl.className = thisEl.className.replace(' marked', '');
    }

    if ( checkbox && checkbox.disabled == false ) {
	checkbox.checked = marked_row[unique_id];
    }
}

function markRowsInit( elementname ) {
    var unique_id;
    // for every table row ...
    var rows = elementname.getElementsByTagName('tr');
    for ( var i = 0; i < rows.length; i++ ) {
	// ... with the class 'odd' or 'even' ...
	    if ( 'odd' != rows[i].className && 'even' != rows[i].className ) {
		continue;
	    }
	// ... add event listeners ...
    // ... to highlight the row on mouseover ...
	if ( navigator.appName == 'Microsoft Internet Explorer' ) {
	    // but only for IE, other browsers are handled by :hover in css
	    rows[i].onmouseover = function() {
		this.className += ' hover';
	    }
	    rows[i].onmouseout = function() {
		this.className = this.className.replace( ' hover', '' );
	    }
	}
    // ... and to mark the row on click ...
	    rows[i].onmousedown = function() {
		    rowMouseDownFunction(this);
	    }

	    // ... and disable label ...
	    var labeltag = rows[i].getElementsByTagName('label')[0];
	    if ( labeltag ) {
		labeltag.onclick = function() {
		    return false;
		}
	}
	// .. and checkbox clicks
	    var inputtags = rows[i].getElementsByTagName('input');
	    for ( var j = 0; j < inputtags.length; j++ ) {
		    if ( inputtags[j] && inputtags[j].type == 'checkbox' ) {
			    inputtags[j].onclick = function() {
				    // opera does not recognize return false;
				    this.checked = ! this.checked;
			    }

			    // set marked_row array if items are checked already
			    if (inputtags[j].checked) {
				    unique_id = inputtags[j].name + inputtags[j].value;
				    marked_row[unique_id] = true;
				    rows[i].className += ' marked';
			    }
		    }

		    if ( inputtags[j] && inputtags[j].type == 'text' ) {
			    inputtags[j].onmousedown = function() {
				    focused_textbox[this.name] = true;
			    }
			    inputtags[j].onblur = function() {
				    focused_textbox[this.name] = false;
			    }
		    }
	    }

	    // find all <a> tags and insert extra row click if button is pressed so it will cancel the other click.
	    var atags = rows[i].getElementsByTagName('a');
	    for (var j = 0; j <atags.length; j++) {
		    if (atags[j].onclick != null && atags[j].onclick != "undefined") {
			    var strOnClick = atags[j].onclick.toString();
			    var strNewOnClick = strOnClick.substring(strOnClick.indexOf("{")+1,strOnClick.length -2);  // get stuff between {}
		    } else {
			    var strNewOnClick = "";
		    }
		    var strInsertOnClick = 'clickParentTR(this); ';
		    atags[j].onclick = new Function(strInsertOnClick + strNewOnClick);
	    }
    }
}

function isNodeMyTag(myNode, myTag) {
	return (myNode.nodeType==1 && myNode.nodeName==myTag);
}

function findParentTag(thisElement, whatTag) {
    var el = thisElement;
    var	found = false;
    for(var i=0; i <10; i++) {
	if (isNodeMyTag(el, "BODY")) {
		break;
	} else if (isNodeMyTag(el, whatTag)) {
		found = true;
		break;
	}
	el = el.parentNode;
    }
    if (found) {
	    return el;
    } else {
	    return null;
    }
}

function clickParentTR(thisElement) {
	var el = findParentTag(thisElement, "TR");
	if (el != null) {
		rowMouseDownFunction(el);
	}
}

function open_win_named(what_link,win_name,xsize,ysize){
    var the_url = "http://localhost"
    var the_x = 500;
    var the_y = 600;
    if(typeof(xsize)=='number') {the_x=xsize;}
    if(typeof(ysize)=='number') {the_y=ysize;}
    the_x -= 0;
    the_y -= 0;
    var how_wide = screen.availWidth;
    var how_high = screen.availHeight;
    if(what_link != ""){the_url=what_link;}
    var the_toolbar = "no";
    var the_addressbar = "no";
    var the_directories = "no";
    var the_statusbar = "no";
    var the_menubar = "no";
    var the_scrollbars = "yes";
    var the_do_resize =  "yes";
    var the_copy_history = "no";
    top_pos = 0;
    left_pos = 0;
    if (window.outerWidth ){
        var option = "toolbar="+the_toolbar+",location="+the_addressbar+",directories="+the_directories+",status="+the_statusbar+",menubar="+the_menubar+",scrollbars="+the_scrollbars+",resizable="+the_do_resize+",outerWidth="+the_x+",outerHeight="+the_y+",copyhistory="+the_copy_history+",left="+left_pos+",top="+top_pos;
        site=open(the_url, win_name, option);
        var Opera = (navigator.userAgent.indexOf('Opera') != -1);
        if(Opera){
            site.resizeTo(the_x,the_y);
            site.moveTo(0,0);
        }
    }
    else
    {
        var option = "toolbar="+the_toolbar+",location="+the_addressbar+",directories="+the_directories+",status="+the_statusbar+",menubar="+the_menubar+",scrollbars="+the_scrollbars+",resizable="+the_do_resize+",Width="+the_x+",Height="+the_y+",copyhistory="+the_copy_history+",left="+left_pos+",top="+top_pos;
        site=open('', win_name, option);
        site.location=the_url;
        if(site.open){site.focus();return false;}
        site.resizeTo(the_x,the_y);
    }
    site.focus();
}

function verifyCorrectWindow(winname,force_yes_no) {
// force_yes_no == true means it must match.  false means it must not match
    if ((!force_yes_no && (winname == window.name)) || (force_yes_no && (winname != window.name))) {
        alert('Your browser session may have timed-out.  Please continue your work in the main browser window.  If problem persists, open a fresh browser window.');
        window.close();
    }
}

/*
Webmonkey GET Parsing Module
Language: JavaScript 1.0
The parsing of GET queries is fundamental
to the basic functionality of HTTP/1.0.
This module parses GET with JavaScript 1.0.
Source: Webmonkey Code Library
(http://www.hotwired.com/webmonkey/javascript/code_library/)
Author: Patrick Corcoran
Author Email: patrick@taylor.org
*/


function createRequestObject() {
  FORM_DATA = new Object();
    // The Object ("Array") where our data will be stored.
  separator = ',';
    // The token used to separate data from multi-select inputs
  query = '' + this.location;
  qu = query
    // Get the current URL so we can parse out the data.
    // Adding a null-string '' forces an implicit type cast
    // from property to string, for NS2 compatibility.
  var baselocation = (query.indexOf('?')==-1) ? query : query.substring(0, query.indexOf('?'));
  query = query.substring((query.indexOf('?')) + 1);
    // Keep everything after the question mark '?'.
  if (query.length < 1) { return false; }  // Perhaps we got some bad data?
  keypairs = new Object();
  numKP = 1;
    // Local vars used to store and keep track of name/value pairs
    // as we parse them back into a usable form.
  while (query.indexOf('&') > -1) {
    keypairs[numKP] = query.substring(0,query.indexOf('&'));
    query = query.substring((query.indexOf('&')) + 1);
    numKP++;
      // Split the query string at each '&', storing the left-hand side
      // of the split in a new keypairs[] holder, and chopping the query
      // so that it gets the value of the right-hand string.
  }
  keypairs[numKP] = query;
    // Store what's left in the query string as the final keypairs[] data.<
  for (i in keypairs) {
    keyName = keypairs[i].substring(0,keypairs[i].indexOf('='));
      // Left of '=' is name.
    keyValue = keypairs[i].substring((keypairs[i].indexOf('=')) + 1);
      // Right of '=' is value.
    while (keyValue.indexOf('+') > -1) {
      keyValue = keyValue.substring(0,keyValue.indexOf('+')) + ' ' + keyValue.substring(keyValue.indexOf('+') + 1);
        // Replace each '+' in data string with a space.
    }
    keyValue = unescape(keyValue);
      // Unescape non-alphanumerics
    if (FORM_DATA[keyName]) {
      FORM_DATA[keyName] = FORM_DATA[keyName] + separator + keyValue;
        // Object already exists, it is probably a multi-select input,
        // and we need to generate a separator-delimited string
        // by appending to what we already have stored.
    } else {
      FORM_DATA[keyName] = keyValue;
        // Normal case: name gets value.
    }
  }
  FORM_DATA['baselocation'] = baselocation;
  return FORM_DATA;
}

FORM_DATA = createRequestObject();

  // This is the array/object containing the GET data.
  // FORM_DATA = createRequestObject();
  // Retrieve information with 'FORM_DATA [ key ] = value'.

var cookies = new Object();

function extractCookies() {
	var name, value;
	var beginning, middle, end;
	for (name in value) {
		cookies = new Object;
	}
	beginning = 0;
	while (beginning < document.cookie.length) {
		middle = document.cookie.indexOf('=',beginning);
		end = document.cookie.indexOf(';',beginning);
		if (end == -1)
			end = document.cookie.length;
		if ( (middle > end) || (middle == -1)) {
			name = document.cookie.substring(beginning, end);
			value = "";
		} else {
			name = document.cookie.substring(beginning,middle);
			value = document.cookie.substring(middle+1, end);
		}
		cookies[name] = unescape(value);
		beginning = end + 2;
	}
}


