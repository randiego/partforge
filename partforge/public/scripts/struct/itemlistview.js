$(document).ready(function() {
	$('select.changeselectbox').comboboxjumper();
	activateLinkToPageButton('#linkToPageButton', lookupUrl, linkToPageUrl, layoutTitle + pageTitleDetail);

	// for all the overfull procedure columns, scroll to the bottom so we see the latest ones.
	$('table.listtable tr td div.cellofprocs').each(function() {
		$(this).scrollTop($(this)[0].scrollHeight);
	});
});

