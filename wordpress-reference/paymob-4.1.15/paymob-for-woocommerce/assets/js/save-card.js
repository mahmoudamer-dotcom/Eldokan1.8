jQuery( document ).ready(
	function ($) {
		var deleteUrl = ''; // Store the URL to delete the card

		// When the delete icon is clicked
		$( '.delete-card-icon' ).on(
			'click',
			function (e) {
				e.preventDefault(); // Prevent default action
				deleteUrl = $( this ).attr( 'href' ); // Store the deletion URL

				// Show the modal
				$( '#paymob-delete-modal' ).fadeIn();
			}
		);

		// Close modal when clicking the 'X' or 'Cancel' button
		$( '.paymob-close, #paymob-cancel-delete' ).on(
			'click',
			function () {
				$( '#paymob-delete-modal' ).fadeOut();
			}
		);

		// When the 'Delete' button is clicked
		$( '#paymob-confirm-delete' ).on(
			'click',
			function () {
				// Redirect to the delete URL to perform the deletion
				window.location.href = deleteUrl;
			}
		);
	}
);