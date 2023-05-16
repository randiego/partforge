(function( $ ) {
	$.widget( "custom.comboboxjumper", {

		 options: {
			 skipfav: true,
			 hidecurrentvaluewhenchanging: 1,
			 allowempty: false
			 },

		_create: function() {
			this.wrapper = $( "<span>" )
				.addClass( "custom-combobox" )
				.insertAfter( this.element );
			this.element.hide();
			this._createAutocomplete();
			this._createShowAllButton();
		},

		_createAutocomplete: function() {
			var selected = this.element.children( ":selected" ),
				value = selected.val() ? selected.text() : (this.options.allowempty ? "" : this.element.children().first().text()),
				selectbox = this.element;
			var allowempty = this.options.allowempty;

			this.input = $( "<input>" )
				.appendTo( this.wrapper )
				.val( value )
				.attr( "title", "" )
				.addClass( "custom-combobox-input ui-widget ui-widget-content ui-state-default ui-corner-left" )
				.autocomplete({
					delay: 0,
					minLength: 0,
					source: $.proxy( this, "_source" ),
					select : function(event, ui) {
						selectbox.change();
					},
					change : function(event, ui) {
						// return input box to original select of you just loose focus without clicking
						event.target.value = '';
					}
				});
			if (this.options.hidecurrentvaluewhenchanging==1) {
				this.input.watermark(value, {className: 'ui-widget-content', useNative: false});
			}

			this.input.autocomplete( "instance" )._renderItem = function( ul, item ) {
				var text = allowempty ? "--- clear ---" : "--------------"; // all dashes appears to just create a seperator
				if (item.label!='') {
					text = item.label;
				}
				return $( '<li class="custom-combobox-items"></li>' )
					.append( '<a>' + text + "</a>" )
					.appendTo( ul );
			};

			this._on( this.input, {
				autocompleteselect: function( event, ui ) {
					ui.item.option.selected = true;
					this._trigger( "select", event, {
						item: ui.item.option
					});
				},

				autocompletechange: "_removeIfInvalid"
			});
		},

		_createShowAllButton: function() {
			var input = this.input,
				wasOpen = false;

			$( "<a>" )
				.attr( "tabIndex", -1 )
				.attr( "title", "Show All Items" )
				.appendTo( this.wrapper )
				.button({
					icons: {
						primary: "ui-icon-triangle-1-s"
					},
					text: false
				})
				.removeClass( "ui-corner-all" )
				.addClass( "custom-combobox-toggle ui-corner-right" )
				.mousedown(function() {
					wasOpen = input.autocomplete( "widget" ).is( ":visible" );
				})
				.click(function() {
					input.focus();

					// Close if already visible
					if ( wasOpen ) {
						return;
					}

					// Pass empty string as value to search for, displaying all results
					input.autocomplete( "search", "" );
				});
		},

		_source: function( request, response ) {
			var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
			var myskipfav = this.options.skipfav;
			response( this.element.children( "option" ).map(function() {
				var text = $( this ).text();
				if ( ( !request.term || matcher.test(text) ) ) {
					if (myskipfav && request.term && /^fav/.test(this.value)) {
					} else {
						return {
							label: text,
							value: text,
							option: this
						};
					}
				}
			}) );
		},

		_removeIfInvalid: function( event, ui ) {

			// Selected an item, nothing to do
			if ( ui.item ) {
				return;
			}

			// Search for a match (case-insensitive)
			var value = this.input.val(),
				valueLowerCase = value.toLowerCase(),
				valid = false;
			this.element.children( "option" ).each(function() {
				if ( $( this ).text().toLowerCase() === valueLowerCase ) {
					this.selected = valid = true;
					return false;
				}
			});

			// Found a match, nothing to do
			if ( valid ) {
				return;
			}

			// Remove invalid value
			this.input
				.val( "" )
				.attr( "title", value + " didn't match any item" )
				.tooltip( "open" );
			this.element.val( "" );
			this._delay(function() {
				this.input.tooltip( "close" ).attr( "title", "" );
			}, 2500 );
			this.input.autocomplete( "instance" ).term = "";
		},

		_destroy: function() {
			this.wrapper.remove();
			this.element.show();
		}
	});
})( jQuery );
