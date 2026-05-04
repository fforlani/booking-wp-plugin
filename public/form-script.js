jQuery(document).ready(function($) {
	const form = $('#booking-form');
	const dateInput = $('#booking-date');
	const slotSelect = $('#booking-slot');
	const message = $('#form-message');
	const dateOptions = $('#booking-date-options');
	let availableDates = [];

	// Set minimum date to today
	const today = new Date().toISOString().split('T')[0];
	dateInput.attr('min', today);

	function updateDateConstraints() {
		if (availableDates.length === 0) {
			return;
		}

		const firstDate = availableDates[0];
		const maxDate = availableDates[availableDates.length - 1];
		dateInput.attr('min', firstDate > today ? firstDate : today);
		dateInput.attr('max', maxDate);
	}

	function populateDateOptions(dates) {
		if ( ! dateOptions.length ) {
			return;
		}

		let optionsHtml = '';
		dates.forEach(function(date) {
			optionsHtml += '<option value="' + date + '"></option>';
		});
		dateOptions.html(optionsHtml);
	}

	function loadAvailableDates() {
		dateInput.prop('disabled', true);
		$.ajax({
			url: BookingData.rest_url + 'dates',
			type: 'GET',
			success: function(response) {
				availableDates = Array.isArray(response.dates) ? response.dates : [];
				populateDateOptions(availableDates);
				updateDateConstraints();
				dateInput.prop('disabled', false);

				if (availableDates.length === 0) {
					showMessage('Non ci sono date disponibili al momento.', 'error');
					dateInput.val('');
					slotSelect.html('<option value="">Seleziona un orario</option>');
				}
			},
			error: function() {
				showMessage('Errore nel caricamento delle date disponibili', 'error');
				dateInput.prop('disabled', false);
			}
		});
	}

	// Load slots when date changes
	dateInput.on('change', function() {
		const date = $(this).val();
		if (!date) {
			slotSelect.html('<option value="">Seleziona un orario</option>');
			return;
		}

		if (availableDates.length > 0 && availableDates.indexOf(date) === -1) {
			showMessage('Data non disponibile. Seleziona una data con slot liberi.', 'error');
			dateInput.val('');
			slotSelect.html('<option value="">Seleziona un orario</option>');
			return;
		}

		loadSlots(date);
	});

	loadAvailableDates();

	function loadSlots(date) {
		$.ajax({
			url: BookingData.rest_url + 'slots',
			type: 'GET',
			data: { date: date },
			success: function(response) {
				let html = '<option value="">Seleziona un orario</option>';
				if (response.slots && response.slots.length > 0) {
					response.slots.forEach(function(slot) {
						html += '<option value="' + slot.time + '">' + slot.time + ' (' + slot.available_spots + ' disponibile)</option>';
					});
				} else {
					html = '<option value="">Nessun orario disponibile</option>';
				}
				slotSelect.html(html);
			},
			error: function() {
				slotSelect.html('<option value="">Errore nel caricamento</option>');
				showMessage('Errore nel caricamento degli slot', 'error');
			}
		});
	}

	// Handle form submission
	form.on('submit', function(e) {
		e.preventDefault();

		const formData = {
			booking_date: $('#booking-date').val(),
			time_slot: $('#booking-slot').val(),
			client_name: $('#client-name').val(),
			client_surname: $('#client-surname').val(),
			client_section: $('#client-section').val(),
			client_email: $('#client-email').val(),
			client_phone: $('#client-phone').val()
		};

		$.ajax({
			url: BookingData.rest_url + 'reserve',
			type: 'POST',
			data: JSON.stringify(formData),
			headers: {
				'Content-Type': 'application/json'
			},
			success: function(response) {
				if (response.success) {
					showMessage(response.message, 'success');
					form[0].reset();
					slotSelect.html('<option value="">Seleziona un orario</option>');
				} else {
					showMessage('Errore durante la prenotazione', 'error');
				}
			},
			error: function(xhr) {
				let errorMsg = 'Errore durante la prenotazione';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMsg = xhr.responseJSON.message;
				}
				showMessage(errorMsg, 'error');
			}
		});
	});

	function showMessage(text, type) {
		message
			.text(text)
			.removeClass('success error')
			.addClass(type)
			.show()
			.delay(5000)
			.fadeOut();
	}
});
