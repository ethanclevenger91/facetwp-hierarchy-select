(function ($) {

    wp.hooks.addAction('facetwp/refresh/hierarchy_select', function($this, facet_name) {
        var selected_values = [];
        $this.find('.facetwp-hierarchy_select option:selected').each(function() {
            var value = $(this).attr('value');
            if( value.length ) {
                selected_values.push($(this).attr('value'));
            }
        });
        FWP.facets[facet_name] = selected_values;
    });

    wp.hooks.addFilter('facetwp/selections/hierarchy_select', function(output, params) {
        return params.el.find('.facetwp-hierarchy_select option:selected').text();
    });

    $(document).on('change', '.facetwp-type-hierarchy_select select', function() {
        var $selected = $(this);
        $selected.trigger('reset');

        FWP.autoload();
    });
    $(document).on('reset', '.facetwp-type-hierarchy_select select', function() {
        var $selected = $(this),
            $facet = $(this).closest('.facetwp-facet');

        $facet.find( '[data-level="' + $selected.data('target') + '"]' ).trigger('reset').remove();

    });
})(jQuery);