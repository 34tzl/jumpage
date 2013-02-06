(function() {
	
	var map = myLatLng = null;
	
	function _is_touch_device() {
		return !!('ontouchstart' in window);
	}
	
	$(document).ready(function() {
		
		$('a[href=#top]').click(function() {
			$('html, body').animate({
				scrollTop : 0
			}, 'slow');
			return false;
		});
		
		$('.buttons a').bind('mouseenter', function() {
			var img = $(this).children('img');
			var src = img.attr('src').replace('.png','');
			
			img.attr('src', src + '-drk.png');
			
		}).bind('mouseleave mouseup', function() {
			var img = $(this).children('img');
			var src = img.attr('src').replace('-drk','');
			
			img.attr('src', src);
			
		});
		
		if($('#google_maps').length > 0)
		{
			_initMaps();
		}
		
		
	});
	
	$(window).resize(function(){
		if($('#google_maps').length > 0)
		{
			_resizeMaps();
		}
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
              { "color": "#2e9cd5" }
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
				new google.maps.Size(20,34), new google.maps.Point(0,0),
				new google.maps.Point(10,34));
			
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
		
		var styledMapOptions = {
			name : "jumpage"
		};

		var wyMapType = new google.maps.StyledMapType(mapStyle, styledMapOptions);
		map.mapTypes.set('jumpagestyle', wyMapType);
		
	}
	
	function _resizeMaps()
	{
		var w = $('#google_maps').width();
		var h = parseInt(w/3);
		
		$('#google_maps').height(h);
		
		if(map)
		{
			map.setCenter(myLatLng);
		}
	}
	

})(jQuery);