/* Cartography */

// TODO Merge this file with cartography-admin (currently only some variables are different).

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
        oaMotivatedBy: 'highlighting',
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
            alert(message);
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
        oaMotivatedBy: 'highlighting',
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
 * @return int
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
var mappingMap = $('#cartography-media');
// Geometries are currently defined as a simple variable.
// TODO Fetch existing values instead of reading.
var geometriesData = geometriesMedia;

// Initialize the map and set default view.
var map = L.map('cartography-media', {
    // TODO Compute the min/max zoom according to images?
    minZoom: -4,
    maxZoom: 8,
    zoom: 0,
    center: [0, 0],
    maxBoundsViscosity: 1,
    crs: L.CRS.Simple,
    pasteControl: true,
});
map.setView([0, 0], 0);

var mapMoved = false;

//TODO Create automatically the bounds from geometries.
var defaultBounds = null;
// defaultBounds = [southWest, northEast];

//Add layers and controls to the map.
var baseMaps = {};
var firstMap;
$.each(mainImages, function(index, mainImage) {
    // Compute image edges as positive coordinates.
    // TODO Choose top left as 0.0 for still images?
    var southWest = L.latLng(0, 0);
    var northEast = L.latLng(mainImage.size[1], mainImage.size[0]);
    var bounds = L.latLngBounds(southWest, northEast);
    var image = L.imageOverlay(mainImage.url, bounds);
    if (!firstMap) {
        firstMap = image;
    }
    baseMaps['Image #' + (index + 1)] = image;
});
if (Object.keys(baseMaps).length > 1) {
    var layerControl = L.control.layers(baseMaps);
    map.addControl(new L.Control.Layers(baseMaps));
}
var bounds = firstMap.getBounds();
firstMap.addTo(map);
map.panTo([bounds.getNorthEast().lat / 2, bounds.getNorthEast().lng / 2]);
// FIXME Fit bounds first image overlay.
//map.fitBounds(bounds);

// Geometries are displayed and edited on the drawnItems layer.
var drawnItems = new L.FeatureGroup();
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
        circle: false,
        marker: true,
        circlemarker: false,
    },
    edit: {
        featureGroup: drawnItems,
        remove: true
    }
});
map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));
map.addControl(drawControl);
map.addControl(geoSearchControl);
map.addLayer(drawnItems);

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

map.on('paste:layer-created', function(element) {
    map.addLayer(element.layer);
});

/* Various methods. */

// Switching sections changes map dimensions, so make the necessary adjustments.
$('#describe').one('o:section-opened', function(e) {
    map.invalidateSize();
    setView();
});

});
