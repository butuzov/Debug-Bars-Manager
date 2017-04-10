/* Debug Bars Manager */
(function($) {
	$(function() {
		var $this = {

			// Initial Settings.
			settings: {
				// Debug Bar Menu Item  in Admin Bar
				menu: $('#wp-admin-bar-debug-bar'),
				// Timeout
				timeout: null,
			},


			save_State: function() {

				// Throtle Ajax Calls
				save_State_Ajax_Call = _.throttle(function() {
					$.ajax({
						url: $this.option('ajaxurl'),
						method: 'POST',
						data: {
							// Current Active Debug Bar
							bar: $(this).hasClass("debug-menu-link") ?
								$(this).attr("id") : "",

							// Debug Bar Settings,
							visible: $("body").hasClass("debug-bar-visible"),
							maximized: $("body").hasClass("debug-bar-maximized"),
							partial: $("body").hasClass("debug-bar-partial"),

							// Ajax Call Related Values.
							action: 'debug_bars_manager_save_state',
							nonce: $this.option('nonce')
						}
					});

				}, 5000);

				// // One of the panels at the debug bar.
				$('.debug-menu-link')
					.click(save_State_Ajax_Call);

				// Max / Min / Close links
				$('#debug-bar-actions span')
					.click(save_State_Ajax_Call);

				$('#wp-admin-bar-debug-bar > div.ab-item')
					.click(save_State_Ajax_Call);

			},

			/**
			 *  Turning On/Off Debug Bars
			 */
			toogle_Pannels: function() {

				// Manually change values for checkbox,
				// declining HTML behaviour, in order not to reload all
				// at the same time.

				$('label', $this.option('menu')).click(function(e) {

					e.preventDefault();

					// Small Timeout.
					window.clearTimeout($this.option('timeout'));

					var cbx = $("input#" + $(this).attr("for"),
						$this.option('menu'));

					// Toogle Checkbox Value
					cbx.prop("checked", !cbx.prop("checked")).trigger("change");

					return false;
				});

				var Inputs = $('input[type="checkbox"]',
					$this.option('menu'));

				Inputs.change(function() {

					data = new Object();
					data.action = 'debug_bars_manager_toogle_panels';
					data.nonce = $this.option('nonce');
					data.debug = Inputs.serialize();

					$.post(
						$this.option('ajaxurl'),
						data,
						function(html) {

							// $this.option( 'timeout', setTimeout( function(){
							// 	location.reload();
							// }, 500) );

						}
					);
				});

			},

			/**
			 * Update General Debug Information
			 */
			info: function() {

				$('<span/>', {
					style: 'font-size:12px;line-height:24px;',
					text: ' [' + $this.settings.stat + ']'
				}).appendTo($('> div.ab-item', $this.option('menu')));
			},

			/**
			 * Shortcut to Get/Set Settings.
			 */
			option: function(setting_name, setting_value) {

				if (typeof setting_value !== "undefined") {
					// this is setter.
					$this.settings[setting_name] = setting_value;
				} else {
					// this is getter.
					if (typeof $this.settings[setting_name] !== 'undefined')
						return $this.settings[setting_name];
					throw 'MissingSettingValue';
				}
			},

			window: function() {

				if (typeof $this.option('css') == 'object') {

					$('body')
						.removeClass('debug-bar-maximized')
						.addClass($this.option('css').join(" "));
				}

				if ($this.option('bar') !== "") {
					_.defer(function() {
						$('#' + $this.option('bar')).trigger('click');
					});
				}


			},

			/**
			 * Init Proccess
			 */
			intialize: function(settings) {

				$this.settings = $.extend(
					$this.settings, settings);


				return $this.info(),
					$this.window(),
					$this.toogle_Pannels(),
					$this.save_State();
			}
		};
		return $this.intialize(debugBarsManagerData);
	});
})(jQuery);