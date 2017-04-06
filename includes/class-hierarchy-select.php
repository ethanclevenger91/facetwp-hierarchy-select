<?php


class FacetWP_Facet_Hierarchy_Select{

	var $settings;
	var $terms;
	var $depth;

	function __construct() {
		$this->label = __( 'Hierarchy Select', 'fwp' );
		add_filter( 'facetwp_assets', array( $this, 'set_assets' ) );
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

		$facet        = $params['facet'];
		$from_clause  = $wpdb->prefix . 'facetwp_index f';
		$where_clause = $params['where_clause'];

		// Orderby
		$orderby = 'counter DESC, f.facet_display_value ASC';
		if ( 'display_value' == $facet['orderby'] ) {
			$orderby = 'f.facet_display_value ASC';
		} elseif ( 'raw_value' == $facet['orderby'] ) {
			$orderby = 'f.facet_value ASC';
		}

		// Sort by depth just in case
		$orderby = "f.depth, $orderby";


		$orderby     = apply_filters( 'facetwp_facet_orderby', $orderby, $facet );
		$from_clause = apply_filters( 'facetwp_facet_from', $from_clause, $facet );

		$sql = "
        SELECT f.facet_value, f.facet_display_value, f.term_id, f.parent_id, f.depth, COUNT(DISTINCT f.post_id) AS counter
        FROM $from_clause
        WHERE f.facet_name = '{$facet['name']}'
        GROUP BY f.facet_value
        ORDER BY $orderby";

		$output = $wpdb->get_results( $sql, ARRAY_A );


		return $output;
	}

	function render( $params ) {

		$output          = '';
		$facet           = $params['facet'];
		$values          = (array) $params['values'];
		$selected_values = (array) array_filter( $params['selected_values'] );

		$label_any = empty( $facet['label_first'] ) ? __( 'Any', 'fwp' ) : $facet['label_first'];
		$label_any = facetwp_i18n( $label_any );
		$target    = null;
		if ( ! empty( $facet['levels'] ) ) {
			$target = 'data-target="1"';
		}
		$output .= '<select class="facetwp-hierarchy_select" data-level="0" ' . $target . '>';

		$output .= '<option value="">' . esc_attr( $label_any ) . '</option>';

		$options    = array();
		$level_ids  = array();
		$level_keys = array();
		foreach ( $values as $result ) {
			ob_start();
			$selected = '';
			if ( ! empty( $selected_values[ $result['depth'] ] ) && $result['facet_value'] == $selected_values[ $result['depth'] ] ) {
				$level_ids[ $result['depth'] ] = $result['term_id'];
				$selected                      = ' selected';
			}

			if ( ! empty( $level_ids[ $result['depth'] - 1 ] ) && $level_ids[ $result['depth'] - 1 ] != $result['parent_id'] ) {
				continue;
			}


			// Determine whether to show counts
			$display_value = $result['facet_display_value'];
			$show_counts   = apply_filters( 'facetwp_facet_dropdown_show_counts', true, array( 'facet' => $facet ) );

			if ( $show_counts ) {
				$display_value .= ' (' . $result['counter'] . ')';
			}

			$options[ $result['depth'] ][]    = '<option value="' . $result['facet_value'] . '"' . $selected . '>' . $display_value . '</option>';
			$level_keys[ $result['depth'] ][] = $result['facet_value'];
		}
		if ( ! empty( $options[0] ) ) {
			$output .= implode( $options[0] );
		}

		$output .= '</select>';

		if ( ! empty( $facet['levels'] ) ) {
			foreach ( $facet['levels'] as $level => $label ) {
				$level += 1;

				if ( empty( $selected_values[ $level - 1 ] ) || empty( $options[ $level ] ) || ! in_array( $selected_values[ $level - 1 ], $level_keys[ $level - 1 ] ) ) {
					continue;
				}
				$target = null;
				if ( $level < count( $facet['levels'] ) ) {
					$target = 'data-target="' . ( $level + 1 ) . '"';
				}
				$enabled = '';
				if ( empty( $selected_values[ $level - 1 ] ) ) {
					$enabled = ' disabled';
				}
				$output .= '<select class="facetwp-hierarchy_select" data-level="' . $level . '" ' . $target . $enabled . '>';
				$output .= '<option value="">' . esc_attr( $label ) . '</option>';
				if ( ! empty( $selected_values[ $level - 1 ] ) && ! empty( $options[ $level ] ) ) {
					$output .= implode( $options[ $level ] );
				}
				$output .= '</select>';
			}
		}

		$output .= ob_get_clean();

		return $output;
	}


	function get_level( $parent, $depth = 0 ) {
		$out = array();
		foreach ( $this->terms as $term ) {
			if ( $term->parent === $parent ) {
				$out[ $term->slug ] = array(
					'name' => $term->name,
				);
				if ( $depth < $this->depth ) {
					$out[ $term->slug ]['children'] = $this->get_level( $term->term_id, $depth + 1 );
				}
			}
		}

		return $out;
	}

	function filter_posts( $params ) {
		global $wpdb;

		$facet           = $params['facet'];
		$selected_values = (array) $params['selected_values'];
		$selected_value  = array_pop( $selected_values );

		$sql = "
    SELECT DISTINCT post_id FROM {$wpdb->prefix}facetwp_index
    WHERE facet_name = '{$facet['name']}' AND facet_value IN ('$selected_value')";

		return $wpdb->get_col( $sql );
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
                    var wrapper = $this.find('.hierarchy-add-level-wrapper');
                    for (var l = 0; l < obj.levels.length; l++) {
                        var level = create_level(obj.levels[l]);
                        level.insertBefore(wrapper);
                    }
                });

                wp.hooks.addFilter('facetwp/save/hierarchy_select', function ($this, obj) {
                    obj['hierarchical'] = 'yes'; // locked.
                    obj['source'] = $this.find('.facet-source').val();
                    obj['label_first'] = $this.find('.facet-label-first').val();
                    obj['parent_term'] = $this.find('.facet-parent-term').val();
                    obj['orderby'] = $this.find('.facet-orderby').val();
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
                    <input type="text" class="facet-label-level" value="<?php esc_attr_e( 'Any', 'fwp' ); ?>"/> <input
                            type="button"
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

}
