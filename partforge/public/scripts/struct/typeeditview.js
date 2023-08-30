
var typeComponents = [];  // array of components
var typeDescriptions = []; // this is an array of array pairs (typeobject_id, description) for parts.
var typeDictionaryArray = [];
var typeFormLayoutArray = [];
var dictEditBuff = {};
var compEditBuff = {};

function htmlEscape(str) {
    return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
}

function Hex2Str(hex) {
    var str = '';
    if (typeof hex != 'undefined') {
	    for (var i = 0; i < hex.length; i += 2) {
	        var v = parseInt(hex.substr(i, 2), 16);
	        if (v) str += String.fromCharCode(v);
	    }
	}
    return str;
}

function Str2Hex(instr) {
	var r='';
	var i=0;
	var h;
	if (typeof instr === 'undefined') instr='';
	while (i<instr.length) {
		h=instr.charCodeAt(i++).toString(16);
		while(h.length<2) {
			h=h;
		}
		r+=h;
	}
	return r.toUpperCase();
}


var componentSubFieldsList = {};
var subFieldListFetched = false;

var layoutItemTouched = -1;
function touchLayoutItem(idx) {
	layoutItemTouched = idx;
}
function wasLayoutItemTouched(idx) {
	return layoutItemTouched==idx;
}
function clearTouchLayoutItem() {
	layoutItemTouched = -1;
}


/**
 * refreshes the componentSubFieldsList with subfields for edit checking purposes.  The flag subFieldListFetched
 * will be set when the fetch is complete.
 */
function loadAllComponentSubFields() {
	componentSubFieldsList = {}; // global
	subFieldListFetched = false;
	var typeobject_ids = [];
	for(var i = 0; i<typeComponents.length; i++) {
		var ids = typeComponents[i]["can_have_typeobject_id"].split('|');
		for(var ididx=0; ididx < ids.length; ididx++) {
			if (IsNumeric(ids[ididx])) {
				typeobject_ids.push(ids[ididx]);
			}
		}
	}
	$.getJSON(baseUrl + '/struct/jsonlistofobjectfields',
			{"typeobject_id" : typeobject_ids},
			function(data) {
				// populate the componentSubFieldsList with the results
				for(var i = 0; i < typeobject_ids.length; i++) {
					if (typeof data[typeobject_ids[i]] != "undefined") {
						componentSubFieldsList[typeobject_ids[i]] = data[typeobject_ids[i]];
					} else {
						alert("Did not get back subfields for typeobject_id = " + typeobject_ids[i]);
					}
				}
				subFieldListFetched = true;
			});
}

/**
 * Used only to ensure we stay alive by periodically doing background call.  It hardly matters what it does
 */
function startKeepAliveProcess() {
	$.getJSON(baseUrl + '/struct/keepalive',
			{},
			function(data) {
				var not_valid_user = typeof data['is_valid_user'] == 'undefined';
			});
	setTimeout('startKeepAliveProcess()', sessionTimeoutInterval);
}


/**
 * populate the component_subfield selector by making ajax call to get fieldnames
 * @param componentname
 * @param compFieldSelId is the ID of the select tag where the list of fields should be stuffed.
 * @param component_subfield
 */
function fillComponentSubFieldSelector(typeobject_id, compFieldSelId, component_subfield) {
	var fieldnames = [];
	$.getJSON(baseUrl + '/struct/jsonlistofobjectfields',
			{"typeobject_id" : typeobject_id},
			function(data) {
				fieldnames = data;
				var selHtml = '';
				var selected = '';
				fieldnames.sort();
				for(var j = 0; j<fieldnames.length; j++) {
					selected = (fieldnames[j] == component_subfield) ? " selected=selected" : "";
					selHtml += '<option value="'+fieldnames[j]+'"'+selected+'>'+fieldnames[j]+'</option>';
				}
				$("#" + compFieldSelId).html(selHtml);
			});
}

function renderAll() {
	renderListOfDictItems();
	renderListOfComponents();
	renderFormLayout();
}

function blankIfUndefined(val) {
	return (typeof val == 'undefined') ? '' : val;
}

