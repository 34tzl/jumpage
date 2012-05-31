(function() {

	$(document).ready(function() {
		
		$('a[href=#top]').click(function() {
			$('html, body').animate({
				scrollTop : 0
			}, 'slow');
			return false;
		});
		
//		_initMaps();
		
	});

	function _initMaps() {
		var mapStyle = [ {
			featureType : "water",
			stylers : [ {
				invert_lightness : true
			}, {
				hue : "#0099ff"
			}, {
				lightness : 6
			}, {
				saturation : 58
			}, {
				gamma : 2.00
			} ]
		}, {
			featureType : "landscape.natural",
			elementType : "geometry",
			stylers : [ {
				visibility : "on"
			}, {
				hue : "#00ff55"
			}, {
				saturation : -98
			}, {
				lightness : 16
			} ]
		}, {
			featureType : "poi",
			stylers : [ {
				visibility : "off"
			} ]
		}, {
			featureType : "road.highway",
			stylers : [ {
				hue : "#9000ff"
			}, {
				saturation : -98
			}, {
				lightness : 25
			} ]
		}, {
			featureType : "road.local",
			stylers : [ {
				lightness : 39
			} ]
		}, {
			featureType : "road.arterial",
			elementType : "geometry",
			stylers : [ {
				lightness : 70
			}, {
				gamma : 0.4
			}, {
				saturation : -99
			} ]
		}, {
			featureType : "road.highway",
			elementType : "geometry",
			stylers : [ {
				invert_lightness : true
			}, {
				hue : "#0066ff"
			}, {
				saturation : -97
			}, {
				gamma : 1.16
			}, {
				lightness : 76
			} ]
		}, {
			featureType : "road.arterial",
			elementType : "labels",
			stylers : [ {
				saturation : -98
			}, {
				lightness : 18
			}, {
				gamma : 1.16
			} ]
		}, {
			featureType : "road.local",
			elementType : "labels",
			stylers : [ {
				lightness : 2
			}, {
				saturation : -99
			} ]
		} ];

		var myLatlng = new google.maps.LatLng(50.9744664, 7.0035533); /* TODO get coordinates from markup */

		var geocoder = new google.maps.Geocoder();

		var myOptions = {
			zoom : 16,
			streetViewControl : true,
			mapTypeControl : false,
			navigationControl : true,
			navigationControlOptions : {
				style : google.maps.NavigationControlStyle.DEFAULT
			},
			scaleControl : false,
			scrollwheel: false,
			draggable: true,
			mapTypeId : 'mystyle',
			disableDefaultUI : false
		};
		
		var map = new google.maps.Map(document.getElementById("map"), myOptions);

		if (geocoder) {
			geocoder.geocode({
				'address' : 'Von-Lohe-Str. 7, 51063, Köln' /* TODO get address from markup */
			},
			function(results, status) {
				if (status == google.maps.GeocoderStatus.OK) {
					map.setCenter(results[0].geometry.location);
				} else {
					map.setCenter(myLatlng);
				}
			});
		}

		var styledMapOptions = {
			name : "bleen"
		};

		var wyMapType = new google.maps.StyledMapType(mapStyle, styledMapOptions);
		map.mapTypes.set('mystyle', wyMapType);

		var image = new google.maps.MarkerImage('common/marker.png',
				new google.maps.Size(32, 32), new google.maps.Point(0, 0),
				new google.maps.Point(0,32));
		
		var marker = new google.maps.Marker({
			position : myLatlng,
			map : map,
			icon : image
		});

		google.maps.event
			.addListener(marker, 'click', function() {
			// map.setCenter(myLatlng);
			location.href = 'http://goo.gl/maps/iWYY'; /* TODO get google places link from jumpage config */
		});

	}

})(jQuery);