(function($) {
	$(document).ready(function() {

		$('#sel').change(function() {
			$('#note').toggleClass('hidden');
		});

	});
})(jQuery.noConflict());