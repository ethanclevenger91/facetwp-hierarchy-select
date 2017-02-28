<?php


class FacetWP_Facet_Hierarchy_Select extends FacetWP_Facet_Dropdown{

	var $settings;

	function __construct() {
		$this->label = __( 'Hierarchy Select', 'fwp' );
		add_filter( 'facetwp_assets', array( $this, 'set_assets' ) );
		add_filter( 'facetwp_render_output', function ( $a ) {
			$a['settings']['hierarchy_select'] = $this->settings;

			return $a;
		} );
	}

	/**
	 * Register the JS assets
	 *
	 * @param $assets
	 *
	 * @return mixed
	 */
	public function set_assets( $assets ) {
		$assets['hierarchy_select'] = FWP_HIER_SELECT_URL . 'js/front.js';

		return $assets;
	}

	/**
	 * Load the available choices
	 */
	function load_values( $params ) {
		global $wpdb;

		$facet = $params['facet'];

		// Apply filtering (ignore the facet's current selection)
		if ( isset( FWP()->or_values ) && ( 1 < count( FWP()->or_values ) || ! isset( FWP()->or_values[ $facet['name'] ] ) ) ) {
			$post_ids  = array();
			$or_values = FWP()->or_values; // Preserve the original
			unset( $or_values[ $facet['name'] ] );

			$counter = 0;
			foreach ( $or_values as $name => $vals ) {
				$post_ids = ( 0 == $counter ) ? $vals : array_intersect( $post_ids, $vals );
				$counter ++;
			}

			// Return only applicable results
			$post_ids = array_intersect( $post_ids, FWP()->unfiltered_post_ids );
		} else {
			$post_ids = FWP()->unfiltered_post_ids;
		}

		$post_ids     = empty( $post_ids ) ? array( 0 ) : $post_ids;
		$where_clause = ' AND post_id IN (' . implode( ',', $post_ids ) . ')';
		$from_clause  = $wpdb->prefix . 'facetwp_index f';

		// Orderby
		$orderby = 'counter DESC, f.facet_display_value ASC';
		if ( 'display_value' == $facet['orderby'] ) {
			$orderby = 'f.facet_display_value ASC';
		} elseif ( 'raw_value' == $facet['orderby'] ) {
			$orderby = 'f.facet_value ASC';
		}

		$orderby      = "f.depth, $orderby";
		$orderby      = apply_filters( 'facetwp_facet_orderby', $orderby, $facet );
		$from_clause  = apply_filters( 'facetwp_facet_from', $from_clause, $facet );
		$where_clause = apply_filters( 'facetwp_facet_where', $where_clause, $facet );

		// Limit
		$limit = ctype_digit( $facet['count'] ) ? $facet['count'] : 20;

		$sql = "
        SELECT f.facet_value, f.facet_display_value, f.term_id, f.parent_id, f.depth, COUNT(DISTINCT f.post_id) AS counter
        FROM $from_clause
        WHERE f.facet_name = '{$facet['name']}' $where_clause
        GROUP BY f.facet_value
        ORDER BY $orderby
        LIMIT $limit";

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Generate the facet HTML
	 */
	function render( $params ) {
		$this->settings[ $params['facet']['name'] ] = 'aasdasd';
		$output                                     = '';
		$facet                                      = $params['facet'];
		$values                                     = (array) $params['values'];
		$selected_values                            = (array) $params['selected_values'];

		$values = FWP()->helper->sort_taxonomy_values( $params['values'], $facet['orderby'] );

		$label_any = empty( $facet['label_first'] ) ? __( 'Any', 'fwp' ) : $facet['label_first'];
		$label_any = facetwp_i18n( $label_any );

		$options   = array();
		$slugs_ids = array();
		foreach ( $values as $result ) {
			if ( $result['depth'] > count( $facet['levels'] ) ) {
				continue;
			}
			$selected = in_array( $result['facet_value'], $selected_values ) ? ' selected' : '';

			// Determine whether to show counts
			$display_value = $result['facet_display_value'];
			$show_counts   = apply_filters( 'facetwp_facet_hierarchy_select_show_counts', true, array( 'facet' => $facet ) );

			if ( $show_counts ) {
				$display_value .= ' (' . $result['counter'] . ')';
			}

			$options[ $result['depth'] ][]       = '<option data-parent="' . $result['parent_id'] . '" value="' . $result['facet_value'] . '"' . $selected . '>' . $display_value . '</option>';
			$slugs_ids[ $result['facet_value'] ] = $result['term_id'];
		}

		foreach ( $options as $index => $option ) {
			$label_level = empty( $facet['levels'][ ( $index - 1 ) ] ) ? __( 'Any', 'fwp' ) : $facet['levels'][ ( $index - 1 ) ];
			$label_level = facetwp_i18n( $label_level );

			$output .= '<select class="facetwp-hierarchy_select" data-level="' . $index . '">';
			$output .= '<option value="">' . esc_attr( $label_level ) . '</option>';

			$output .= implode( '', $option );

			$output .= '</select>';
		}

		ob_start();
		//var_dump( $options );

		//var_dump( $values );
		$output .= ob_get_clean();


		return $output;
	}

	/**
	 * Output any admin scripts
	 */
	function admin_scripts() {
		?>
        <script>
            (function ($) {

                function create_level(value) {
                    var template = $('#hierarchy-select-tmpl').html();
                    new_line = $(template);

                    if (value) {
                        new_line.find('.facet-label-level').val(value);
                    }
                    return new_line;
                }


                wp.hooks.addAction('facetwp/load/hierarchy_select', function ($this, obj) {
                    $this.find('.facet-source').val(obj.source);
                    $this.find('.facet-label-first').val(obj.label_first);
                    $this.find('.facet-parent-term').val(obj.parent_term);
                    $this.find('.facet-orderby').val(obj.orderby);
                    $this.find('.facet-count').val(obj.count);
                    var wrapper = $this.find('.hierarchy-add-level-wrapper');
                    for (var l = 0; l < obj.levels.length; l++) {
                        var level = create_level(obj.levels[l]);
                        level.insertBefore(wrapper);
                    }
                });

                wp.hooks.addFilter('facetwp/save/hierarchy_select', function ($this, obj) {
                    obj['source'] = $this.find('.facet-source').val();
                    obj['label_first'] = $this.find('.facet-label-first').val();
                    obj['parent_term'] = $this.find('.facet-parent-term').val();
                    obj['orderby'] = $this.find('.facet-orderby').val();
                    obj['count'] = $this.find('.facet-count').val();
                    obj['levels'] = [];
                    $this.find('.facet-label-level').each(function () {
                        obj['levels'].push(this.value);
                    });

                    return obj;
                });

                // init the levels
                $(document).on('click', '.hierarchy-add-level', function (e) {
                    var clicked = $(this),
                        parent = clicked.closest('.hierarchy-add-level-wrapper'),
                        new_line = create_level();

                    new_line.insertBefore(parent);
                });
                $(document).on('click', '.hierarchy-select-remove-level', function (e) {
                    $(this).closest('.hierarchy-select-level').remove();
                });

            })(jQuery);
        </script>
        <script type="text/html" id="hierarchy-select-tmpl">
            <tr class="hierarchy-select-level">
                <td>
					<?php _e( "Level's label", 'fwp' ); ?>:
                    <div class="facetwp-tooltip">
                        <span class="icon-question">?</span>
                        <div class="facetwp-tooltip-content">
                            Customize this level's label.
                        </div>
                    </div>
                </td>
                <td>
                    <input type="text" class="facet-label-level" value=""/> <input type="button"
                                                                                   class="button button-small hierarchy-select-remove-level"
                                                                                   style="margin: 1px;"
                                                                                   value="<?php _e( 'Remove', 'fwp' ); ?>"/>
                </td>
            </tr>
        </script>
		<?php
	}

	/**
	 * Output admin settings HTML
	 */
	function settings_html() {
		?>
        <tr>
            <td>
				<?php _e( 'Parent term', 'fwp' ); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content">
                        Enter the parent term's ID if you want to a custom starting level.
                        Otherwise, leave blank.
                    </div>
                </div>
            </td>
            <td>
                <input type="text" class="facet-parent-term" value=""/>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Sort by', 'fwp' ); ?>:</td>
            <td>
                <select class="facet-orderby">
                    <option value="count"><?php _e( 'Highest Count', 'fwp' ); ?></option>
                    <option value="display_value"><?php _e( 'Display Value', 'fwp' ); ?></option>
                    <option value="raw_value"><?php _e( 'Raw Value', 'fwp' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <td>
				<?php _e( 'Count', 'fwp' ); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content"><?php _e( 'The maximum number of facet choices to show', 'fwp' ); ?></div>
                </div>
            </td>
            <td><input type="text" class="facet-count" value="20"/></td>
        </tr>
        <tr>
            <td>
				<?php _e( 'First level label', 'fwp' ); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content">
                        Customize the first level's label (default: "Any")
                    </div>
                </div>
            </td>
            <td>
                <input type="text" class="facet-label-first" value="<?php _e( 'Any', 'fwp' ); ?>"/>
            </td>
        </tr>
        <tr class="hierarchy-add-level-wrapper">
            <td></td>
            <td>
                <input type="button" class="hierarchy-add-level button button-small" style="width: 200px;"
                       value="<?php _e( 'Add Level', 'fwp' ); ?>"/>
            </td>
        </tr>
		<?php
	}

	/**
	 * Checks if the value is in the path
	 */
	private function is_in_path( $val, $path ) {
		var_dump( $a );

		return true;
	}
}
