<?php
/**
 * POI List Table
 *
 * Extends WP_List_Table to display POIs in a familiar WordPress admin interface.
 *
 * @package Clear_Map
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Clear_Map_POI_List_Table class.
 */
class Clear_Map_POI_List_Table extends WP_List_Table {

	/**
	 * Categories data.
	 *
	 * @var array
	 */
	private $categories = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'poi',
				'plural'   => 'pois',
				'ajax'     => true,
			)
		);

		$this->categories = get_option( 'clear_map_categories', array() );
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'photo'    => __( 'Photo', 'clear-map' ),
			'logo'     => __( 'Logo', 'clear-map' ),
			'name'     => __( 'Name', 'clear-map' ),
			'category' => __( 'Category', 'clear-map' ),
			'address'  => __( 'Address', 'clear-map' ),
			'status'   => __( 'Status', 'clear-map' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'name'     => array( 'name', false ),
			'category' => array( 'category', false ),
			'address'  => array( 'address', false ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array(
			'delete' => __( 'Delete', 'clear-map' ),
		);

		// Add category change options.
		if ( ! empty( $this->categories ) ) {
			foreach ( $this->categories as $key => $category ) {
				$actions[ 'move_to_' . $key ] = sprintf(
					/* translators: %s: category name */
					__( 'Move to: %s', 'clear-map' ),
					$category['name']
				);
			}
		}

		return $actions;
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Get POIs.
		$pois = $this->get_pois_flat();

		// Handle search.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		if ( ! empty( $search ) ) {
			$pois = array_filter(
				$pois,
				function( $poi ) use ( $search ) {
					$search_lower = strtolower( $search );
					return (
						stripos( $poi['name'], $search_lower ) !== false ||
						stripos( $poi['address'], $search_lower ) !== false ||
						stripos( $poi['description'], $search_lower ) !== false
					);
				}
			);
		}

		// Handle category filter.
		$category_filter = isset( $_REQUEST['category_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['category_filter'] ) ) : '';
		if ( ! empty( $category_filter ) ) {
			$pois = array_filter(
				$pois,
				function( $poi ) use ( $category_filter ) {
					return $poi['category_key'] === $category_filter;
				}
			);
		}

		// Handle sorting.
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'name';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'asc';

		usort(
			$pois,
			function( $a, $b ) use ( $orderby, $order ) {
				$result = 0;

				switch ( $orderby ) {
					case 'name':
						$result = strcasecmp( $a['name'], $b['name'] );
						break;
					case 'category':
						$result = strcasecmp( $a['category_name'], $b['category_name'] );
						break;
					case 'address':
						$result = strcasecmp( $a['address'], $b['address'] );
						break;
				}

				return ( 'asc' === $order ) ? $result : -$result;
			}
		);

		// Pagination.
		$per_page     = $this->get_items_per_page( 'clear_map_pois_per_page', 20 );
		$current_page = $this->get_pagenum();
		$total_items  = count( $pois );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		// Slice for current page.
		$pois = array_slice( $pois, ( $current_page - 1 ) * $per_page, $per_page );

		$this->items = $pois;
	}

	/**
	 * Get POIs as flat array with category info.
	 *
	 * @return array
	 */
	private function get_pois_flat() {
		$pois       = get_option( 'clear_map_pois', array() );
		$flat_pois  = array();

		foreach ( $pois as $category_key => $category_pois ) {
			$category_name  = isset( $this->categories[ $category_key ]['name'] )
				? $this->categories[ $category_key ]['name']
				: $category_key;
			$category_color = isset( $this->categories[ $category_key ]['color'] )
				? $this->categories[ $category_key ]['color']
				: '#999999';

			foreach ( $category_pois as $index => $poi ) {
				$flat_pois[] = array_merge(
					$poi,
					array(
						'category_key'   => $category_key,
						'category_name'  => $category_name,
						'category_color' => $category_color,
						'poi_index'      => $index,
						'unique_id'      => $category_key . '|' . $index,
					)
				);
			}
		}

		return $flat_pois;
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item POI data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="poi_ids[]" value="%s" />',
			esc_attr( $item['unique_id'] )
		);
	}

	/**
	 * Photo column.
	 *
	 * @param array $item POI data.
	 * @return string
	 */
	public function column_photo( $item ) {
		if ( ! empty( $item['photo'] ) ) {
			return sprintf(
				'<img src="%s" alt="" class="poi-thumbnail" />',
				esc_url( $item['photo'] )
			);
		}

		return '<span class="poi-no-image dashicons dashicons-format-image"></span>';
	}

	/**
	 * Logo column.
	 *
	 * @param array $item POI data.
	 * @return string
	 */
	public function column_logo( $item ) {
		$logo = isset( $item['logo'] ) ? $item['logo'] : '';

		if ( ! empty( $logo ) ) {
			return sprintf(
				'<img src="%s" alt="" class="poi-thumbnail poi-logo-thumb" />',
				esc_url( $logo )
			);
		}

		return '<span class="poi-no-image dashicons dashicons-store"></span>';
	}

	/**
	 * Name column.
	 *
	 * @param array $item POI data.
	 * @return string
	 */
	public function column_name( $item ) {
		$actions = array(
			'edit'   => sprintf(
				'<a href="#" class="poi-edit-link" data-poi-id="%s">%s</a>',
				esc_attr( $item['unique_id'] ),
				__( 'Edit', 'clear-map' )
			),
			'delete' => sprintf(
				'<a href="#" class="poi-delete-link" data-poi-id="%s">%s</a>',
				esc_attr( $item['unique_id'] ),
				__( 'Delete', 'clear-map' )
			),
		);

		return sprintf(
			'<strong><a href="#" class="poi-edit-link row-title" data-poi-id="%s">%s</a></strong>%s',
			esc_attr( $item['unique_id'] ),
			esc_html( $item['name'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Category column.
	 *
	 * @param array $item POI data.
	 * @return string
	 */
	public function column_category( $item ) {
		return sprintf(
			'<span class="category-badge" style="background-color: %s;">%s</span>',
			esc_attr( $item['category_color'] ),
			esc_html( $item['category_name'] )
		);
	}

	/**
	 * Address column.
	 *
	 * @param array $item POI data.
	 * @return string
	 */
	public function column_address( $item ) {
		$address = ! empty( $item['address'] ) ? $item['address'] : '—';

		// Truncate long addresses.
		if ( strlen( $address ) > 50 ) {
			$address = substr( $address, 0, 47 ) . '...';
		}

		return esc_html( $address );
	}

	/**
	 * Status column.
	 *
	 * @param array $item POI data.
	 * @return string
	 */
	public function column_status( $item ) {
		$icons = array();

		// Has coordinates.
		$has_coords = ! empty( $item['lat'] ) && ! empty( $item['lng'] );
		$icons[]    = sprintf(
			'<span class="status-icon %s" title="%s"><span class="dashicons dashicons-location"></span></span>',
			$has_coords ? 'status-ok' : 'status-missing',
			$has_coords ? __( 'Has coordinates', 'clear-map' ) : __( 'Missing coordinates', 'clear-map' )
		);

		// Has photo.
		$has_photo = ! empty( $item['photo'] );
		$icons[]   = sprintf(
			'<span class="status-icon %s" title="%s"><span class="dashicons dashicons-format-image"></span></span>',
			$has_photo ? 'status-ok' : 'status-missing',
			$has_photo ? __( 'Has photo', 'clear-map' ) : __( 'No photo', 'clear-map' )
		);

		// Has logo.
		$has_logo = ! empty( $item['logo'] );
		$icons[]  = sprintf(
			'<span class="status-icon %s" title="%s"><span class="dashicons dashicons-store"></span></span>',
			$has_logo ? 'status-ok' : 'status-missing',
			$has_logo ? __( 'Has logo', 'clear-map' ) : __( 'No logo', 'clear-map' )
		);

		return '<div class="status-icons">' . implode( '', $icons ) . '</div>';
	}

	/**
	 * Default column handler.
	 *
	 * @param array  $item        POI data.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Message when no items found.
	 */
	public function no_items() {
		esc_html_e( 'No POIs found.', 'clear-map' );
	}

	/**
	 * Extra table navigation.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<select name="category_filter" id="filter-by-category">
				<option value=""><?php esc_html_e( 'All Categories', 'clear-map' ); ?></option>
				<?php foreach ( $this->categories as $key => $category ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( isset( $_REQUEST['category_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['category_filter'] ) ) : '', $key ); ?>>
						<?php echo esc_html( $category['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'clear-map' ), '', 'filter_action', false ); ?>
		</div>
		<div class="alignleft actions">
			<button type="button" class="button" id="export-pois-btn">
				<span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
				<?php esc_html_e( 'Export', 'clear-map' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Generate row class.
	 *
	 * @param array $item POI data.
	 * @return string
	 */
	protected function get_row_class( $item ) {
		$classes = array();

		if ( empty( $item['lat'] ) || empty( $item['lng'] ) ) {
			$classes[] = 'poi-missing-coords';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Generate single row.
	 *
	 * @param array $item POI data.
	 */
	public function single_row( $item ) {
		$class = $this->get_row_class( $item );
		echo '<tr class="' . esc_attr( $class ) . '" data-poi-id="' . esc_attr( $item['unique_id'] ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}
}
