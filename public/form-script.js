jQuery(document).ready(function($) {
	const form = $('#booking-form');
	const dateInput = $('#booking-date');
	const slotInput = $('#booking-slot');
	const slotOptions = $('#booking-slot-options');
	const slotStep = $('#booking-slot-step');
	const detailsStep = $('#booking-details-step');
	const selectedDateLabel = $('#booking-selected-date');
	const selectedSummary = $('#booking-selected-summary');
	const message = $('#form-message');
	const dateOptions = $('#booking-date-options');
	let availableDates = [];
	let calendar = null;

	const italianLocale = {
		firstDayOfWeek: 1,
		weekdays: {
			shorthand: ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'],
			longhand: ['Domenica', 'Lunedi', 'Martedi', 'Mercoledi', 'Giovedi', 'Venerdi', 'Sabato']
		},
		months: {
			shorthand: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'],
			longhand: ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre']
		}
	};

	const today = new Date().toISOString().split('T')[0];

	function initCalendar() {
		if (typeof flatpickr !== 'function') {
			dateInput.attr('type', 'date').attr('min', today).prop('readonly', false);
			return;
		}

		calendar = flatpickr(dateInput[0], {
			inline: true,
			dateFormat: 'Y-m-d',
			disableMobile: true,
			locale: italianLocale,
			minDate: today,
			enable: [],
			disable: [
				function(date) {
					return isWeekendDate(date);
				}
			],
			onDayCreate: function(dObj, dStr, fp, dayElem) {
				if (isWeekendDate(dayElem.dateObj)) {
					dayElem.classList.add('booking-weekend-day');
				}
			},
			onChange: function(selectedDates, dateStr) {
				handleDateChange(dateStr);
			}
		});
	}

	function updateDateConstraints() {
		if (availableDates.length === 0) {
			if (calendar) {
				calendar.set('enable', []);
				calendar.clear();
			}
			return;
		}

		const firstDate = availableDates[0];
		const maxDate = availableDates[availableDates.length - 1];
		const minDate = firstDate > today ? firstDate : today;

		if (calendar) {
			calendar.set({
				enable: availableDates,
				minDate: minDate,
				maxDate: maxDate
			});
			calendar.jumpToDate(calendar.selectedDates.length ? dateInput.val() : minDate);
			return;
		}

		dateInput.attr('min', minDate);
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
				availableDates = Array.isArray(response.dates) ? response.dates.filter(isWeekdayDateString).sort() : [];
				populateDateOptions(availableDates);
				updateDateConstraints();
				dateInput.prop('disabled', false);

				if (availableDates.length === 0) {
					showMessage('Non ci sono date disponibili al momento.', 'error');
					dateInput.val('');
					resetSlots('Seleziona una data per vedere gli orari disponibili');
					hideStep(slotStep);
					hideStep(detailsStep);
				}
			},
			error: function() {
				showMessage('Errore nel caricamento delle date disponibili', 'error');
				dateInput.prop('disabled', false);
			}
		});
	}

	function handleDateChange(date) {
		if (!date) {
			resetSlots('Seleziona una data per vedere gli orari disponibili');
			selectedDateLabel.text('-');
			hideStep(slotStep);
			hideStep(detailsStep);
			return;
		}

		if (availableDates.length > 0 && availableDates.indexOf(date) === -1) {
			showMessage('Data non disponibile. Seleziona una data con slot liberi.', 'error');
			dateInput.val('');
			resetSlots('Seleziona una data disponibile');
			selectedDateLabel.text('-');
			hideStep(slotStep);
			hideStep(detailsStep);
			return;
		}

		selectedDateLabel.text(formatDisplayDate(date));
		selectedSummary.text('-');
		showStep(slotStep);
		hideStep(detailsStep);
		loadSlots(date);
	}

	initCalendar();
	loadAvailableDates();

	if (!calendar) {
		dateInput.on('change', function() {
			handleDateChange($(this).val());
		});
	}

	function loadSlots(date) {
		resetSlots('Caricamento orari disponibili...');

		$.ajax({
			url: BookingData.rest_url + 'slots',
			type: 'GET',
			data: { date: date },
			success: function(response) {
				if (response.slots && response.slots.length > 0) {
					renderSlotButtons(response.slots);
				} else {
					resetSlots('Nessun orario disponibile');
				}
			},
			error: function() {
				resetSlots('Errore nel caricamento degli orari');
				showMessage('Errore nel caricamento degli slot', 'error');
			}
		});
	}

	function renderSlotButtons(slots) {
		let html = '';

		slots.forEach(function(slot) {
			const slotTime = escapeHtml(slot.time);
			const availabilityLabel = slot.available_spots === 1 ? '1 disponibilita' : slot.available_spots + ' disponibilita';

			html += '<button type="button" class="booking-slot-button" data-slot="' + slotTime + '" aria-pressed="false">';
			html += '<span class="booking-slot-time">' + slotTime + '</span>';
			html += '<span class="booking-slot-spots">' + availabilityLabel + '</span>';
			html += '</button>';
		});

		slotInput.val('');
		slotOptions.html(html);
	}

	function resetSlots(text) {
		slotInput.val('');
		slotOptions.html('<div class="booking-slot-empty">' + escapeHtml(text) + '</div>');
		selectedSummary.text('-');
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function isWeekdayDateString(dateString) {
		const date = new Date(dateString + 'T00:00:00');
		return !isWeekendDate(date);
	}

	function isWeekendDate(date) {
		const day = date.getDay();
		return day === 0 || day === 6;
	}

	slotOptions.on('click', '.booking-slot-button', function() {
		const button = $(this);

		slotOptions.find('.booking-slot-button')
			.removeClass('selected')
			.attr('aria-pressed', 'false');

		button
			.addClass('selected')
			.attr('aria-pressed', 'true');

		slotInput.val(button.data('slot'));
		selectedSummary.text(formatDisplayDate(dateInput.val()) + ' alle ' + button.data('slot'));
		showStep(detailsStep);
	});

	function showStep(step) {
		step.prop('hidden', false).addClass('is-active');
	}

	function hideStep(step) {
		step.prop('hidden', true).removeClass('is-active');
	}

	function formatDisplayDate(dateString) {
		if (!dateString) {
			return '-';
		}

		const date = new Date(dateString + 'T00:00:00');

		return date.toLocaleDateString('it-IT', {
			weekday: 'long',
			day: '2-digit',
			month: 'long',
			year: 'numeric'
		});
	}

	// Handle form submission
	form.on('submit', function(e) {
		e.preventDefault();

		if (!slotInput.val()) {
			showMessage('Seleziona un orario disponibile.', 'error');
			return;
		}

		const formData = {
			booking_date: $('#booking-date').val(),
			time_slot: slotInput.val(),
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
					if (calendar) {
						calendar.clear();
					}
					resetSlots('Seleziona una data per vedere gli orari disponibili');
					selectedDateLabel.text('-');
					hideStep(slotStep);
					hideStep(detailsStep);
					loadAvailableDates();
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
