jQuery(document).ready(function($) {
	// Handle tab switching
	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		const tab = $(this).attr('href');
		
		// Remove active class from all tabs and contents
		$('.nav-tab').removeClass('nav-tab-active');
		$('.tab-content').removeClass('active');
		
		// Add active class to clicked tab
		$(this).addClass('nav-tab-active');
		
		// Show corresponding content
		$(tab).addClass('active');
	});

	// Form validation for blocked specific dates
	$('#blocked_specific_dates').on('blur', function() {
		const dates = $(this).val().split('\n').filter(d => d.trim());
		const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
		
		dates.forEach(date => {
			if (!dateRegex.test(date.trim())) {
				alert('Formato data non valido: ' + date + '\nUsare formato YYYY-MM-DD');
			}
		});
	});

	// Auto-format dates
	$('#availability_start_date, #availability_end_date').on('change', function() {
		const startDate = $('#availability_start_date').val();
		const endDate = $('#availability_end_date').val();
		
		if (startDate && endDate && startDate > endDate) {
			alert('La data di inizio deve essere prima della data di fine!');
			$(this).val('');
		}
	});

	// Time validation
	$('#first_slot_time, #last_slot_time').on('change', function() {
		const firstSlot = $('#first_slot_time').val();
		const lastSlot = $('#last_slot_time').val();
		
		if (firstSlot && lastSlot && firstSlot >= lastSlot) {
			alert('L\'orario di inizio deve essere prima dell\'orario di fine!');
			$(this).val('');
		}
	});
});
