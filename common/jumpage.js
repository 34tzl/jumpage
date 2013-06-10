(function() {
	
	var map = myLatLng = null;
	
	function _is_touch_device()
	{
		return !!('ontouchstart' in window);
	}
	
	function _is_mobile_device()
	{
		return $('div.mobile:last').css('display') == 'block';
	}
	
	function _setWallPosts()
	{
		if(_is_mobile_device()) return false;
		
		var col_w = (parseInt($('.wall').width()/2)-12);
		var col_t = col_l = el_h = 0;
		var col_h = [0,0];
		
		$('.post').css({
			position: 'absolute',
			display: 'block',
			width: col_w + 'px',
			float: 'none',
			clear: 'both'
		}).each(function(i, el){
			
			el = $(el);
			
			el_h = el.outerHeight();
			
			if(col_h[0] > col_h[1])
			{
				
				col_t = col_h[1] + 'px';
				col_h[1] += el_h + 24;
				
				col_l = col_w + 48;
				
			}
			else
			{
				
				col_t = col_h[0] + 'px';
				col_h[0] += el_h + 24;
				
				col_l = 24;
			}
			
			el.css({
				top: col_t,
				left: col_l
			});
			
		});
		
		if(col_h[0] > col_h[1])
		{
			el_h = col_h[0];
		}
		else
		{
			el_h = col_h[1];
		}
		
		el_h = (el_h) + 'px';
		
		$('.wall').height(el_h);
		
	}
	
	$(document).ready(function() {
		
		$('a[href=#top]').click(function() {
			$('html, body').animate({
				scrollTop : 0
			}, 'slow');
			return false;
		});
		
		if($('#google_maps').length > 0)
		{
			_initMaps();
		}
		
	});
	
	$(window).load(function() {
		_setWallPosts();
	});
	
	$(window).resize(function(){
		if($('#google_maps').length > 0)
		{
			_resizeMaps();
		}
		_setWallPosts();
		
	});
	
	function _initMaps()
	{
		var mapStyle = [{
            "featureType": "road.highway",
            "elementType": "geometry",
            "stylers": [
              { "color": "#999999" }
            ]
          },{
            "featureType": "road",
            "elementType": "labels.text.stroke",
            "stylers": [
              { "color": "#ffffff" }
            ]
          },{
            "featureType": "road.arterial",
            "elementType": "geometry",
            "stylers": [
              { "color": "#999999" }
            ]
          },{
            "featureType": "road",
            "elementType": "labels.icon",
            "stylers": [
              { "saturation": -100 }
            ]
          },{
            "featureType": "transit",
            "elementType": "labels.icon",
            "stylers": [
              { "saturation": -100 }
            ]
          },{
            "featureType": "transit.line",
            "stylers": [
              { "color": "#808080" }
            ]
          },{
            "featureType": "landscape",
            "stylers": [
              { "visibility": "off" }
            ]
          },{
            "featureType": "water",
            "elementType": "geometry",
            "stylers": [
              { "color": googleMapsConfig['color'] }
            ]
          },{
          },{
            "featureType": "poi",
            "stylers": [
              { "visibility": "off" }
            ]
          },{
            "featureType": "landscape",
            "elementType": "geometry.fill",
            "stylers": [
              { "visibility": "on" },
              { "color": "#eeeeee" }
            ]
          }
        ];
		
		var lat = parseFloat(googleMapsConfig['Lat']);
		var lng = parseFloat(googleMapsConfig['Lng']);
		
		var latlngset = lat > 0 && lng > 0;
		
		if(latlngset)
		{
			myLatLng = new google.maps.LatLng(lat, lng);
		}
		
		var myOptions = {
			zoom: googleMapsConfig['zoom'],
			streetViewControl: !_is_touch_device(),
			mapTypeControl: false,
			navigationControl: true,
			navigationControlOptions: {
				style : google.maps.NavigationControlStyle.DEFAULT
			},
			center: myLatLng,
			scaleControl: true,
			scrollwheel: false,
			draggable: !_is_touch_device(), //$('body').width() > 560,
			disableDoubleClickZoom: true,
			mapTypeId: 'jumpagestyle',
			disableDefaultUI : true
		};
		
		map = new google.maps.Map(document.getElementById("google_maps"), myOptions);
		
		var _setMarker = function(position)
		{
			var icon = new google.maps.MarkerImage('common/marker.png',
				new google.maps.Size(24,32), new google.maps.Point(0,0),
				new google.maps.Point(12,32));
			
			var marker = new google.maps.Marker({
				position: position,
				map: map,
				icon: icon,
				title: googleMapsConfig['title']
			});
			
			if(googleMapsConfig['href'] == '')
			{
				googleMapsConfig['href'] = 'http://maps.google.com/maps?q=' 
					+ encodeURI(googleMapsConfig['address']) 
					+ '&ll=' + position.lat() + ',' + position.lng();
			}
			
			google.maps.event
				.addListener(marker, 'click', function() {
				location.href = googleMapsConfig['href'];
			});
			
			_resizeMaps();
		};
		
		
		if(latlngset)
		{
			_setMarker(myLatLng);
		}
		else
		{
			var geocoder = new google.maps.Geocoder();
		
			if (geocoder) {
				geocoder.geocode({
					'address' : googleMapsConfig['address']
				},
				function(results, status) {
					
					if(status == google.maps.GeocoderStatus.OK)
					{
						myLatLng = results[0].geometry.location;
					}
					
					_setMarker(myLatLng);
				});
			}
		}
		
		var jpMapType = new google.maps.StyledMapType(mapStyle, {name : 'jumpage'});
		map.mapTypes.set('jumpagestyle', jpMapType);
		
		window.map = map;
		
//		$(document).google = google;
//		$(document).map = map;
		
		_resizeMaps();
	}
	
	function _resizeMaps()
	{
		var w = $('#google_maps').width();
		var h = parseInt(w/2);
		
		$('#google_maps').height(h);
		
		if(map)
		{
			map.setCenter(myLatLng);
		}
	}
	

})(jQuery);