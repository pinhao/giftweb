<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<meta charset="utf-8"> 
<style type="text/css">
  html { height: 100% }
  body { height: 100%; margin: 0; padding: 0 }
  #map_canvas { height: 100% }
</style>
<script type="text/javascript"
    src="http://maps.googleapis.com/maps/api/js?sensor=false">
</script>
<script type="text/javascript">
function bindInfoWindow(marker, map, infoWindow, html) {
  google.maps.event.addListener(marker, 'click', function() {
    infoWindow.setContent(html);
    infoWindow.open(map, marker);
  });
}

function to_radians(degrees) {
	return (degrees*(Math.PI/180));
}

function to_degrees(radians) {
	return (radians*(180/Math.PI));
}

function initialize() {
    var centerLocation = new google.maps.LatLng(<?php echo $center['latitude'];?>, <?php echo $center['longitude'];?>);
    var myOptions = {
      zoom: 17,
      center: centerLocation,
      mapTypeId: google.maps.MapTypeId.HYBRID
    };
    var map = new google.maps.Map(document.getElementById("map_canvas"),
        myOptions);
	var infoWindow = new google.maps.InfoWindow;
	
	var markers = new Array();
	<?php foreach ($markers as $marker):?>
	markers.push(new google.maps.Marker({
		position: new google.maps.LatLng(<?php echo $marker['latitude'];?>, <?php echo $marker['longitude'];?>), 
		map: map,
		title: '<?php echo $marker['name'];?><br/><?php echo $marker['acronym'];?><br/><?php echo $marker['id'];?>'
	}));
	<?php endforeach;?>
	for (var i = 0; i < markers.length; i++) {
    	bindInfoWindow(markers[i], map, infoWindow, markers[i].title);
	}
	
	var distance = <?php echo $distance; ?>;
	if ( distance != -1 ) {
	var r = ((distance / Math.sqrt(2)) / 6367);
	console.log(centerLocation);
	var deltaLon = Math.asin(Math.sin(r)/Math.abs(Math.cos(to_radians(centerLocation.lat()))));
	var latMin = to_degrees(to_radians(centerLocation.lat()) - r);
	var latMax = to_degrees(to_radians(centerLocation.lat()) + r);
	var lonMin = centerLocation.lng() - to_degrees(deltaLon);
	var lonMax = centerLocation.lng() + to_degrees(deltaLon);
	var min = new google.maps.LatLng(latMin, lonMin);
	var max = new google.maps.LatLng(latMax, lonMax);
	var rectBounds = new google.maps.LatLngBounds(min, max);
	var rectOptions = {
	      strokeColor: "#00FF00",
	      strokeOpacity: 0.8,
	      strokeWeight: 2,
	      fillColor: "#00FF00",
	      fillOpacity: 0.35,
	      map: map,
	      bounds: rectBounds
	    };
	var circleOptions = {
	      strokeColor: "#FF0000",
	      strokeOpacity: 0.8,
	      strokeWeight: 2,
	      fillColor: "#FF0000",
	      fillOpacity: 0.35,
	      map: map,
	      center: centerLocation,
	      radius: distance * 1000
	    };
	new google.maps.Circle(circleOptions);
	new google.maps.Rectangle(rectOptions);
	}
}
</script>
</head>
<body onload="initialize()">
  <div id="map_canvas" style="width:100%; height:100%"></div>
</body>
</html>