function capitalizeFirstLetter(string)
{
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function isWriteProtected(fieldname) {
	return (writeProtectedFields.length > 0) && (('|'+writeProtectedFields.join('|')+'|').indexOf('|'+fieldname+'|')!=-1)
}

function getWriteProtectedMessage(fieldname, iscomponent) {
	// fieldname count be used to provide more information about why the field is write protected
	return iscomponent ? "the name cannot be changed because items exist that have this component set."
			 : "the name cannot be changed because items exist that have this value set or there are types defined with this as a component subfield.";
}

function renderCaption(name,caption_field) {
	caption_field = blankIfUndefined(caption_field);
	if (caption_field=='') {
		var words = name.split('_');
		for (var i = 0; i < words.length; i++) {
			words[i] = capitalizeFirstLetter(words[i]);
		}
		caption_field = words.join(' ');
	}
	return caption_field;
}

/*
 * This takes the edit checking parameters and converts them to a subcaption
 */
function renderSubcaptionHtml(subcaption, min, max, units, type) {
	var out = [];
	if (IsNumeric(min) && IsNumeric(max)) {
		if (type=="boolean") {
			if (min=="1") {
				out.push('Yes');
			} else if (max=="0") {
				out.push('No');
			}
		} else {
			if (min==max) {
				out.push('exactly '+min);
			} else {
				out.push(min + ' to ' + max);
			}
		}
	} else if (IsNumeric(min)) {
		out.push('&ge; '+min);
	} else if (IsNumeric(max)) {
		out.push('&le; '+max);
	}
	if ((typeof units != 'undefined') && (units.length > 0)) out.push(units);
	var synthcap = out.length > 0 ? out.join(' ') : '';
	return synthcap=='' ? subcaption : ((subcaption=='') ? synthcap : '['+synthcap+']<br />'+subcaption);
}

function renderListOfDictItems() {

	var html = "";
	html += '<p><a class="bd-linkbtn dictraweditlink" href="#">edit raw dictionary</a></p>';

	html += '<table class="listtable"><tr><th>Name</th><th>Type</th><th>Caption / Subcaption</th><th>Edit Instructions</th><th>Featured</th><th>Required</th><th>Minimum</th><th>Maximum</th><th>Units</th><th> </th>';

	typeDictionaryArray.sort(function(a,b){
		if (a.name > b.name) return 1;
		if (a.name < b.name) return -1;
		return 0;
	});
	for(var i=0; i<typeDictionaryArray.length; i++) {
		var f = typeDictionaryArray[i];
		var subcaption = blankIfUndefined(f["subcaption"]);
		var subcaption = renderSubcaptionHtml(subcaption, blankIfUndefined(f["minimum"]), blankIfUndefined(f["maximum"]), blankIfUndefined(f["units"]), blankIfUndefined(f["type"]));
		var editinstructions = '<div style="max-width:300px;">'+blankIfUndefined(f["editinstructions"])+'</div>';
		var type = f["type"];
		if ((type=='calculated') && (blankIfUndefined(f["expression"]) != "")) { type = type + '<br /><span class="paren">' + f["expression"] + '</span>'; }
		var delete_btn = !isWriteProtected(f["name"]) ? '<a data-key="'+i+'" class="bd-linkbtn dictlinedeletelink" href="#">delete</a>' : '';
	    html += '<tr>';
	    html += '<td>'+f["name"]+'</td><td>'+type+'</td><td>'+renderCaption(f["name"],f["caption"])+'<br /><span class="paren">'+subcaption+'</span></td><td>'+editinstructions+'</td><td>'+blankIfUndefined(f["featured"])+'</td><td>'+blankIfUndefined(f["required"])+'</td><td>'+blankIfUndefined(f["minimum"])+'</td><td>'+blankIfUndefined(f["maximum"])+'</td><td>'+blankIfUndefined(f["units"])+'</td>';
		html += '<td><a data-key="'+i+'" class="bd-linkbtn dictlineeditlink" href="#">edit</a> <a data-key="'+i+'" class="bd-linkbtn dictlinecopylink" href="#">copy</a> '+delete_btn+'</td>';
		html += '</tr>';
	}

	html += '</table>';
	html += '<a class="bd-linkbtn" href="#" id="addDictEntryLink">add</a><div id="dictEditorContainer"></div>';


	$("#dictionaryEditorDiv").html(html);
	$("#addDictEntryLink").on("click", function(event) {
		$("#dictionaryEditorDiv a").off('click');
		var key = -1; // nothing
		dictEditBuff = {"name" : "", "type" : "varchar", "len" : 32};
		renderDictItemEditor($('#dictEditorContainer'), true, key);
		return false;
	});

	$("a.dictlinedeletelink").on("click", function() {
		if (confirm("Are you sure?")) {
			var key = $(this).attr('data-key');
			typeDictionaryArray.splice(key,1);
			renderAll();
		}
		return false;
	});

	$("a.dictlineeditlink").on("click", function(event) {
		$("#dictionaryEditorDiv a").off('click');
		var key = $(this).attr('data-key');
		dictEditBuff = $.extend({},typeDictionaryArray[key]);    // global
		if (typeof dictEditBuff['type'] == 'undefined') dictEditBuff['type'] = 'varchar'; // set something, anything
		renderDictItemEditor($('#dictEditorContainer'),false, key);


		return false;
	});

	$("a.dictlinecopylink").on("click", function(event) {
		$("#dictionaryEditorDiv a").off('click');
		var key = $(this).attr('data-key');
		dictEditBuff = $.extend({},typeDictionaryArray[key]);    // global
		if (typeof dictEditBuff['type'] == 'undefined') dictEditBuff['type'] = 'varchar'; // set something, anything
		renderDictItemEditor($('#dictEditorContainer'),true, key);


		return false;
	});

	$("a.dictraweditlink").on("click", function(event) {
		$("#dictionaryEditorDiv a").off('click');
		renderDictRawEditor($(this).parent());
		return false;
	});

}

function subtypeSelectHtml(selectTagId, component_value, component_name) {
	var copyDesc = [];
	for(var i = 0; i<typeComponents.length; i++) {
		if (typeComponents[i]["component_name"]==component_name) {
			var idssearch = '|'+typeComponents[i]["can_have_typeobject_id"]+'|';
			for(var ti=0; ti<typeDescriptions.length; ti++) {
				if (idssearch.indexOf('|'+typeDescriptions[ti][0]+'|')!=-1) {
					copyDesc.push([typeDescriptions[ti][0], typeDescriptions[ti][1]]);
				}
			}
		}
	}
	return selectHtml2(selectTagId,'de-propval', copyDesc, component_value);
}


function fetchDictItemEditorHtml() {
	var html = "";
	html += '<div class="bd-propeditor">';
	if ((typeof typesListing[dictEditBuff['type']] != 'undefined')) {
		html += '<p>'+typesListing[dictEditBuff['type']]['help']+'</p>';
	}
	html += '<div><table class="edittable editdictionary">';

	html += '<tr><th>Type:</th><td>'+typeSelectHtml('dictionaryTypeSelect', dictEditBuff['type'])+'</td></tr>';

	var name_edit_disabled = '';
	var disabled_msg = '';
	if (isWriteProtected(dictEditBuff['name'])) {
		name_edit_disabled = ' disabled="disabled" ';
		disabled_msg = '<div class="disabled_message">'+getWriteProtectedMessage(dictEditBuff['name'], false)+'</div>';
	}

	html += '<tr><th>Name:<br /><span class="paren">internal name for the field.  Must be unique.  Use lowercase and underscores, < '+MaxAllowedFieldLength.toString()+' chars.</span></th><td><input id="typeNameInput" class="de-propval" '+name_edit_disabled+' type="text" value="'+dictEditBuff['name']+'">'+disabled_msg+'</td></tr>';

	var entryObj = $.extend({}, dictEditBuff);  // clone
	delete entryObj['type'];
	delete entryObj['name'];

	for(var paramKey in typesListing[dictEditBuff['type']]['parameters']) {
		try {
			var propvaltype = typesListing[dictEditBuff['type']]['parameters'][paramKey]['type'];
		} catch(e) {};
		if (typeof propvaltype == 'undefined') {
			var propvaltype = 'string';
		}
		var propvalue = dictEditBuff[paramKey];
		if (typeof propvalue == 'undefined') {
			propvalue = '';
		}

		if (propvaltype=='pickone') {
			try {
				var propvalvalues = typesListing[dictEditBuff['type']]['parameters'][paramKey]['values'];
			} catch(e) {};
			if (typeof propvalvalues != 'object') {
				var propvalvalues = [];
			}
			var out = {};
			for(var propvalkey = 0; propvalkey < propvalvalues.length; propvalkey++) {
				var propvalvalue = propvalvalues[propvalkey];
				out[propvalvalue] = propvalvalue;
			}
			var valueinput = selectHtml('','de-propval', out, propvalue);
		} else if ((typeof propvalue == "object") || (propvaltype=='hashtable')) {
			var iniStyleProps = [];
			for(var prop in propvalue) {
				if (propvalue.hasOwnProperty(prop)) {
					iniStyleProps.push(prop+'='+propvalue[prop]);
				}
			}
			var valueinput = $('<textarea class="de-propval"></textarea>').text(iniStyleProps.join("\r\n"))[0].outerHTML;
		} else if ((paramKey=='component_name') && (dictEditBuff['type']=='component_subfield')) {
			var valueinput = componentSelectHtml('propparam_' + paramKey, propvalue);
		} else if ((paramKey=='embedded_in_typeobject_id') && (dictEditBuff['type']=='component_subfield')) {
			var valueinput = subtypeSelectHtml('propparam_' + paramKey,propvalue, dictEditBuff['component_name']);
		} else if ((paramKey=='component_subfield') && (dictEditBuff['type']=='component_subfield')) {
			var valueinput = subfieldSelectHtml('propparam_' + paramKey,propvalue);
		} else if ((paramKey=='editinstructions')) {
			var valueinput = '<textarea class="de-propval">'+htmlEscape(propvalue)+'</textarea>';
		} else {
			var valueinput = '<input class="de-propval" type="text" value="'+htmlEscape(propvalue)+'">';
		}

		var paramHelp = typesListing[dictEditBuff['type']]['parameters'][paramKey]['help'];
		var paramHelpstr = "";
		if (typeof paramHelp != 'undefined') {
			paramHelpstr = '<br /><span class="paren">'+paramHelp+'</span>';
		}
		html += '<tr data-paramkey="'+paramKey+'"><th>'+paramKey+':'+paramHelpstr+'</th><td>'+valueinput+'</td></tr>';
	}
	html += '</table></div>';
	html += '<a class="bd-linkbtn propeditdonelink" href="#">done</a> <a class="bd-linkbtn propeditcancellink" href="#">cancel</a>';
	html += '</div>';
	return html;
}

function saveDictItemEditorToBuff(containerSet,showAlerts,isNew,key) {
	var ok = true;

	dictEditBuff = {};
	dictEditBuff['name'] = $("#typeNameInput").val();


	var name = dictEditBuff['name'];

	if (!checkifDictionaryNameOk(name,showAlerts)) ok = false;

	// make sure we don't duplicate a fieldname within the components
	var dup = false;
	for(var i =0; i < typeDictionaryArray.length; i++) {
		if ((isNew || (i!=key)) && (typeDictionaryArray[i]['name']==name)) {
			dup = true;
			break;
		}
	}

	// check for the same names in the components
	for(var i=0; i<typeComponents.length; i++) {
		if ((typeComponents[i]['component_name']==name)) {
			dup = true;
			break;
		}
	}

	if (dup) {
		if (showAlerts) {
			alert('please use a unique name for the entry.');
		}
		ok = false;
	}

	dictEditBuff['type'] = $("#dictionaryTypeSelect").val();


	containerSet.find("tr").each(function(index,elem) {
		var propname = $(elem).attr('data-paramkey');
		var rawpropvalue = $.trim($(".de-propval",elem).val());  // this converts select boxes as well as text
		if ((propname!==undefined) && (rawpropvalue!="")) {

			// string, pickone, hashtable,
			try {
				var propType = typesListing[dictEditBuff['type']]['parameters'][propname]['type'];
				if (propType=='hashtable') {
					propvalue = {};
					var rowpropvaluesplit = rawpropvalue.split("\n");
					for(var i=0; i<rowpropvaluesplit.length; i++) {
						var row = rowpropvaluesplit[i];
						var rowParts = splitOnce(row,'=');
						var rowkey = $.trim(rowParts[0]);
						if (rowkey!='') {
							var rowval = rowkey;
							if ((rowParts.length > 1) && ($.trim(rowParts[1])!='')) {
								rowval = $.trim(rowParts[1]);
							}
							propvalue[rowkey] = rowval;
						}
					}
				//	var propvalue = $.evalJSON(rawpropvalue);
				} else if (propType=='pickone') {
					var propvalue = rawpropvalue; // not right
				} else {
					var propvalue = rawpropvalue;
				}

				if (showAlerts && ("minimum|maximum".indexOf(propname)>-1) && (propvalue!='') && !IsNumeric(propvalue)) {
					alert('the property name '+propname+ ' must be numeric or blank');
					ok = false;
				}

				dictEditBuff[propname] = propvalue;
			} catch(e) {
				if (showAlerts) {
					alert('the property name '+propname+ ' cannot be parsed: '+e.message);
				}
			}
		}
	});

	if (IsNumeric(dictEditBuff['minimum']) && IsNumeric(dictEditBuff['maximum']) && (parseFloat(dictEditBuff['minimum'])>parseFloat(dictEditBuff['maximum']))) {
		alert('the minimum value must be less than the maximum value');
		ok = false;
	}

	if (dictEditBuff['type']=="boolean") {
		// both max and min must be undefine if either is undefined.
		if (((typeof dictEditBuff['minimum'] == "undefined") && (typeof dictEditBuff['maximum'] != "undefined"))
				|| ((typeof dictEditBuff['minimum'] != "undefined") && (typeof dictEditBuff['maximum'] == "undefined"))) {
			alert('both maximum and minimum need to be set or both unset.');
			ok = false;
		}
	}

	return ok;
}

function renderDictItemEditor(containerSet, isNew, key) {
	containerSet.html(fetchDictItemEditorHtml());
	containerSet.dialog({
		title: "Edit Dictionary Entry",
		width: 700,
		modal: true,
		closeOnEscape: false,
		close: function( event, ui ) {containerSet.dialog('destroy'); renderAll();}
	});
	clearTouchLayoutItem();
	$("#dictionaryTypeSelect").on("change", function() {
		saveDictItemEditorToBuff(containerSet,false,isNew,key);
		renderDictItemEditor(containerSet, isNew, key);
		return false;
	});

	// special handling for component_name if it exists
	var compSelectorId = 'propparam_component_name';
	if ($("#"+compSelectorId).length > 0) {  // if it exists
		$('#propparam_embedded_in_typeobject_id').on("change", function() {
			saveDictItemEditorToBuff(containerSet,false,isNew,key);
			renderDictItemEditor(containerSet, isNew, key);
			return false;
		});

		var compFieldSelId = 'propparam_component_subfield';
		$("#" + compSelectorId).on("change", function() {
			saveDictItemEditorToBuff(containerSet,false,isNew,key);
			renderDictItemEditor(containerSet, isNew, key)
			return false;
		});

		// Create the initial list of options.
		fillComponentSubFieldSelector($("#propparam_embedded_in_typeobject_id").val(),compFieldSelId, dictEditBuff['component_subfield']);
	}

	containerSet.find("a.propeditdonelink").on("click", function(event) {


		var ok = saveDictItemEditorToBuff(containerSet,true,isNew,key);

		if (ok || ((UserType=='Admin') && confirm("proceed at your own peril?"))) {
			if (isNew) {
				typeDictionaryArray.push($.extend({},dictEditBuff));
			} else {
				typeDictionaryArray[key] = $.extend({},dictEditBuff);
			}
			containerSet.dialog('destroy');
			renderAll();
		}
		return false;

	});

	containerSet.find("a.propeditcancellink").on("click", function(event) {
		containerSet.dialog('destroy');
		renderAll();
		return false;
	});

}

function renderDictRawEditor(containerSet) {

	var html = "";
	html += '<div class="bd-propeditor"><p><a class="bd-linkbtn" id="dictraweditdonelink" href="#">done</a></p>';
	html += $('<textarea class="de-propval" id="dictrawtext"></textarea>').text(dictArrayToKeyValueList(typeDictionaryArray))[0].outerHTML;
	html += '</div>';
	containerSet.html(html);
	containerSet.dialog({
		title: "Edit/View Raw Dictionary String",
		width: 700,
		modal: true,
		closeOnEscape: false,
		close: function( event, ui ) {containerSet.dialog('destroy'); renderAll();}
	});


	// if we click done, then grab input and replace the contents of the buffer var typeDictionaryArray
	$("#dictraweditdonelink").on("click", function(event) {
		$("#dictionaryEditorDiv a").off('click');
		typeDictionaryArray = keyValueListToDictArray($("#dictrawtext").val());
		containerSet.dialog('destroy');
		renderAll();
		return false;
	});
}

/*
 * Turns the dictionary structure passed in by php into a numeric indexed array for processing
 */
function dictionaryToArray(dictObj) {
	var out = [];
	for(var key in dictObj) {
		if (key != undefined) {
			var props = dictObj[key];
			props["name"] = key;
			out.push(props);
		}
	}
	return out;
}

/*
 * Note that this is NOT the inverse of dictionaryToArray, as that function starts with a JS object.
 * This one starts with an JS array of objects
 */
function arrayToDictionaryStr(dictArray) {
	var obj = {};
	for(var key=0; key<dictArray.length; key++) {
		var name = dictArray[key]["name"];
		var dictentry = $.extend({},dictArray[key]);
		if (typeof name != "undefined") {
			obj[name] = dictentry;
			delete obj[name]["name"];  // added this so we don't have a redundant name (key & field)
		}
	}
	return $.toJSON(obj);
}


function subfieldSelectHtml(selectTagId, component_value) {
	return selectHtml(selectTagId,'de-propval', {component_value:component_value}, component_value);
}


function componentSelectHtml(selectTagId, component_value) {
	var out = {};
	if (component_value == '') out['']='';
	for(var i = 0; i<typeComponents.length; i++) {
		var name = typeComponents[i]["component_name"];
		out[name] = name;
	}
	return selectHtml(selectTagId,'de-propval', out, component_value);
}


function checkifDictionaryNameOk(name, showAlerts) {
	// make sure the field name looks like "my_field_name"
	var re = new RegExp("^[a-z_0-9]+$");
	if (!name.match(re) || (name.length > MaxAllowedFieldLength) || IsNumeric(name)) {
		if (showAlerts) {
			alert('the name of the field must only contain lowercase characters, numbers and underscores and must be < '+MaxAllowedFieldLength.toString()+' characters and not a number.');
		}
		return false;
	}

	// make sure we don't use reserved words for fieldnames
	var reservedWConcat = '|'+ReservedWords.join('|')+'|';
	if (reservedWConcat.indexOf('|'+name+'|')!=-1) {
		if (showAlerts) {
			alert('The field name "'+name+'" is a reserved word.  Please use a different name.');
		}
		return false;
	}

	return true;
}


/*
 * converts the working array dictionary into a of fieldname={json junk}
 */
function dictArrayToKeyValueList(dictArray) {
	var strarr = [];
	for(var key=0; key<dictArray.length; key++) {
		var name = dictArray[key]["name"];
		var dictentry = dictArray[key];
		if (typeof name != "undefined") {
			var obj = $.extend({},dictentry);  // clone the object
			delete obj["name"];
			strarr.push(name + "=" + $.toJSON(obj));
		}
	}
	return strarr.join("\r\n");
}

function splitOnce(st,ch) {
	var out = [];
	var splitvals = st.split(ch);
	var rhs = [];
	if (splitvals.length>0) {
		out.push(splitvals[0]);
		for(var i=0; i<splitvals.length; i++) {
			if (i>0) rhs.push(splitvals[i]);
		}
		if (rhs.length>0) out.push(rhs.join(ch));
	}
	return out;
}

/*
 * Basically an inverse of dictArrayToKeyValueList()
 */
function keyValueListToDictArray(keyValStr) {
	propvalue = {};
	var rowpropvaluesplit = keyValStr.split("\n");
	for(var i=0; i<rowpropvaluesplit.length; i++) {
		var row = rowpropvaluesplit[i];
		var rowParts = splitOnce(row,'=');
		var rowkey = rowParts[0].trim();
		if (rowkey!='') {
			var rowval = rowkey;
			if ((rowParts.length > 1) && ($.trim(rowParts[1])!='')) {
				rowval = rowParts[1].trim();
			}
			propvalue[rowkey] = JSON.parse(rowval);
		}
	}
	return dictionaryToArray(propvalue);
}

/*
 * This creates a structured array from the string encoding of the types.
 */

function ZeroIfUndefined(val) {
	return typeof val == 'undefined' ? 0 : val;
}

function componentStrToArray(cStr) {
	var out = [];
	if (cStr!='') {
		var compArray = cStr.split(';');
		for(var i=0; i<compArray.length; i++) {
			var fields = compArray[i].split(',');
			out.push({"typecomponent_id" : fields[0], "component_name" : fields[1], "can_have_typeobject_id" : fields[2], "caption" : Hex2Str(fields[3]), "subcaption" : Hex2Str(fields[4]), "featured" : ZeroIfUndefined(fields[5]), "required" : ZeroIfUndefined(fields[6]), "max_uses" : ZeroIfUndefined(fields[7])});
		}
	}
	return out;
}

function arrayToComponentStr(compArray) {
	var out = [];
	for(var i=0; i<compArray.length; i++) {
		out.push(compArray[i]["typecomponent_id"]+","+compArray[i]["component_name"]+","+compArray[i]["can_have_typeobject_id"]+","+Str2Hex(compArray[i]["caption"])+","+Str2Hex(compArray[i]["subcaption"])+","+compArray[i]["featured"]+","+compArray[i]["required"]+","+compArray[i]["max_uses"]);
	}
	return out.join(";");
}

function selectHtml(selectTagId, classId, valueDict, component_value) {
	var sorted = true;
	kvpairs = [];
	for(var key in valueDict) {
		var val = valueDict[key];
		kvpairs.push([key,val]);
	}

	if (sorted) {
		kvpairs.sort(function(a,b) {
			if (a[1] > b[1]) {
				return 1;
			}
			if (a[1] < b[1]) {
				return -1;
			}
			return 0;
		});
	}

	return selectHtml2(selectTagId, classId, kvpairs, component_value);
}

/**
 * select box using ordered pairs of keyvalues to maintain a predefined order.
 * @param selectTagId
 * @param classId
 * @param keyValueArray an array of arrays [ [key1,val1], [key2,val2], ...]
 * @param component_value
 * @returns {String}
 */
function selectHtml2(selectTagId, classId, keyValueArray, component_value) {
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

function typeSelectHtml(selectTagId, component_value) {
	var out = {};
	for(var entry in typesListing) {
		out[entry] = entry;
	}
	return selectHtml(selectTagId,'', out, component_value);
}

function renderListOfComponents() {

	// It's our responsibility to keep the componentSubFieldsList array up to date.  This runs asynchronously, so
	// we will need to use subFieldListFetched later on to make sure this happened.
	loadAllComponentSubFields();

	var html = "";

	var typeDescByTypeObject = {};
	for (var tdi=0; tdi<typeDescriptions.length; tdi++) {
		typeDescByTypeObject[typeDescriptions[tdi][0]] = typeDescriptions[tdi][1];
	}

	typeComponents.sort(function(a,b){
		if (a.component_name > b.component_name) return 1;
		if (a.component_name < b.component_name) return -1;
		return 0;
	});
	html += '<table class="listtable"><tr><th>Name</th><th>Type(s)</th><th>Caption / Subcaption</th><th>Featured</th><th>Required</th>'+(isAPart ? '<th>Max Uses</th>' : '')+'<th> </th>';
	for(var i = 0; i<typeComponents.length; i++) {
		var f = typeComponents[i];
	    html += '<tr>';

	    // possibly multiple types
	    var ids = typeComponents[i]["can_have_typeobject_id"].split('|');
	    var desc = [];
	    for(var ididx=0; ididx<ids.length; ididx++) {
	    	desc.push(typeDescByTypeObject[ids[ididx]]);
	    }

	    var delete_btn = !isWriteProtected(f["component_name"]) ? '<a data-key="'+i+'" class="bd-linkbtn linedeletelink" href="#">delete</a>' : '';

	    html += '<td>'+f["component_name"]+'</td><td>'+desc.join('<br />')+'</td><td>'+renderCaption(f["component_name"],f["caption"])+'<br /><span class="paren">'+blankIfUndefined(f["subcaption"])+'</span></td><td>'+f["featured"]+'</td><td>'+f["required"]+'</td>'+(isAPart ? '<td>'+f["max_uses"]+'</td>' : '');
		html += '<td><a data-key="'+i+'" class="bd-linkbtn lineeditlink" href="#">edit</a> '+delete_btn+'</td>';
		html += '</tr>';
	}

	html += '</table>';
	html += '<a class="bd-linkbtn" id="addComponentLink" href="#">add</a><div id="compEditorContainer"></div>';

	$("#componentEditorDiv").html(html);

	$("#addComponentLink").on("click", function(event) {
		compEditBuff = {"typecomponent_id" : "new", "component_name" : "", "can_have_typeobject_id" : "", "caption" : "", "subcaption" : "", "featured" : "0", "required" : "", "max_uses" : "1"};
		var key = -1; // nothing
		renderCompEditor($('#compEditorContainer'), true, key);
		return false;
	});

	$("a.linedeletelink").on("click", function(event) {
		if (confirm("Are you sure?")) {
			var key = $(this).attr('data-key');
			typeComponents.splice(key,1);
			renderAll();
		}
		return false;
	});

	$("a.lineeditlink").on("click", function(event) {
		$("#componentEditorDiv a").off('click');
		var key = $(this).attr('data-key');
		compEditBuff = $.extend({},typeComponents[key]);
		renderCompEditor($('#compEditorContainer'), false, key);
		return false;
	});

}

function fetchCompEditorHtml() {
	var html = "";

	html += '<div class="bd-propeditor">';
	html += '<div><table class="edittable editdictionary">';

	var name_edit_disabled = '';
	var disabled_msg = '';
	if (isWriteProtected(compEditBuff['component_name'])) {
		name_edit_disabled = ' disabled="disabled" ';
		disabled_msg = '<div class="disabled_message">'+getWriteProtectedMessage(compEditBuff['component_name'], true)+'</div>';
	}

	html += '<tr><th>Name:<br /><span class="paren">internal name for the component.  Must be unique.  Use lowercase and underscores, < '+MaxAllowedFieldLength.toString()+' chars.</span></th><td><input id="compNameInput" '+name_edit_disabled+' class="de-propval" type="text" value="'+compEditBuff['component_name']+'">'+disabled_msg+'</td></tr>';

	var ids = compEditBuff["can_have_typeobject_id"].split('|');
	html += '<tr data-paramkey="can_have_typeobject_id"><th>Component Type(s):<br /><span class="paren">select the type(s) allowed for this component.</span></th><td>';
	for(var ididx=0; ididx<ids.length+1; ididx++) {
		var copyDesc = $.extend([],typeDescriptions);
		var emptyel = ["",""];
		if (ididx>0) copyDesc.push(emptyel);
		var selid = (ididx==ids.length) ? '' : ids[ididx];
		var valueinput = selectHtml2('compHasAnTypeObjectId_'+ididx,'de-propval comptypesel', copyDesc, selid);
		html += '<div>'+valueinput+'</div>';
	}
	html += '</td></tr>';

	// caption
	var valueinput = '<input id="compCaption" class="de-propval" type="text" value="'+htmlEscape(compEditBuff["caption"])+'">';
	html += '<tr data-paramkey="caption"><th>Caption:<br /><span class="paren">Normally the name of a component is presented by removing the underscores from the name field and capitalizing words. If you want a different name presented to the user, enter it here.  See notes in the data dictionary editor on using HTML in your captions.</span></th><td>'+valueinput+'</td></tr>';

	// subcaption
	var valueinput = '<input id="compSubCaption" class="de-propval" type="text" value="'+htmlEscape(compEditBuff["subcaption"])+'">';
	html += '<tr data-paramkey="caption"><th>Subcaption:<br /><span class="paren">This text goes under the caption field.  See notes in the data dictionary editor on using HTML in your subcaptions.</span></th><td>'+valueinput+'</td></tr>';

	// featured
	var valueinput = selectHtml('compFeatured','de-propval', {"0":"0","1":"1"}, compEditBuff["featured"]);
	html += '<tr data-paramkey="caption"><th>Featured:<br /><span class="paren">1 = show this value in headline descriptions of this part or procedure.  By making a handful (say, 1 to 3) of your fields featured, you provide a nice at-a-glance summary of this part or procedure while sparing viewers the gory details.</span></th><td>'+valueinput+'</td></tr>';

	// required
	var valueinput = selectHtml('compRequired','de-propval', {"0":"0","1":"1"}, compEditBuff["required"]);
	html += '<tr data-paramkey="caption"><th>Required:<br /><span class="paren">1 = user must enter something for this field.</span></th><td>'+valueinput+'</td></tr>';

	// max_uses
	if (isAPart) {
		var valueinput = '<input id="compMaxUses" class="de-propval" type="text" value="'+htmlEscape(compEditBuff["max_uses"])+'">';
		html += '<tr data-paramkey="caption"><th>Max Uses:<br /><span class="paren">This is the number of times that the component can be associated with a current version of a part. Set this to 1 (the default) if you only want users to be able to use a part once. A value of 2 would allow 2 instances of the component to be used before showing an error. 0 disables the Max Uses checking. -1 does this in addition to not counting the use here against the Max Uses checking on other parts.</span></th><td>'+valueinput+'</td></tr>';
	}

	html += '</table></div>';
	html += '<a class="bd-linkbtn propeditdonelink" href="#">done</a> <a class="bd-linkbtn propeditcancellink" href="#">cancel</a>';
	html += '</div>';
	return html;
}


/**
 * Takes fields from the CompEditForm and maps into the compEditBuff
 */
function saveCompEditorToBuff(showAlerts,isNew,key) {
	var ok = true;
	var component_name = $("#compNameInput").val();
	compEditBuff['component_name'] = component_name;

	// we only do the checking here if we are really saving this for real
	if (showAlerts) {
		if (!checkifDictionaryNameOk(component_name,true)) {
			return false;
		}

		// make sure we don't duplicate a fieldname among components
		var dup = false;
		for(var i=0; i<typeComponents.length; i++) {
			if ((isNew || (i!=key)) && (typeComponents[i]['component_name']==component_name)) {
				dup = true;
				break;
			}
		}

		// check for the same names in the field dictionary
		for(var i=0; i<typeDictionaryArray.length; i++) {
			if (typeDictionaryArray[i]['name']==component_name) {
				dup = true;
				break;
			}
		}

		if (dup) {
			alert('please use a unique name for the entry.');
			return false;
		}

		if (isAPart) {
			var max_uses_val = $("#compMaxUses").val();
			if (!IsNumeric(max_uses_val) || (IsNumeric(max_uses_val) && (parseFloat(max_uses_val)<-1))) {
				alert('the Max Uses field must be -1 or greater.');
				ok = false;
			}
		}
	}
	compEditBuff["can_have_typeobject_id"] = getCompEditorTypesToBuff($('#compEditorContainer select.comptypesel'));
	compEditBuff["caption"] = $("#compCaption").val().trim();
	compEditBuff["subcaption"] = $("#compSubCaption").val().trim();
	compEditBuff["featured"] = $("#compFeatured").val();
	compEditBuff["required"] = $("#compRequired").val();
	compEditBuff["max_uses"] = isAPart ? $("#compMaxUses").val() : '1';

	return ok;

}

function getCompEditorTypesToBuff(typeselectors) {
	var ids = [];
	typeselectors.each(function(index,elem) {
		var val = $(elem).val();
		if (val!='') {
			if (('|'+ids.join('|')+'|').indexOf('|'+val+'|')==-1) {
				ids.push(val);
			}
		}
	});
	return ids.join('|');
}

function renderCompEditor(editorContainer, isNew, key) {
	editorContainer.html(fetchCompEditorHtml());
	editorContainer.dialog({
		title: "Edit Component ("+compEditBuff['typecomponent_id']+")",
		width: 700,
		modal: true,
		closeOnEscape: false,
		close: function( event, ui ) {editorContainer.dialog('destroy'); renderAll();}
	});
	clearTouchLayoutItem();
	editorContainer.find("a.propeditdonelink").on("click", function(event) {
		var ok = saveCompEditorToBuff(true,isNew,key);
		if (ok || ((UserType=='Admin') && confirm("proceed at your own peril?"))) {
			if (isNew) {
				typeComponents.push($.extend({},compEditBuff));
			} else {
				typeComponents[key] = $.extend(typeComponents[key],compEditBuff);
			}
			editorContainer.dialog('destroy');
			renderAll();
		}
		return false;
	});

	editorContainer.find("a.propeditcancellink").on("click", function(event) {
		editorContainer.dialog('destroy');
		renderAll();
		return false;
	});

	typeselectors = editorContainer.find("select.comptypesel");
	typeselectors.on("change", function(event) {
		saveCompEditorToBuff(false,isNew,key);
		renderCompEditor(editorContainer, isNew, key);
		return false;
	});
}



// http://stackoverflow.com/questions/18082/validate-decimal-numbers-in-javascript-isnumeric
function IsNumeric(input)
{
    return (input - 0) == input && (''+input).replace(/^\s+|\s+$/g, "").length > 0;
}


function allLayoutColumnNames() {
	var out = [];
	for(var i=0; i<typeFormLayoutArray.length; i++) {
		var row = typeFormLayoutArray[i];
		if (row["type"]=='columns') {
			out.push(row["column"]["name"]);
		}
	}
	return out;

}

/*
 * returns list of both component and dictionary fieldnames suitable for the layout fields
 * Removes fields that are already.
 */
function allFieldNames(showOnlyUnusedFields) {
	var out = [];
	var fieldname;
	var fieldAlreadyUsed = showOnlyUnusedFields ? '|'+allLayoutColumnNames().join('|')+'|' : '';
	for(var i = 0; i<typeComponents.length; i++) {
		var fieldname = typeComponents[i]["component_name"];
		if (fieldAlreadyUsed.indexOf('|'+fieldname+'|')==-1) {
			out.push(fieldname);
		}
	}
	for(var i=0; i<typeDictionaryArray.length; i++) {
		var fieldname = typeDictionaryArray[i]["name"];
		if (fieldAlreadyUsed.indexOf('|'+fieldname+'|')==-1) {
			out.push(fieldname);
		}
	}

	// this doesn't do anything anymore.  Remove?
	var defaultFields = [];
	for(var i=0; i<defaultFields.length; i++) {
		var fieldname = defaultFields[i];
		if (fieldAlreadyUsed.indexOf('|'+fieldname+'|')==-1) {
			out.push(fieldname);
		}
	}
	return out;
}

/*
 * This will build a list of all the dictionary fieldnames and also the component names.
 */
function layoutFieldnameSelectHtml(curr_value) {

	var html = "";
	var selected;
	html += '<select>';
	var fieldnames = allFieldNames(true);
	fieldnames.sort();
	var found = false;
	for(var i = 0; i<fieldnames.length; i++) {
		var fieldname = fieldnames[i];
		if (fieldname == curr_value) {
			selected = " selected=selected";
			found = true;
		} else {
			selected = "";
		}
		html += '<option value="'+fieldname+'"'+selected+'>'+fieldname+'</option>';
	}
	if (!found) {
		html += '<option value="'+curr_value+'" selected=selected>'+curr_value+'</option>';
	}
	html += '</select>';
	return html;
}

/**
 *
 * @returns an array of procedure_to that are in the layout.
 */
function allLayoutProcLists() {
	var out = [];
	for(var i=0; i<typeFormLayoutArray.length; i++) {
		var row = typeFormLayoutArray[i];
		if (row["type"]=='procedure_list') {
			out.push(row["procedure_to"]);
		}
	}
	return out;
}

function unusedTypeProcDescriptions(alwaysIncludeTypeObjectId) {
	var out = [];
	currentProcs = allLayoutProcLists();
	for (var tdi = 0; tdi < typeProcDescriptions.length; tdi++) {
		if ((currentProcs.indexOf(typeProcDescriptions[tdi][0]) == -1) || (alwaysIncludeTypeObjectId == typeProcDescriptions[tdi][0])) {
			out.push([typeProcDescriptions[tdi][0], typeProcDescriptions[tdi][1]]);
		}
	}
	return out;
}


function renderFormLayout() {
	var html = "";
	var scrollToViewID = "";
	var procDescByTypeObject = {};
	for (var tdi=0; tdi<typeProcDescriptions.length; tdi++) {
		procDescByTypeObject[typeProcDescriptions[tdi][0]] = typeProcDescriptions[tdi][1];
	}
	var show_proc_list_controls = (typeProcDescriptions.length > 0);


	// build list of fields that are allowed to show up in the layout.
	var validFields = '|typeversion_id|item_serial_number|effective_date|'+allFieldNames(false).join('|')+'|'; // get all the fields that are possible
	var sortingNoticeBannerTxt = 'Drag and drop fields, then click "done moving"';
	html += '<div class="bd-layout-outer-div"><div class="sortingNoticeBanner" style="display:none;margin-top: 15px; margin-bottom:5px;"">'+sortingNoticeBannerTxt+'</div><div class="bd-layout-frame">';
	html += '<ul id="layout_sortable" class="bd-layout-list">';
	for(var i=0; i<typeFormLayoutArray.length; i++) {
		var row = typeFormLayoutArray[i];
		var width = row["layout-width"];
		var blockid = 'layoutitem_'+i;
		var classnm = '';
		if (wasLayoutItemTouched(i)) {
			classnm = 'touchlight ';
			scrollToViewID = blockid;
		}
		if (row["type"]=='columns') {
			classnm += (width==2) ? 'bd-layout-item-double' : 'bd-layout-item-single';
			var warnstr = "";
			// does the field called row["column"]["name"] exist in the dictionary or component list?
			if (validFields.indexOf('|'+row["column"]["name"]+'|')==-1) {
				warnstr = '<div class="bd-bad-column-name">Not in Dictionary</div>';
			}
			html += '<li id="'+blockid+'" class="'+classnm+' db-field-item"><div class="db-edit-control">' +
					'<a href="#" class="bd-linkbtn layoutFieldEditLink" title="change field name">edit</a> '+
					'<a href="#" class="bd-linkbtn layoutFieldAddLink" title="insert a new field here">ins field</a> '+
					'<a href="#" class="bd-linkbtn layoutFieldAddTextLink" title="insert a text block or photo here">ins text</a> ';
			html += show_proc_list_controls ? '<a href="#" class="bd-linkbtn pf-inserting layoutProcListEditLink" title="insert a list of procedures here">ins proc</a> ' : '';
			html += '<a href="#" class="bd-linkbtn startSortLink" title="rearrange the layout by draging and dropping">move</a> ' +
					'<a href="#" class="bd-linkbtn layoutFieldDeleteLink" title="delete this block">delete</a> ' +
					'</div><div class="db-sizer-control"> <a href="#" class="bd-linkbtn layoutWiderLink" title="make two columns wide">wide</a> ' +
					'<a href="#" class="bd-linkbtn layoutNarrowerLink" title="make one column wide">narrow</a> ' +
					'<a href="#" class="bd-linkbtn doneSortLink" title="finish moving/arranging and return to editing">done moving</a></div>'+row["column"]["name"]+warnstr+'</li>';

		} else if (row["type"]=='html') {
			classnm += 'bd-layout-item-double pf-html-item';
			var texthtml = row["html"];
			if (texthtml=='') texthtml = '<div class="empty_space_notice">(click "edit" to add some text or photos)</div>';
			html += '<li id="'+blockid+'" class="'+classnm+'"><div class="db-edit-control">'+
					'<a href="#" class="bd-linkbtn layoutHtmlEditLink">edit</a> ' +
					'<a href="#" class="bd-linkbtn layoutFieldAddLink" title="insert a new field here">ins field</a> '+
					'<a href="#" class="bd-linkbtn layoutFieldAddTextLink" title="insert a text block or photo here">ins text</a> ';
			html += show_proc_list_controls ? '<a href="#" class="bd-linkbtn pf-inserting layoutProcListEditLink" title="insert a list of procedures here">ins proc</a> ' : '';
			html += '<a href="#" class="bd-linkbtn startSortLink" title="rearrange the layout by draging and dropping">move</a> ' +
					'<a href="#" class="bd-linkbtn layoutHtmlDeleteLink">delete</a> ' +
					'</div><div class="db-sizer-control"> <a href="#" class="bd-linkbtn doneSortLink" title="finish moving/arranging and return to editing">done moving</a></div>'+texthtml+'</li>';

		} else if (row["type"]=='procedure_list') {
			classnm += 'bd-layout-item-double bd-layout-proc-list';
			var texthtml = '<div style="font-weight: bold">Procedure List:</div>';
			texthtml += "<div>"+procDescByTypeObject[row["procedure_to"]]+"</div>";
			texthtml += '<span class="paren">Required: '+row["procedure_required"]+'</span>';
			html += '<li id="'+blockid+'" class="'+classnm+'"><div class="db-edit-control">'+
					'<a href="#" class="bd-linkbtn pf-editing layoutProcListEditLink">edit</a> ' +
					'<a href="#" class="bd-linkbtn layoutFieldAddLink" title="insert a new field here">ins field</a> '+
					'<a href="#" class="bd-linkbtn layoutFieldAddTextLink" title="insert a text block or photo here">ins text</a> ';
			html += show_proc_list_controls ? '<a href="#" class="bd-linkbtn pf-inserting layoutProcListEditLink" title="insert a list of procedures here">ins proc</a> ' : '';
			html += '<a href="#" class="bd-linkbtn startSortLink" title="rearrange the layout by draging and dropping">move</a> ' +
					'<a href="#" class="bd-linkbtn layoutHtmlDeleteLink">delete</a> ' +
					'</div><div class="db-sizer-control"> <a href="#" class="bd-linkbtn doneSortLink" title="finish moving/arranging and return to editing">done moving</a></div>'+texthtml+'</li>';
		}
	}
	html += '</ul>';
	html += '</div></div><div class="sortingNoticeBanner" style="display:none; margin-top:5px; margin-bottom:15px;">'+sortingNoticeBannerTxt+'</div>';
	html += '<a class="bd-linkbtn" href="#" id="addLayoutFieldLink">add field</a>';
	html += ' <a class="bd-linkbtn pf-appending layoutFieldAddTextLink" href="#">add text & photos block</a>';
	html += show_proc_list_controls ? ' <a class="bd-linkbtn pf-appending layoutProcListEditLink" href="#">add list of procedures</a>' : '';


	$("#formLayoutEditorDiv").html(html);
	if (scrollToViewID!='') {
		bringElementIntoView($('#'+scrollToViewID));
	}

	$("#addLayoutFieldLink").on("click", function() {
		var fieldnames = allFieldNames(true);
		var outitem = {};
		outitem['type'] = 'columns';
		outitem['column'] = {};
		if (fieldnames.length>0) {
			outitem['column']['name'] = fieldnames[0];
		} else {
			outitem['column']['name'] = '';
		}
		outitem['layout-width'] = 1;
		typeFormLayoutArray.push(outitem);
		touchLayoutItem(typeFormLayoutArray.length - 1);
		renderAll();
		return false;
	});

	$(".layoutFieldAddLink").on("click", function() {

		var fieldnames = allFieldNames(true);
		var outitem = {};
		outitem['type'] = 'columns';
		outitem['column'] = {};
		if (fieldnames.length>0) {
			outitem['column']['name'] = fieldnames[0];
		} else {
			outitem['column']['name'] = '';
		}
		outitem['layout-width'] = 1;
		var id = $(this).parent().parent()[0].id;
		var idx = id.split('_')[1];
		typeFormLayoutArray.splice(idx,0,outitem);
		touchLayoutItem(idx);
		renderAll();
		return false;
	});

	// click handle insert html field to layout
	$(".layoutFieldAddTextLink").on("click", function() {
		var outitem = {};
		outitem['type'] = 'html';
		outitem['html'] = '';
		outitem['layout-width'] = 2;
		if ($(this).hasClass("pf-appending")) {
			typeFormLayoutArray.push(outitem);
			var idx = typeFormLayoutArray.length - 1;
		} else {
			var id = $(this).parent().parent()[0].id;
			var idx = id.split('_')[1];
			typeFormLayoutArray.splice(idx,0,outitem);
		}
		touchLayoutItem(idx);
		renderAll();
		return false;
	});

	$(".db-sizer-control a.layoutWiderLink, .db-sizer-control a.layoutNarrowerLink").on("click", function() {
		var idx = $(this).parent().parent()[0].id.split('_')[1];
		touchLayoutItem(idx);
		if ($(this).hasClass('layoutWiderLink')) {
			if (typeFormLayoutArray[idx]['layout-width']<2) {
				typeFormLayoutArray[idx]['layout-width']++;
				$(this).parent().parent().removeClass('bd-layout-item-single').addClass('bd-layout-item-double');
			}
		} else {
			if (typeFormLayoutArray[idx]['layout-width']>1) {
				typeFormLayoutArray[idx]['layout-width']--;
				$(this).parent().parent().removeClass('bd-layout-item-double').addClass('bd-layout-item-single');
			}
		}
		return false;
	});

	$(".layoutFieldEditLink").on("click", function() {
		var id = $(this).parent().parent()[0].id;
		var idx = id.split('_')[1];
		touchLayoutItem(idx);
		var html = '';
		html += layoutFieldnameSelectHtml(typeFormLayoutArray[idx]['column']['name'])+' <a href="#" class="bd-linkbtn db-done">done</a>';

		$(this).parent().parent().html(html);
		$("#"+id+" a.db-done").on("click", function() {
			typeFormLayoutArray[idx]['column']['name'] = $("#"+id+" select").val();
			renderAll();
			return false;
		});
		return false;
	});

	$(".layoutProcListEditLink").on("click", function() {
		var outitem = {};
		outitem['type'] = 'procedure_list';
		outitem['procedure_required'] = '0';
		outitem['layout-width'] = 2;
		if ($(this).hasClass("pf-appending")) {
			typeFormLayoutArray.push(outitem); // place at end of array
			var idx = typeFormLayoutArray.length - 1;
			var id = "layoutitem_new_item";
			// add a container for editing and keep a reference to it
			$('<li id="'+id+'" class="bd-layout-proc-list bd-layout-item-double"></li>').appendTo('#layout_sortable');
			var targel = $('#'+id);
		} else if ($(this).hasClass("pf-inserting")) {
			var curr_el_id = $(this).parent().parent()[0].id;
			var idx = curr_el_id.split('_')[1];
			typeFormLayoutArray.splice(idx,0,outitem);
			var id = "layoutitem_new_item";
			$('<li id="'+id+'" class="bd-layout-proc-list bd-layout-item-double"></li>').insertBefore('#'+curr_el_id);
			var targel = $('#'+id);
		} else { // pf-editing
			var targel = $(this).parent().parent();
			var id = targel[0].id;
			var idx = id.split('_')[1];
		}

		touchLayoutItem(idx);
		var html = '';
		// build embedded editor and insert
		html += '<table class="bd-propeditor">';
		var valueinput = selectHtml2('ProcListTypeObjectId_'+idx,'de-propval', unusedTypeProcDescriptions(typeFormLayoutArray[idx]["procedure_to"]), typeFormLayoutArray[idx]["procedure_to"]);
		html += '<tr><th>Procedure Type:</th><td> '+valueinput+'</td></tr>';
		var valueinput = selectHtml('ProcListRequired_'+idx,'de-propval', {"0":"0","1":"1"}, typeFormLayoutArray[idx]["procedure_required"]);
		html += '<tr><th>Is Required:</th><td> '+valueinput+'</td></tr>';
		html += '<tr><td></td><td><a href="#" class="bd-linkbtn db-done">done</a></td></tr>';
		html += '</table>';
		targel.html(html);
		$("#"+id+" a.db-done").on("click", function() {
			typeFormLayoutArray[idx]["procedure_to"] = $("#"+id+" select").val();
			typeFormLayoutArray[idx]["procedure_required"] = $("#ProcListRequired_"+idx).val();
			renderAll();
			return false;
		});
		return false;
	});

	$(".layoutFieldDeleteLink").add(".layoutHtmlDeleteLink").on("click", function() {
		var id = $(this).parent().parent()[0].id;
		var idx = id.split('_')[1];
		if (confirm("are you sure?")) {
			typeFormLayoutArray.splice(idx,1);
			touchLayoutItem(-1);
			renderAll();
		}
		return false;
	});


	// open an html editor for this html type field.
	$(".layoutHtmlEditLink").on("click", function() {
		$("#formLayoutEditorDiv a").off('click');
		var id = $(this).parent().parent()[0].id;
		var idx = id.split('_')[1];
		touchLayoutItem(idx);
		var html = '';
		html += '<textarea id="htmleditorID">'+typeFormLayoutArray[idx]['html']+'</textarea>';
		$("#HtmlEditorContainer").html(html);
		$("#HtmlEditorContainer").dialog({
			title: "Edit Text",
			width: 600,
			modal: true,
			closeOnEscape: false,
			resize: function () {
				//var height = $("#HtmlEditorContainer").css("height");
				var height = $("#HtmlEditorContainer").height();
				height -=$("#htmleditorID_toolbargroup").height();
				height -=$("#htmleditorID_path_row").height();
				height -= 10;

			    $('#htmleditorID_ifr').css("height",height+"px");
			},
			open: function () {
                tinyMCE.init({ mode: 'exact',
			                	elements: 'htmleditorID',
			                	content_css : baseUrl+"/commonLayout.css",
			                	dfimageupload_upload_url : TypeObjectId=='new' ? '' : baseUrl+'/types/documents?typeobject_id='+TypeObjectId+'&format=json',
			                	dfimageupload_typeobject_id : TypeObjectId,
			                	theme: "advanced",
			                	plugins : "dfimageupload,autolink,lists,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,advlist",
			            		theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,formatselect,|,bullist,numlist,|,outdent,indent,blockquote,|,link,unlink,help,code",
			            		theme_advanced_buttons2 : "tablecontrols,|,hr,removeformat,|,sub,sup,|,charmap,|,dfimageupload",
			                	width: '100%', height: 300,
			                	paste_preprocess : function(pl, o) {
			                		if (/<img[^>]+src="data:image/ig.test(o.content)) {
			                			o.content = '';
			                			alert('Sorry.  You cannot past images from the clipboard.  Please use the Upload/Insert Image icon on the toolbar for this.');
			                		}
			                	}  });
                },
			close: function( event, ui ) {
				typeFormLayoutArray[idx]['html'] = $("#HtmlEditorContainer textarea").val();
	            tinyMCE.execCommand('mceRemoveControl', false, 'htmleditorID');
	            $(this).dialog('destroy');
	            renderAll();
	            },
	        buttons: {
	        	'Ok': function ()
                {
	        		typeFormLayoutArray[idx]['html'] = $("#HtmlEditorContainer textarea").val();
	        		tinyMCE.execCommand('mceRemoveControl', false, 'htmleditorID');
	        		$(this).dialog('destroy');
	        		renderAll();
                },
	        	'Cancel': function ()
	            {
	        		tinyMCE.execCommand('mceRemoveControl', false, 'htmleditorID');
	        		$(this).dialog('destroy');
	        		renderAll();
	            }
	        }
		});

		return false;
	});

	$(".startSortLink").on("click", function() {
		$( "#layout_sortable" ).sortable();
		$( "#layout_sortable" ).disableSelection();
		$( ".sortingNoticeBanner").show();
		$( "#layout_sortable" ).addClass('bd-sorting');
		var idxstartsort = $(this).parent().parent()[0].id.split('_')[1];
		touchLayoutItem(idxstartsort);
		bringElementIntoView($(this).parent().parent());
		$(this).parent().parent().addClass('touchlight');

		$(".doneSortLink").on("click", function(event) {
			// reorder the array typeFormLayoutArray per the final id numbers and redraw.
			newout = [];
			var idxclicked = $(this).parent().parent()[0].id.split('_')[1];
			$("#layout_sortable").children("li").each(function(idx,elm) {
				var oldidx = elm.id.split('_')[1];
				newout.push(typeFormLayoutArray[oldidx]);
				if (oldidx==idxclicked) touchLayoutItem(newout.length - 1);
			});
			typeFormLayoutArray = newout;

			// now add width to loners
			var nextcol = 0;
			for(var iitem=0; iitem<typeFormLayoutArray.length; iitem++) {
				var width = forceOneOrTwo(typeFormLayoutArray[iitem]['layout-width']);
				var holdwidth = width;

				if (nextcol==0) { // we have a
					nextcol += width;
					if (nextcol>1) {
						nextcol = 0;
					}
				} else { // nextcol=1
					if (width==1) {
						nextcol = 0;
					} else { // width = 2
						// widen the previous one
						typeFormLayoutArray[iitem-1]['layout-width']++;
						nextcol = 0;
					}
				}
			}
			if (nextcol==1) {
				typeFormLayoutArray[iitem-1]['layout-width']++;
			}
			renderAll();


			return false;
		});

		return false;
	});
}

function forceOneOrTwo(invar) {
	if (invar > 2) return 2;
	if (invar < 1) return 1;
	return Math.round(invar);
}

function bringElementIntoView(jqueryEl) {
	var topPos = jqueryEl.offset().top;
	var scrollTopCurr = $(window).scrollTop();
	var viewPortHeight = $(window).height();
	var elHidden = (topPos < scrollTopCurr) || (topPos > scrollTopCurr + viewPortHeight);

	if (elHidden) $('html, body').animate({scrollTop: topPos -100 }, 'slow');
}

/*
 * Turns the layout structure which is intrinsically two column into a flat
 */
function layoutToFlatArray(layoutIn) {
	var out = [];
	for(var i=0; i<layoutIn.length; i++) {

		var row = layoutIn[i];
		if (row["type"]=='columns') {
			for(var col=0; col<row["columns"].length; col++) {
				var outitem = {};
				outitem['type'] = 'columns';
				outitem['column'] = row["columns"][col];
				outitem['layout-width'] = (row["columns"].length==1) ? 2 : 1;
				out.push(outitem);
			}
		} else {
			var outitem = row;
			outitem['layout-width'] = 2;
			out.push(outitem);
		}
	}
	return out;
}

function startOutRowFromInLayoutRow(inrow, outrow) {
	outrow['type'] = inrow['type'];
	if (inrow['type']=='columns') {
		outrow['columns'] = [];
		outrow['columns'].push(inrow['column']);
	} else if (inrow['type']=='html') {
		outrow['html'] = inrow['html'];
	} else if (inrow['type']=='procedure_list') {
		outrow['procedure_required'] = inrow['procedure_required'];
		outrow['procedure_to'] = inrow['procedure_to'];
	}
}

function completeOutRowFromInLayoutRow(inrow, outrow) {
	if (inrow['type']=='columns') {
		outrow['columns'].push(inrow['column']);
	} else if (inrow['type']=='html') {
	} else if (inrow['type']=='procedure_list') {
	}
}

/*
 * This converts from local JS array back to JSON format for submitting in post.
 */

function flatArrayToGroupedJSON(layoutArray) {
	out = [];
	var nextcol = 0;
	var therow = {};
	for(var i=0; i<layoutArray.length; i++) {
		var item = layoutArray[i];
		var width = item['layout-width'];
		if (nextcol==0) { // we have a
			// fill preinitialized row for pushing out.
			startOutRowFromInLayoutRow(item, therow);
			nextcol += width;
			if (nextcol>1) {
				out.push(therow);
				therow = {};
				nextcol = 0;
			}
		} else { // nextcol=1
			if (width==1) {
				// complete an already started row then push out
				completeOutRowFromInLayoutRow(item, therow);
				out.push(therow);
				therow = {};
				nextcol = 0;
			} else { // width = 2. we will be too large, so push out already started single, then build and push out the new wide one
				out.push(therow);
				therow = {};
				startOutRowFromInLayoutRow(item, therow);
				out.push(therow);
				therow = {};
				nextcol = 0;
			}
		}
	}
	if (nextcol==1) {
		out.push(therow);
	}
	return $.toJSON(out);
}


function packupFormVars() {
	$('input[name="list_of_typecomponents"]').val(arrayToComponentStr(typeComponents));
	$('input[name="type_data_dictionary"]').val(arrayToDictionaryStr(typeDictionaryArray));
	var tst = flatArrayToGroupedJSON(typeFormLayoutArray);
	$('input[name="type_form_layout"]').val(tst);
}

/**
 * Checks to make sure your layout it filled.
 * @returns {Boolean} true if we are OK to submit
 */
function checkLayoutFilled() {
	var ok = true;
	var forgottenFields = allFieldNames(true);
	if (forgottenFields.length > 0) {
		alert("You have fields defined in your dictionary or component list [ " + forgottenFields.join(", ") + " ] that do not appear in the layout.  This is allowed, but maybe not what you intended.");
		ok = false;
	}
	return ok;
}

/**
 * Make sure there are not layout fields that have not been defined.
 * @returns {Boolean} true if we are OK to submit
 */
function checkUndefinedLayoutFields() {
	var ok = true;
	var allowedFields = '|'+allFieldNames(false).join('|')+'|';
	var unknownFields = [];
	var layoutFields = allLayoutColumnNames();
	for (var ii=0; ii<layoutFields.length; ii++) {
		if (allowedFields.indexOf('|'+layoutFields[ii]+'|')==-1) {
			unknownFields.push((layoutFields[ii].length>0) ? layoutFields[ii] : "<blank>");
		}
	}
	if (unknownFields.length > 0) {
		alert("You have at least one field in your layout [ " + unknownFields.join(", ") + " ] that is not defined in the dictionary or component list.");
		ok = false;
	}
	return ok;
}

function checkObsoleteProcedureListsInLayout() {
	var ok = true;
	var procTOInLayout = allLayoutProcLists();
	var layedOutObsoleteProcs = [];

	for (var tdi = 0; tdi < typeProcDescriptions.length; tdi++) {
		// for each obsolete procedure that we know about that is also in the layout, add to booboo list.
		if ((typeProcDescriptions[tdi][1].indexOf('[Obsolete]') > -1) && (procTOInLayout.indexOf(typeProcDescriptions[tdi][0]) > -1)) {
			layedOutObsoleteProcs.push(typeProcDescriptions[tdi][1]);
		}
	}

	if (layedOutObsoleteProcs.length > 0) {
		alert("You have at least one procedure list in your layout [ " + layedOutObsoleteProcs.join(", ") + " ] that is obsolete.");
		ok = false;
	}
	return ok;
}

/**
 * Checks to make sure that each component_subfield has a valid component name and subfield selected.
 * It loops through and finds all component_subfield types to do this.
 * @returns true if we are OK to submit
 */
function checkValidComponentSubFields() {
	var ok = true;

	// loop through the dictionary and select type="component_subfield" to check
	for ( var i = 0; i < typeDictionaryArray.length; i++) {
		if (typeof typeDictionaryArray[i]["type"] == 'undefined') {
			ok = false;
			alert('The dictionary entry ' + typeDictionaryArray[i]["name"] + ' does not have a type define. Please edit it to correct.');
		}
		if (typeDictionaryArray[i]["type"] == 'component_subfield') {
			// got a component_subfield type. Now lets check the parameters.
			// verify that typeDictionaryArray[i]["component_name"]
			var component_name_in_dict = typeDictionaryArray[i]["component_name"];
			var component_subfield_in_dict = typeDictionaryArray[i]["component_subfield"];
			var embedded_in_typeobject_id = typeDictionaryArray[i]["embedded_in_typeobject_id"];
			var foundCN = false;
			for (var key=0; key<typeComponents.length; key++) {
				var fieldname = typeComponents[key]["component_name"];
				if (fieldname == component_name_in_dict) {
					foundCN = true;

					// ok, the component name exists, what about the subfield.
					// We can only check this if we have the list already fetched.
					if (subFieldListFetched) {  // this means componentSubFieldsList is filled
						var foundCSF = false;

						var typeobject_id = null;
						if (IsNumeric(embedded_in_typeobject_id)) {
							typeobject_id = embedded_in_typeobject_id;
						} else {
							var ids = typeComponents[key]["can_have_typeobject_id"].split('|');
							if (ids.length == 1) {
								typeobject_id = ids[0];
							}
						}

						if (IsNumeric(typeobject_id)) {
							if (typeof componentSubFieldsList[typeobject_id] != 'undefined') {
								var fieldsstr = '|' + componentSubFieldsList[typeobject_id].join('|') + '|';
								if (fieldsstr.indexOf('|' + component_subfield_in_dict + '|') != -1) {
									foundCSF = true;
								}
							}
						}

						if (!foundCSF) {
							ok = false;
							alert('The dictionary entry for ' + typeDictionaryArray[i]["name"]
									+ ' is incorrect. It is a component_subfield type but the component_subfield ' + component_subfield_in_dict + ' cannot be found in the target component.  Try editing the dictionary entry.');
						}
					}
				}
			}
			if (!foundCN) {
				ok = false;
				alert('The dictionary entry for '
						+ typeDictionaryArray[i]["name"]
						+ ' is incorrect. It is a component_subfield type but the component_name parameter ' + component_name_in_dict + ' is not set to one of the existing component names.  Try editing the dictionary entry.');
			}
		}
	}

	return ok;
}

/**
 * Checks to make sure that each component has a valid typeobject_id it is pointing to.
 * @returns true if we are OK to submit
 */
function checkValidComponents() {
	var ok = true;

	// need list of valid descriptions
	var typeDescByTypeObject = {};
	for (var tdi=0; tdi<typeDescriptions.length; tdi++) {
		typeDescByTypeObject[typeDescriptions[tdi][0]] = typeDescriptions[tdi][1];
	}

	var unknownFields = [];
	for (var key=0; key<typeComponents.length; key++) {
		var fieldname = typeComponents[key]["component_name"];
		var ids = typeComponents[key]["can_have_typeobject_id"].split('|');
		for(var i=0; i < ids.length; i++) {
			var typeobject_id = ids[i];
			if (typeof typeDescByTypeObject[typeobject_id] == 'undefined') {
				unknownFields.push(fieldname);
			}
		}
	}
	if (unknownFields.length > 0) {
		alert("You have at least one component [ " + unknownFields.join(", ") + " ] that does not have a valid type selected.");
		ok = false;
	}

	return ok;
}

/**
 * This makes sure the variables specified in the calculated types are valid field names.
 */
function checkValidCalculatedTypes() {
	var ok = true;

	var allowed_var_names = [];
	var calculated_field_idx = [];

	// scan the dictionary for fields of type calculated.
	// get list that includes all the fieldnames that can evaluate to a number, even with some effort (varchar,float,boolean,date, datetime,enum, component_subfield)
	for(var i=0; i<typeDictionaryArray.length; i++) {
		if (['float','varchar','boolean','date','datetime','enum','component_subfield'].includes(typeDictionaryArray[i]['type'])) {
			allowed_var_names.push(typeDictionaryArray[i]['name']);
		} else if (typeDictionaryArray[i]['type']=='calculated') {
			calculated_field_idx.push(i);
		}
	}

	// now look the fields in the expressions to make sure they are actual fields.
	var reg = /\[([^\[^\]]+)]/g;
	for(var ifield = 0; ifield < calculated_field_idx.length; ifield++) {
		var idx = calculated_field_idx[ifield];
		var fieldsinexp = [...typeDictionaryArray[idx]['expression'].matchAll(reg)];
		for (var i = 0; i < fieldsinexp.length; i++) {
			var fieldinexp = fieldsinexp[i][1];
			if (!allowed_var_names.includes(fieldinexp)) {
				alert('"['+fieldinexp+']" is not an allowed variable name in the expression "'+typeDictionaryArray[idx]['expression']+'" entered for "'+typeDictionaryArray[idx]['name']+'".');
				ok = false;
			}
		}
	}

	return ok;
}

/**
 * Perform various checks on the conisistency of the definition
 * @returns true if we are OK to submit
 */
function validate() {
	$(".doneSortLink").last().trigger("click");
	$("a.db-done").trigger("click"); // makes sure any in-place edit dialogs are submitted.
	var layoutOk = checkLayoutFilled();
	var everthingElseOk = checkUndefinedLayoutFields() && checkValidComponents() && checkValidComponentSubFields() && checkValidCalculatedTypes() && checkObsoleteProcedureListsInLayout();
	if (everthingElseOk) {
		if (!layoutOk) {
			return confirm("save anyway?");
		} else {
			return true;
		}
	}
	return false;
}

$(document).ready(function() {

	$(document).tinymce({
		// Location of TinyMCE script
		script_url : baseUrl+'/scripts/tiny_mce/tiny_mce.js?v=12',

		// General options (actually all these are really set up above in the dialog open event)
		theme : "advanced",

		// Theme options
		theme_advanced_toolbar_location : "top",
		theme_advanced_toolbar_align : "left",
		theme_advanced_statusbar_location : "bottom",
		theme_advanced_resizing : true,

		// Example content CSS (should be your site CSS)
		content_css : baseUrl+"/commonLayout.css",

	});


	typeComponents = componentStrToArray( $('input[name="list_of_typecomponents"]').val() );

	// get list of parts that are allowed to be components
	$.getJSON(baseUrl + '/struct/jsonlistoftypedescriptions',
			{"typecategory_id" : "2"},
			function(data) {
				typeDescriptions = data;
				renderAll();
			});

	// we always want to pack things up.
	$('form').on("submit", function(event) {
		packupFormVars();
	});

	// we are really saving, so do a serious check
	$('input[name="btnOK"], input[name="btnChangePart"]').on("click", function() {
		return validate();
	});

	$('select[name="typecategory_id"]').on("change", function() {
		$('input[name="btnOnChange"]').val('changedtypecategory');
		packupFormVars();
		$('form').trigger("submit");
	});

	$('select[name="serial_number_type"]').on("change", function() {
		$('input[name="btnOnChange"]').val('changedsernumtype');
		packupFormVars();
		$('form').trigger("submit");
	});

	startKeepAliveProcess();

	typeDictionaryArray = dictionaryToArray(typeDataDictionary);
	typeFormLayoutArray = layoutToFlatArray(typeFormLayout);
	renderAll();

});
