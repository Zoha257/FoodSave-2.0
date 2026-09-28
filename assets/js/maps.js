// FoodSave Google Maps helpers.
// The API key is injected by the PHP page through the Google Maps script tag.
let foodSaveMap = null;
let foodSaveDirectionsService = null;
let foodSaveDirectionsRenderer = null;
let foodSaveDistanceMatrixService = null;

function initializeServices() {
    if (!window.google || !google.maps) return false;
    foodSaveDirectionsService = new google.maps.DirectionsService();
    foodSaveDirectionsRenderer = new google.maps.DirectionsRenderer({ suppressMarkers: true });
    foodSaveDistanceMatrixService = new google.maps.DistanceMatrixService();
    return true;
}

function initMapWithAddress(mapElementId, address, title) {
    const el = document.getElementById(mapElementId);
    if (!el || !window.google || !google.maps) return;
    new google.maps.Geocoder().geocode({address: address}, (results,status) => {
        if (status !== 'OK' || !results[0]) { el.innerHTML='<div class="alert alert-warning">Unable to locate this address.</div>'; return; }
        const map = new google.maps.Map(el,{zoom:15,center:results[0].geometry.location,streetViewControl:false,fullscreenControl:true});
        new google.maps.Marker({map,position:results[0].geometry.location,title:title||''});
    });
}

function initMapWithCoords(mapElementId, lat, lng, title) {
    const el=document.getElementById(mapElementId); if(!el||!window.google||!google.maps)return;
    const position={lat:Number(lat),lng:Number(lng)};
    const map=new google.maps.Map(el,{zoom:15,center:position,streetViewControl:false,fullscreenControl:true});
    new google.maps.Marker({map,position,title:title||''});
}

function initMapWithMultipleLocations(mapElementId, locations) {
    const el=document.getElementById(mapElementId); if(!el||!window.google||!google.maps)return;
    const map=new google.maps.Map(el,{zoom:5,center:{lat:20.5937,lng:78.9629},streetViewControl:false,fullscreenControl:true});
    const bounds=new google.maps.LatLngBounds();
    const geocoder=new google.maps.Geocoder();
    (locations||[]).forEach(location=>geocoder.geocode({address:location.address},(results,status)=>{
        if(status==='OK'&&results[0]){const pos=results[0].geometry.location;new google.maps.Marker({map,position:pos,title:location.title||''});bounds.extend(pos);map.fitBounds(bounds);}
    }));
    foodSaveMap=map;
}

function getCurrentLocation(callback) {
    if (!navigator.geolocation) { callback(null); return; }
    navigator.geolocation.getCurrentPosition(p=>callback({lat:p.coords.latitude,lng:p.coords.longitude}),()=>callback(null));
}

function calculateAndDisplayRoute(map, origin, destination, infoWindow) {
    if(!foodSaveDirectionsService) initializeServices();
    if(!foodSaveDirectionsService)return;
    foodSaveDirectionsRenderer.setMap(map);
    foodSaveDirectionsService.route({origin,destination,travelMode:google.maps.TravelMode.DRIVING},(response,status)=>{
        if(status==='OK'){foodSaveDirectionsRenderer.setDirections(response);if(infoWindow&&response.routes[0].legs[0]){const leg=response.routes[0].legs[0];infoWindow.setContent((infoWindow.getContent()||'')+`<div class="route-info"><b>Distance:</b> ${leg.distance.text}<br><b>Estimated time:</b> ${leg.duration.text}</div>`);}}
    });
}

function calculateDistancesToDonations(userLocation, locations, callback) {
    if(!foodSaveDistanceMatrixService)initializeServices();
    if(!foodSaveDistanceMatrixService){callback([]);return;}
    foodSaveDistanceMatrixService.getDistanceMatrix({origins:[userLocation],destinations:(locations||[]).map(x=>x.address),travelMode:google.maps.TravelMode.DRIVING,unitSystem:google.maps.UnitSystem.METRIC},(response,status)=>{
        if(status!=='OK'){callback([]);return;}
        const distances=response.rows[0].elements.map((e,i)=>({location:locations[i],distance:e.distance?e.distance.text:'N/A',duration:e.duration?e.duration.text:'N/A'}));
        callback(distances);
    });
}

function showNearbyDonations(map,userLocation,radiusKm) {
    return new google.maps.Circle({map,center:userLocation,radius:Number(radiusKm)*1000,fillOpacity:0.1,strokeWeight:1});
}
