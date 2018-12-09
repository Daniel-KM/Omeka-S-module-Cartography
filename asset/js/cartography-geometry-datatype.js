(function($) {

    $(document).ready( function() {

        $('textarea.value.geometry').on('keyup', function(e) {
            geometryCheck(this, 'geometry');
        });

        // Initial load.
        initGeometryDatatypes();

    });

    /**
     * Prepare the geometry datatypes for the main resource template.
     *
     * There is no event in resource-form.js and common/resource-form-templates.phtml,
     * except the generic view.add.after and view.edit.after, so the default
     * form is completed dynamically during the initial load.
     */
    var initGeometryDatatypes = function() {
        var defaultSelectorAndFields = $('.resource-values.field.template .add-values.default-selector, #properties .resource-values div.default-selector');
        appendGeometryDatatypes(defaultSelectorAndFields);
    }

    /**
     * Append the configured datatypes to a list of element.
     */
    var appendGeometryDatatypes = function(selector) {
        if (geometryDatatypes.indexOf('geometry') !== -1) {
            $('<a>', {'class': 'add-value button o-icon-geometry', 'href': '#', 'data-type': 'geometry'})
                .text(Omeka.jsTranslate('Geometry'))
                .appendTo(selector);
            selector.append("\n");
        }
    };

    /**
     * Check user input geometry.
     *
     * @param object element
     * @param string datatype
     */
    var geometryCheck = function(element, datatype) {
        var primitive, message;
        var val = element.value.trim();
        if (datatype === 'geometry') {
            try {
                primitive = Terraformer.WKT.parse(val);
            } catch (err) {
                message = 'Please enter a valid wkt for the geometry.';
            }
        }

        if (val === '' || primitive) {
            element.setCustomValidity('');
        } else {
            element.setCustomValidity(Omeka.jsTranslate(message));
        }
    }

})(jQuery);
