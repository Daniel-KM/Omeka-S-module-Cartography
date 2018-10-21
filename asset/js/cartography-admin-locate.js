/* Cartography */

// The base code comes initially from Mapping/asset/js/mapping-show.js in order
// to keep standard markers., but now the dependency has been removed.
// TODO Check if some code is dead or useless.
// TODO The automatic bounds should be managed to fit existing geometries (or let the user set it and store it like in mapping?) or full map.
// TODO Clarify use of the annotation identifier.

$(document).ready( function() {

/**
 * Fetch geometries for a resource.
 *
 * @return array
 */
var fetchGeometries = function(identifier, partIdentifier) {
    var url = window.location.origin + basePath + '/admin/cartography/' + identifier + '/geometries'
        + '?mediaId=0';

    $.get(url, null,
        function(data, textStatus, jqxhr) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }

            displayGeometries(data.geometries);
        })
        .fail(function(jqxhr) {
            var message = jqxhr.responseText.substring(0, 1) !== '<' ? JSON.parse(jqxhr.responseText).message : Omeka.jsTranslate('Unable to fetch the geometries.');
            alert(message);
        });
}

/**
 * Display geometries.
 */
var displayGeometries = function(geometries) {
    $.each(geometries, function(index, data) {
         var geojson = Terraformer.WKT.parse(data['wkt']);
         var options = data['options'] ? data['options'] : {};
         options.annotationIdentifier = data['id'];
         var layer = L.geoJson(geojson, options);
         addGeometry(layer, data['id']);
         layer.annotationIdentifier = data['id'];
         layer.options.annotationIdentifier = data['id'];
    });
}

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
        mediaId: currentMediaId(),
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
            var message = jqxhr.responseText.substring(0, 1) !== '<' ? JSON.parse(jqxhr.responseText).message : Omeka.jsTranslate('Unable to save the geometry.');
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
        mediaId: currentMediaId(),
        wkt: wkt,
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
            var message = jqxhr.responseText.substring(0, 1) !== '<' ? JSON.parse(jqxhr.responseText).message : Omeka.jsTranslate('Unable to update the geometry.');
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
            var message = jqxhr.responseText.substring(0, 1) !== '<' ? JSON.parse(jqxhr.responseText).message : Omeka.jsTranslate('Unable to delete the geometry.');
            alert(message);
        });
}

/**
 * Allows to create editable layer from existing geometries.
 *
 * @see https://gis.stackexchange.com/questions/203540/how-to-edit-an-existing-layer-using-leaflet/203773#203773
 * @todo Fix: the existing groups are not draggable (but the newly created were).
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
 * Get the media id of the current image overlay.
 *
 * There is no media id in Locate, since anything is georeferenced, and related
 * to the item.
 *
 * @todo Get id of the current ImageOverlay via Leaflet methods, not jQuery.
 * @return null
 */
var currentMediaId = function() {
    return null;
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

var section = 'locate';

//TODO Find the way to get the current annotation after the resource selection.
var currentAnnotation;

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
map.addLayer(baseMaps['Satellite']);
//TODO Fix and add the fit bound control with geometries, not markers.
fetchGeometries(resourceId);

// Geometries are displayed and edited on the drawnItems layer.
var drawnItems = new L.FeatureGroup();
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
var geoSearchControl = new window.GeoSearch.GeoSearchControl({
    provider: new window.GeoSearch.OpenStreetMapProvider,
    showMarker: false,
    retainZoomLevel: true,
});
map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));
map.addControl(drawControl);
map.addControl(geoSearchControl);
map.addControl(new L.control.scale({'position':'bottomleft','metric':true,'imperial':false}));
map.addLayer(drawnItems);

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

setView();

/* Manage geometries. */

// Handle adding new geometries.
map.on(L.Draw.Event.CREATED, function (element) {
    addGeometry(element.layer);
});

