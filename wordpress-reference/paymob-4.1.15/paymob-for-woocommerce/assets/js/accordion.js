jQuery( document ).ready(
	function ($) {
		$( "#config-note-accordion" ).accordion(
			{
				icons: { "header": "ui-icon-plus", "activeHeader": "ui-icon-minus" },
				collapsible: false,
				active: false,
				heightStyle: "content"
			}
		);
		$( "#callback-accordion" ).accordion(
			{
				icons: { "header": "ui-icon-plus", "activeHeader": "ui-icon-minus" },
				collapsible: true,
				active: false,
				heightStyle: "content"
			}
		);

		$( "#has-items-accordion" ).accordion(
			{
				icons: {   "header": "ui-icon-triangle-1-e", "activeHeader": "ui-icon-triangle-1-s" },
				collapsible: false,
				active: false,
				heightStyle: "content"
			}
		);
		$( "#extra-accordion" ).accordion(
			{
				icons: { "header": "ui-icon-triangle-1-e", "activeHeader": "ui-icon-triangle-1-s" },
				collapsible: false,
				active: false,
				heightStyle: "content"
			}
		);
		$( "#save-changes-accordion" ).accordion(
			{
				icons: {  "header": "ui-icon-triangle-1-e", "activeHeader": "ui-icon-triangle-1-s" },
				collapsible: false,
				active: false,
				heightStyle: "content"
			}
		);
	}
);