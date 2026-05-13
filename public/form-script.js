jQuery(document).ready(function($) {
	const form = $('#booking-form');
	const manageForm = $('#booking-manage-form');
	const dateInput = $('#booking-date');
	const slotInput = $('#booking-slot');
	const slotOptions = $('#booking-slot-options');
	const slotStep = $('#booking-slot-step');
	const detailsStep = $('#booking-details-step');
	const selectedDateLabel = $('#booking-selected-date');
	const selectedSummary = $('#booking-selected-summary');
	const message = $('#form-message');
	const dateOptions = $('#booking-date-options');
	const formHeader = $('.booking-form-header');
	const loadingState = $('#booking-loading-state');
	const successState = $('#booking-success-state');
	const successMessage = $('#booking-success-message');
	const successWhen = $('#booking-success-when');
	const successStudent = $('#booking-success-student');
	const successContact = $('#booking-success-contact');
	const newReservationButton = $('#booking-new-reservation');
	const errorModal = $('#booking-error-modal');
	const errorText = $('#booking-error-text');
	const cancelReservationButton = $('#booking-cancel-reservation');
	const confirmCancelModal = $('#booking-confirm-cancel-modal');
	const confirmCancelAction = $('#booking-confirm-cancel-action');
	const managementToken = manageForm.data('booking-token') || BookingData.manage_token || '';
	let availableDates = [];
	let calendar = null;

	if (!dateInput.length) {
		return;
	}

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
			altInput: true,
			altFormat: 'd-m-Y',
			altInputClass: 'booking-date-display',
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
				blurCalendarFocus();
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
			data: { token: managementToken },
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

				if (manageForm.length && dateInput.val()) {
					handleDateChange(dateInput.val());
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
			data: { date: date, token: managementToken },
			success: function(response) {
				if (response.slots && response.slots.length > 0) {
					renderSlotButtons(response.slots);
				} else {
					resetSlots('Nessun orario disponibile');
				}
				scrollToStep(slotStep);
			},
			error: function() {
				resetSlots('Errore nel caricamento degli orari');
				showMessage('Errore nel caricamento degli slot', 'error');
				scrollToStep(slotStep);
			}
		});
	}

	function renderSlotButtons(slots) {
		let html = '';
		const selectedSlot = manageForm.length ? slotInput.val() : '';

		slots.forEach(function(slot) {
			const slotTime = escapeHtml(slot.time);
			const availabilityLabel = slot.is_current ? 'orario attuale' : (slot.available_spots === 1 ? '1 disponibilità' : slot.available_spots + ' disponibilità');
			const isSelected = selectedSlot === slot.time;

			html += '<button type="button" class="booking-slot-button' + (isSelected ? ' selected' : '') + '" data-slot="' + slotTime + '" aria-pressed="' + (isSelected ? 'true' : 'false') + '">';
			html += '<span class="booking-slot-time">' + slotTime + '</span>';
			html += '<span class="booking-slot-spots">' + availabilityLabel + '</span>';
			html += '</button>';
		});

		if (selectedSlot && slots.some(function(slot) { return slot.time === selectedSlot; })) {
			slotInput.val(selectedSlot);
			selectedSummary.text(formatDisplayDate(dateInput.val()) + ' alle ' + selectedSlot);
		} else {
			slotInput.val('');
		}

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
		scrollToStep(detailsStep);
	});

	function showStep(step) {
		step.prop('hidden', false).addClass('is-active');
	}

	function hideStep(step) {
		step.prop('hidden', true).removeClass('is-active');
	}

	function scrollToStep(step) {
		if (!step.length || step.prop('hidden')) {
			return;
		}

		window.setTimeout(function() {
			const offset = 24;
			const top = step.offset().top - offset;

			window.scrollTo({
				top: top,
				behavior: 'smooth'
			});
		}, 120);
	}

	function blurCalendarFocus() {
		if (calendar && calendar._input) {
			calendar._input.blur();
		}

		dateInput.blur();

		if (document.activeElement && typeof document.activeElement.blur === 'function') {
			document.activeElement.blur();
		}
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

		if (!$('#privacy-consent').is(':checked')) {
			showMessage('Accetta l\'informativa privacy per completare la prenotazione.', 'error');
			scrollToStep(detailsStep);
			return;
		}

		const formData = {
			booking_date: $('#booking-date').val(),
			time_slot: slotInput.val(),
			client_name: $('#client-name').val(),
			client_surname: $('#client-surname').val(),
			client_gender: $('#client-gender').val(),
			client_section: $('#client-section').val(),
			client_email: $('#client-email').val(),
			client_phone: $('#client-phone').val(),
			privacy_consent: $('#privacy-consent').is(':checked') ? '1' : ''
		};

		showLoadingState();

		$.ajax({
			url: BookingData.rest_url + 'reserve',
			type: 'POST',
			data: JSON.stringify(formData),
			headers: {
				'Content-Type': 'application/json'
			},
			success: function(response) {
				if (response.success) {
					showSuccessState(formData, response.message);
				} else {
					showFormState();
					showErrorPopup('Errore durante la prenotazione. Riprova tra qualche istante.');
				}
			},
			error: function(xhr) {
				let errorMsg = 'Errore durante la prenotazione';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMsg = xhr.responseJSON.message;
				}
				showFormState();
				showErrorPopup(errorMsg);
			}
		});
	});

	manageForm.on('submit', function(e) {
		e.preventDefault();

		if (!slotInput.val()) {
			showMessage('Seleziona un orario disponibile.', 'error');
			return;
		}

		showLoadingState();

		$.ajax({
			url: BookingData.rest_url + 'reschedule',
			type: 'POST',
			data: JSON.stringify({
				token: managementToken,
				booking_date: dateInput.val(),
				time_slot: slotInput.val()
			}),
			headers: {
				'Content-Type': 'application/json'
			},
			success: function(response) {
				if (response.success) {
					showManagementSuccess(response.message || 'La prenotazione e stata riprogrammata correttamente.');
				} else {
					showFormState();
					showErrorPopup('Errore durante la riprogrammazione. Riprova tra qualche istante.');
				}
			},
			error: function(xhr) {
				let errorMsg = 'Errore durante la riprogrammazione';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMsg = xhr.responseJSON.message;
				}
				showFormState();
				showErrorPopup(errorMsg);
			}
		});
	});

	$(document).on('click', '#booking-cancel-reservation', function() {
		showCancelConfirmPopup();
	});

	$(document).on('click', '#booking-confirm-cancel-action', function() {
		hideCancelConfirmPopup();
		cancelReservation();
	});

	newReservationButton.on('click', function() {
		if (manageForm.length) {
			window.location.href = window.location.href.split('?')[0];
			return;
		}

		resetBookingForm();
		showFormState();
		loadAvailableDates();
	});

	function showLoadingState() {
		message.hide().stop(true, true);
		formHeader.prop('hidden', true);
		form.prop('hidden', true);
		manageForm.prop('hidden', true);
		successState.prop('hidden', true);
		loadingState.prop('hidden', false);
	}

	function showSuccessState(formData, responseMessage) {
		loadingState.prop('hidden', true);
		formHeader.prop('hidden', true);
		form.prop('hidden', true);
		successMessage.text(responseMessage || 'La tua prenotazione è stata registrata correttamente.');
		successWhen.text(formatDisplayDate(formData.booking_date) + ' alle ' + formData.time_slot);
		successStudent.text($.trim(formData.client_name + ' ' + formData.client_surname));
		successContact.text(formData.client_email + ' - ' + formData.client_phone);
		successState.prop('hidden', false);
	}

	function showManagementSuccess(responseMessage) {
		loadingState.prop('hidden', true);
		formHeader.prop('hidden', true);
		manageForm.prop('hidden', true);
		successMessage.text(responseMessage);
		successState.prop('hidden', false);
	}

	function showFormState() {
		loadingState.prop('hidden', true);
		successState.prop('hidden', true);
		formHeader.prop('hidden', false);
		form.prop('hidden', false);
		manageForm.prop('hidden', false);
	}

	function resetBookingForm() {
		form[0].reset();
		if (calendar) {
			calendar.clear();
		}
		resetSlots('Seleziona una data per vedere gli orari disponibili');
		selectedDateLabel.text('-');
		hideStep(slotStep);
		hideStep(detailsStep);
	}

	function showErrorPopup(text) {
		errorText.text(text || 'Non siamo riusciti a completare la prenotazione. Riprova tra qualche istante.');
		errorModal.prop('hidden', false);
		$('body').addClass('booking-modal-open');
	}

	function hideErrorPopup() {
		errorModal.prop('hidden', true);
		$('body').removeClass('booking-modal-open');
	}

	function showCancelConfirmPopup() {
		$('#booking-confirm-cancel-modal').prop('hidden', false).removeAttr('hidden');
		$('body').addClass('booking-modal-open');
	}

	function hideCancelConfirmPopup() {
		$('#booking-confirm-cancel-modal').prop('hidden', true).attr('hidden', 'hidden');
		$('body').removeClass('booking-modal-open');
	}

	function cancelReservation() {
		showLoadingState();

		$.ajax({
			url: BookingData.rest_url + 'cancel',
			type: 'POST',
			data: JSON.stringify({
				token: managementToken
			}),
			headers: {
				'Content-Type': 'application/json'
			},
			success: function(response) {
				if (response.success) {
					showManagementSuccess(response.message || 'La prenotazione e stata cancellata correttamente.');
				} else {
					showFormState();
					showErrorPopup('Errore durante la cancellazione. Riprova tra qualche istante.');
				}
			},
			error: function(xhr) {
				let errorMsg = 'Errore durante la cancellazione';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMsg = xhr.responseJSON.message;
				}
				showFormState();
				showErrorPopup(errorMsg);
			}
		});
	}

	errorModal.on('click', '[data-booking-error-close]', function() {
		hideErrorPopup();
	});

	confirmCancelModal.on('click', '[data-booking-confirm-close]', function() {
		hideCancelConfirmPopup();
	});

	$(document).on('keydown', function(e) {
		if (e.key === 'Escape' && !errorModal.prop('hidden')) {
			hideErrorPopup();
		}

		if (e.key === 'Escape' && !confirmCancelModal.prop('hidden')) {
			hideCancelConfirmPopup();
		}
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
