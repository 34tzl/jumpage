(function() {

	$(document).ready(function() {
		
		$('a[href=#top]').click(function() {
			$('html, body').animate({
				scrollTop : 0
			}, 'slow');
			return false;
		});
		
		$('.buttons a').hover(function() {
			var img = $(this).children('img');
			var src = img.attr('src').replace('.png','');
			
			img.attr('src', src + '-drk.png');
			
		}, function() {
			var img = $(this).children('img');
			var src = img.attr('src').replace('-drk','');
			
			img.attr('src', src);
			
		});
		
	});

})(jQuery);