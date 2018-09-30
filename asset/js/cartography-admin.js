/* Cartography */

// The base code comes initially from Mapping/asset/js/mapping-show.js in order
// to keep standard markers., but now the dependency has been removed.
// TODO Check if some code is dead or useless.
// TODO The automatic bounds should be managed to fit existing geometries (or let the user set it and store it like in mapping?) or full map.
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

    var geojson = layer.toGeoJSON();
    var wkt;
    // Check the process of terraformer wkt convert / reconvert (Feature/FeatureCollection).
    if (geojson.features && geojson.features[0].geometry) {
        wkt = Terraformer.WKT.convert(geojson.features[0].geometry);
    } else {
        wkt = Terraformer.WKT.convert(geojson.geometry);
    }

    var url = basePath + '/admin/cartography/annotate';
    var data = {
        // Identifier is always empty.
        id : identifier,
        resourceId: resourceId,
        // TODO Use "locating" (not standard) as motivation?
        motivation: 'highlighting',
        wkt: wkt,
        // Options are saved only when updated: some people don't need styles
        // so it will be lighter in that case.
        // options: layer.options
    };

    $.post(url, data,
    function(data, textStatus, jqxhr) {
        // No json means error, and the only non-json error is redirect to login.
        if (!data.result) {
            alert('Log in to save the geometry.');
            return;
        }
        identifier = data.result.id;
        layer.annotationIdentifier = identifier;
        layer.options.annotationIdentifier = identifier;
        drawnItems.addLayer(layer);
    })
    .fail(function(jqxhr) {
        var message = JSON.parse(jqxhr.responseText).message || 'Unable to save the geometry.';
        // The deletion is automatic when not recorded.
    });
};

/**
 * Edit a geometry.
 *
 * @param layer
 */
var editGeometry = function(layer) {
    identifier = layer.annotationIdentifier;
    identifier2 = layer.options.annotationIdentifier;
    if (!identifier) {
        alert('Unable to save the edited geometry.');
        return;
    }

    var geojson = layer.toGeoJSON();
    var wkt;
    // Check the process of terraformer wkt convert / reconvert (Feature/FeatureCollection).
    if (geojson.features && geojson.features[0].geometry) {
        wkt = Terraformer.WKT.convert(geojson.features[0].geometry);
    } else {
        wkt = Terraformer.WKT.convert(geojson.geometry);
    }

    var url = basePath + '/admin/cartography/annotate';
    var data = {
        id: identifier,
        wkt: wkt,
        // Note: motivation is not udpatable.
        motivation: 'highlighting',
        options: layer.options
    };

    $.post(url, data,
    function(data, textStatus, jqxhr) {
        // Not json means error, and the only non-json error is redirect to login.
        if (!data.result) {
            alert('Log in to edit the geometry.');
            return;
        }
        console.log('Geometry updated.');
//        identifier = data.result.id;
//        drawnItems.addLayer(layer);
    })
    .fail(function(jqxhr) {
        var message = JSON.parse(jqxhr.responseText).message || 'Unable to update the geometry.';
        alert(message);
    });
}

/**
 * Delete a geometry.
 *
 * @param layer
 */
var deleteGeometry = function(layer) {
    var url = basePath + '/admin/cartography/delete-annotation';
    console.log(layer);
    var identifier = layer.annotationIdentifier;
    var identifier2 = layer.options.annotationIdentifier;
    if (!identifier) {
        console.log('No identifier to delete.')
        return;
    }

    $.post(url, {id: identifier},
    function(data, textStatus, jqxhr) {
        // Not json means error, and the only non-json error is redirect to login.
        if (!data.result) {
            alert('Log in to delete the geometry.');
            return;
        }
        console.log('Geometry deleted.')
//        drawnItems.removeLayer(layer);
    })
    .fail(function(jqxhr) {
        var message = JSON.parse(jqxhr.responseText).message || 'Unable to delete the geometry.';
        alert(message);
    });
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
var map = L.map('cartography-map');
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
var drawControl = new L.Control.Draw({
    draw: {
        polyline: true,
        polygon: true,
        rectangle: true,
        circle: true,
        circlemarker: true
    },
    edit: {
        featureGroup: drawnItems,
        remove: true
    }
});
// map.addControl(layerControl);
map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));
map.addControl(drawControl);
map.addControl(geoSearchControl);
map.addControl(new L.Control.Layers(baseMaps));
// TODO Fix and add the fit bound control with geometries, not markers.
//map.addControl(new L.Control.FitBounds(markers));

map.addLayer(baseMaps['Satellite']);
map.addLayer(drawnItems);
// map.addLayer(markers);

setView();

/* Style Editor (https://github.com/dwilhelm89/Leaflet.StyleEditor) */
// Initialize the StyleEditor
var styleEditor = L.control.styleEditor({
    // position: 'topleft',
    // colorRamp: ['#1abc9c', '#2ecc71', '#3498db'],
    // markers: ['circle-stroked', 'circle', 'square-stroked', 'square'],
    strings: {
        // Only cancel is updated.
        cancel: 'Finish',
        cancelTitle: 'Cancel Styling',
        tooltip: 'Click on the element you want to style',
        tooltipNext: 'Choose another element you want to style'
    },
    useGrouping: false,
});
map.addControl(styleEditor);

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

//Handle adding new geometries.
map.on(L.Draw.Event.CREATED, function (element) {
    addGeometry(element.layer);
});

// Handle editing geometries (when the edit button "save" is clicked).
map.on(L.Draw.Event.EDITED, function(element) {
    // TODO Check if options changed to avoid to save default ones.
    element.layers.eachLayer(function(layer) {
        editGeometry(layer);
    });
});

// Handle deleting geometries (when the delete button "save" is clicked).
map.on(L.Draw.Event.DELETED, function(element) {
    // TODO Don't delete geometry if issue on server.
    console.log('delete');
    element.layers.eachLayer(function(layer) {
        console.log('delete layer');
        deleteGeometry(layer);
    });
});

// Handle styling of a geometry.
map.on('styleeditor:changed', function(element){
    editGeometry(element);
});

/* Various methods. */

// TODO Remove standard markers.
// Add the standard mapping map markers.
//$('.mapping-marker-popup-content').each(function() {
//    var popup = $(this).clone().show();
//    var latLng = new L.LatLng(popup.data('marker-lat'), popup.data('marker-lng'));
//    var marker = new L.Marker(latLng);
//    marker.bindPopup(popup[0]);
//    markers.addLayer(marker);
//});

// Switching sections changes map dimensions, so make the necessary adjustments.
$('#locate').one('o:section-opened', function(e) {
    map.invalidateSize();
    setView();
});

});