// Handle editing geometries (when the edit button "save" is clicked).
map.on(L.Draw.Event.EDITED, function(element) {
    // TODO Check if options changed to avoid to save default ones.
    // FIXME It doesn't work when a marker is moved or style edited.
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

// Manage the relations side bar.
// TODO Move specific code from StyleEditor here and use events.

// Store the current annotation identifier for easier processing after resource selection.
map.on('styleeditor:editing', function(element){
    // The annotation identifier is enough, but currently, the full layer/marker/geometry is saved.
    currentAnnotation = element;
});
map.on('styleeditor:hidden', function(element){
    currentAnnotation = null;
});

// Disable the core resource-form.js bind for the sidebar selector.
// TODO Factorize to avoid this check.
if (typeof mainImages === 'undefined' || mainImages.length === 0) {
    $(document).off('o:prepare-value');
}

/**
 * Add a new linked resource from the sidebar for the style editor.
 *
 * @see application/asset/js/resource-form.js
 * type: "resource"; value: empty; valueObj: data of one selected item; nameprefix: empty.
 */
$(document).on('o:prepare-value', function(e, type, value, valueObj, namePrefix) {
    if (!valueObj || typeof valueObj['value_resource_id'] === 'undefined') {
        return;
    }
    // Check if the current section is open.
    if ($('.section.active').prop('id') !== section || $('#' + section + ' .leaflet-styleeditor.editor-enabled').length !== 1) {
        return;
    }
    var identifier = currentAnnotation.annotationIdentifier
        ? currentAnnotation.annotationIdentifier
        : currentAnnotation.options.annotationIdentifier
        ? currentAnnotation.options.annotationIdentifier
        : null;
    if (!identifier) {
        alert('Unable to find the geometry.');
        return;
    }

    // Check if the selected resource is already linked.
    var partIdentifier = currentMediaId();
    var url = window.location.origin + basePath + '/admin/cartography/' + resourceId + '/geometries'
        + '?mediaId=' + '0' + '&annotationId=' + identifier;
    $.get(url, null,
        function(data, textStatus, jqxhr) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }

            if (typeof data.geometries[identifier] !== 'undefined') {
                var oaLinking = data.geometries[identifier].options.oaLinking || [];
                var arrayLength = oaLinking.length;
                for (var i = 0; i < arrayLength; i++) {
                    if (oaLinking[i]['value_resource_id'] === valueObj['value_resource_id']) {
                        alert('The resource is already linked to the current annotation.');
                        return;
                    }
                }
            }

            var resourceDataTypes = [
                'resource',
                'resource:item',
                'resource:itemset',
                'resource:media',
            ];
            if (!valueObj || resourceDataTypes.indexOf(type) === -1) {
                return;
            }

            addLinkedResource(identifier, valueObj);
            appendLinkedResource(valueObj);
        })
        .fail(function(jqxhr) {
            var message = jqxhr.responseText.substring(0, 1) !== '<' ? JSON.parse(jqxhr.responseText).message : Omeka.jsTranslate('Unable to fetch the geometries.');
            alert(message);
        });
});
var addLinkedResource = function(identifier, valueObj) {
    if (typeof currentAnnotation.options.oaLinking === 'undefined') {
        currentAnnotation.options.oaLinking = [];
    }
    currentAnnotation.options.oaLinking.push(valueObj);
    editGeometry(currentAnnotation);
};
var appendLinkedResource = function(valueObj) {
    // Prepare the markup for the resource data types.
    var html = '<div class="value selecting-resource">'
        + '<p class="selected-resource">'
        // TODO Add ellipsis to display the title and to display the resource icon.
        // + '<span class="o-title ' + valueObj['value_resource_name'] + '">';
        + '<span class="o-title ' + valueObj['value_resource_name'] + '-no">'
        + (typeof valueObj['thumbnail_url'] !== 'undefined' ? '<img src="' + valueObj['thumbnail_url'] + '">' : '')
        + '<a href="' + valueObj['url'] + '">'
        + (typeof valueObj['display_title'] === 'undefined' ? Omeka.jsTranslate('[Untitled]') : valueObj['display_title'])
        + '</a>'
        + '</span>'
        + '</p>'
        + '<ul class="actions">'
        + '<li><a class="o-icon-delete remove-value" title="Remove value" href="#" aria-label="Remove value" data-value-resource-id="' + valueObj['value_resource_id'] + '"></a></li>'
        + '</ul>'
        + '</div>';
    var oaLinkingDiv = $('.leaflet-styleeditor-oalinking.value.selecting-resource:visible');
    oaLinkingDiv.append(html);
};

/**
 * Remove a linked resource (directly via jQuery).
 */
$('#' + section).on('click', '.leaflet-styleeditor-interior .actions .remove-value', function (element) {
    if (!currentAnnotation || !currentAnnotation.options.oaLinking || currentAnnotation.options.oaLinking.length === 0) {
        return;
    }

    // Remove the linked resource from the list of linked resources.
    var oaLinking = currentAnnotation.options.oaLinking || [];
    var valueResourceId = $(this).data('value-resource-id');
    var exists = false;
    for (var i = 0; i < oaLinking.length; i++) {
        if (oaLinking[i]['value_resource_id'] == valueResourceId) {
            oaLinking.splice(i, 1);
            exists = true;
            break;
        }
    }
    if (!exists) {
        return;
    }

    currentAnnotation.options.oaLinking = oaLinking;
    editGeometry(currentAnnotation);

    // Remove the element from the style editor.
    $(this).closest('.value.selecting-resource').remove();
});

// Switching sections changes map dimensions, so make the necessary adjustments.
$('#' + section).one('o:section-opened', function(e) {
    map.invalidateSize();
    setView();
});

//Close the sidebar when switching sections to avoid possible issues between describe/locate.
$('#' + section).on('o:section-closed', function(e) {
    var sidebar = $('#select-resource');
    Omeka.closeSidebar(sidebar);
});

});