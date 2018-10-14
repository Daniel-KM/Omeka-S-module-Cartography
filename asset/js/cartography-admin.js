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
        // "locating" is a not standard motivation.
        oaMotivatedBy: 'locating',
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
    var identifier = layer.annotationIdentifier || getMarkerIdentifier(layer);
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
        // "locating" is a not standard motivation.
        oaMotivatedBy: 'locating',
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
    var identifier = layer.annotationIdentifier || getMarkerIdentifier(layer);
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
 * Get marker identifier.
 *
 * @todo Fix this process, too hacky: the identifier should be simple to save and find.
 * @param layer
 */
var getMarkerIdentifier = function(layer) {
    var identifier = layer.options.annotationIdentifier;
    if (identifier) {
        return identifier;
    }
    var parents = Object.values(layer._eventParents);
    return parents[parents.length - 1].annotationIdentifier;
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

// Initialize the map and set default view.
var map = L.map('cartography-map', {
    pasteControl: true,
});
map.setView([20, 0], 2);
var mapMoved = false;

// TODO Create automatically the bounds from geometries.
var defaultBounds = null;
// defaultBounds = [southWest, northEast];

// Add layers and controls to the map.
if (typeof baseMaps === 'undefined') {
    let baseMaps = {};
}
if (typeof baseMaps !== 'object' || $.isEmptyObject(baseMaps)) {
    baseMaps = {
        'Streets': L.tileLayer.provider('OpenStreetMap.Mapnik'),
        'Grayscale': L.tileLayer.provider('OpenStreetMap.BlackAndWhite'),
        'Satellite': L.tileLayer.provider('Esri.WorldImagery'),
        'Terrain': L.tileLayer.provider('Esri.WorldShadedRelief'),
    };
}

if (!wmsLayers.length) {
    var layerControl = L.control.layers(baseMaps);
    map.addControl(new L.Control.Layers(baseMaps));
} else {
    //Adapted from mapping-block.js (module Mapping).
    // TODO Remove the "no overlay" when there is no overlay.
    // TODO Use multi-checkboxes, not radios.
    var noOverlayLayer = new L.GridLayer();
    var groupedOverlays = {
        'Overlays': {
            'No overlay': noOverlayLayer,
        },
    };

    // Set and prepare opacity control, if there is an overlay layer.
    var openWmsLayer, openWmsLabel;
    var opacityControl;
    var handleOpacityControl = function(overlay, label) {
        if (opacityControl) {
            // Only one control at a time.
            map.removeControl(opacityControl);
            opacityControl = null;
        }
        if (overlay !== noOverlayLayer) {
            // The "No overlay" overlay gets no control.
            opacityControl =  new L.Control.Opacity(overlay, label);
            map.addControl(opacityControl);
        }
    };

    // Add grouped WMS overlay layers.
    map.addLayer(noOverlayLayer);
    $.each(wmsLayers, function(index, data) {
        var wmsLabel = data.label.length ? data.label : (Omeka.jsTranslate('Layer') + ' ' + (index + 1));
        // Leaflet requires the layers and the styles separated.
        // Require a recent browser (@url https://developer.mozilla.org/en-US/docs/Web/API/URLSearchParams#Browser_compatibility#Browser_compatibility).
        // TODO Add a check and pure js to bypass missing URL interface.
        var url =  new URL(data.url);
        var searchParams = url.searchParams;
        var wmsLayers = '';
        wmsLayers = searchParams.get('LAYERS') || searchParams.get('Layers') || searchParams.get('layers') || wmsLayers;
        searchParams.delete('LAYERS'); searchParams.delete('Layers'); searchParams.delete('layers');
        var wmsStyles = '';
        wmsStyles = searchParams.get('STYLES') || searchParams.get('Styles') || searchParams.get('styles') || wmsStyles;
        searchParams.delete('STYLES'); searchParams.delete('Styles'); searchParams.delete('styles');
        url.search = searchParams;
        var wmsUrl = url.toString();
        if (wmsUrl.indexOf('?') === -1) {
            wmsUrl += '?';
        }
        wmsLayer = L.tileLayer.wms(wmsUrl, {
            layers: wmsLayers,
            styles: wmsStyles,
            format: 'image/png',
            transparent: true,
        });
        // Open the first wms overlay by default.
        if (index === 0) {
            openWmsLayer = wmsLayer;
            openWmsLabel = wmsLabel;
        }
        groupedOverlays['Overlays'][wmsLabel] = wmsLayer;
    });
    L.control.groupedLayers(baseMaps, groupedOverlays, {
        exclusiveGroups: ['Overlays']
    }).addTo(map);
}

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
map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));
map.addControl(drawControl);
map.addControl(geoSearchControl);
map.addControl(new L.control.scale({'position':'bottomleft','metric':true,'imperial':false}));
// TODO Fix and add the fit bound control with geometries, not markers.
//map.addControl(new L.Control.FitBounds(markers));

map.addLayer(baseMaps['Satellite']);
map.addLayer(drawnItems);
// map.addLayer(markers);

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

// Append the opacity control at the end of the toolbar for better ux.
if (typeof openWmsLayer !== 'undefined' && openWmsLayer) {
    map.removeLayer(noOverlayLayer);
    map.addLayer(openWmsLayer);
    handleOpacityControl(openWmsLayer, openWmsLabel);

    // Handle the overlay opacity control.
    map.on('overlayadd', function(e) {
        handleOpacityControl(e.layer, e.name);
    });
}

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
    element.layers.eachLayer(function(layer) {
        deleteGeometry(layer);
    });
});

// Handle styling of a geometry.
map.on('styleeditor:changed', function(element){
    editGeometry(element);
});

// Handle paste wkt/geojson.
map.on('paste:layer-created', function(element){
    addGeometry(element.layer);
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
