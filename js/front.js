(function ($) {
    wp.hooks.addAction('facetwp/refresh/hierarchy_select', function ($this, facet_name) {
        var val = $this.find('.facetwp-hierarchy_select option:selected[value!=""]').last().val();
        FWP.facets[facet_name] = val ? [val] : [];
        console.log( FWP.settings.hierarchy_select );
    });

    wp.hooks.addFilter('facetwp/selections/hierarchy_select', function (output, params) {
        return params.el.find('.facetwp-hierarchy_select option:selected').text();
    });

    $(document).on('change', '.facetwp-type-hierarchy_select select', function () {
        var $facet = $(this).closest('.facetwp-facet');
        $facet.find('[data-level]').not(this).hide();
        if ('' !== $facet.find(':selected').val()) {
            //FWP.static_facet = $facet.attr('data-name');
        }
        FWP.autoload();
    });
})(jQuery);