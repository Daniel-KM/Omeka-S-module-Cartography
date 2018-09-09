/* Cartography */

// The base code comes initially from Mapping/asset/js/mapping-show.js in order
// to keep standard markers., but now the dependency has been removed.
// TODO Check if some code is dead or useless.
// TODO The automatic bounds should be managed to fit existing geometries (or let the user set it and store it like in mapping? or full map).
// TODO Clarify use of the annotation identifier.

$(document).ready( function() {

/**
 * Add a geometry to the map.
 *
 * @param layer
 * @param identifier
 */
var addGeometry = function(layer, identifier) {
    // Don't save the geometry two times.
    if (identifier) {
        layer.annotationIdentifier = identifier;
        layer.options.annotationIdentifier = identifier;
        // drawnItems.addLayer(layer);
        addNonGroupLayers(layer, drawnItems);
        layer.annotationIdentifier = identifier;
        layer.options.annotationIdentifier = identifier;
        return;
    }
}

/**
 * Allows to create editable layer from existing geometries.
 *
 * @see https://gis.stackexchange.com/questions/203540/how-to-edit-an-existing-layer-using-leaflet/203773#203773
 * @todo Fix: the existing groups are not draggable (but the newly created are).
 */
var addNonGroupLayers = function(sourceLayer, targetGroup) {
    if (sourceLayer instanceof L.LayerGroup) {
        sourceLayer.eachLayer(function (layer) {
            addNonGroupLayers(layer, targetGroup);
        });
    } else {
        targetGroup.addLayer(sourceLayer);
        sourceLayer.annotationIdentifier = sourceLayer.options.annotationIdentifier;
    }
}

/**
 * Fit map bounds.
 *
 * @todo Fit map bounds.
 */
var setView = function() {
    if (defaultBounds) {
        map.fitBounds(defaultBounds);
    } else {
//        var bounds = markers.getBounds();
//        if (bounds.isValid()) {
//            map.fitBounds(bounds);
//        } else {
//            map.setView([20, 0], 2)
//        }
    }
};

/* Initialization */

 // Get map data.
var mappingMap = $('#cartography-map');
// Geometries are currently defined as a simple variable.
// TODO Fetch existing values instead of reading.
var geometriesData = geometries;

//Initialize the map and set default view.
var map = L.map('cartography-map', { pasteControl: true });
map.setView([20, 0], 2);
var mapMoved = false;

// TODO Create automatically the bounds from geometries.
var defaultBounds = null;
// defaultBounds = [southWest, northEast];

// Add layers and controls to the map.
var baseMaps = {
    'Streets': L.tileLayer.provider('OpenStreetMap.Mapnik'),
    'Grayscale': L.tileLayer.provider('OpenStreetMap.BlackAndWhite'),
    'Satellite': L.tileLayer.provider('Esri.WorldImagery'),
    'Terrain': L.tileLayer.provider('Esri.WorldShadedRelief')
};
var layerControl = L.control.layers(baseMaps);
// Geometries are displayed and edited on the drawnItems layer.
var drawnItems = new L.FeatureGroup();
// TODO Remove all references to markers of the standard mapping map.
//var markers = new L.FeatureGroup();
var geoSearchControl = new window.GeoSearch.GeoSearchControl({
    provider: new window.GeoSearch.OpenStreetMapProvider,
    showMarker: false,
    retainZoomLevel: true,
});
// map.addControl(layerControl);
map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));
//map.addControl(geoSearchControl);
map.addControl(new L.Control.Layers(baseMaps));
// TODO Fix and add the fit bound control.
//map.addControl(new L.Control.FitBounds(markers));

map.addLayer(baseMaps['Satellite']);
map.addLayer(drawnItems);
// map.addLayer(markers);

map.on('paste:layer-created', function (e) {
    map.addLayer(e.layer);
});

setView();

/* Manage edition of geometries. */

// Handle existing geometries.
$.each(geometriesData, function(index, data) {
    var geojson = Terraformer.WKT.parse(data['wkt']);
    var options = data['options'] ? data['options'] : {};
    options.annotationIdentifier = data['id'];
    var layer = L.geoJson(geojson, options);
    addGeometry(layer, data['id']);
    layer.annotationIdentifier = data['id'];
    layer.options.annotationIdentifier = data['id'];
});

/* Various methods. */

// Switching sections changes map dimensions, so make the necessary adjustments.
$('#cartography').one('o:section-opened', function(e) {
    map.invalidateSize();
    setView();
});

});
