jQuery(document).ready(function($){
	p2_hovercardify();

	// When new posts are loaded, hovercardify them
	$('#main').ajaxSuccess(function(e, xhr, settings){
		console.log( $.parseJSON(xhr.responseText) );
		if ( xhr.responseText.length > 0 && $( xhr.responseText ).attr('class') != 'p2-hovercard' )
			p2_hovercardify();
	});

	function p2_hovercardify() {
		$('.p2_hovercardify').one('scrollin', function() {
			// bail if the card is already loaded
			if ( $(this).find('.p2-hovercard:first').length > 0 )
				return;

			// ajax request
			p2_hovercards_requestCard( this );
		});
	}

	function p2_hovercards_requestCard( object ) {
		var $box = $(object),
		    $a = $box.find('a:first');

		if( $a.hasClass('nocard') )
			return;

		// show spin.js spinner
		var opts = {
			lines: 9,
			length: 0,
			width: 2,
			radius: 4,
			left: 0,
			zIndex: 1
		};
		$box.spin( opts );

		var data = {
			action: 'load_p2_hovercards',
			slug: $a.text(),
			url: $a.attr('href')
		};

		$.post(ajaxUrl, data, function(r){
			$box.append(r);
		}).success(function(r) {
			// hide spinner
			$box.spin( false );

			if ( r.length == 0 )
				$a.addClass('nocard');
		});
	}
});