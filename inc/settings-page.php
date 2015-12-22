<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cheatin\' uh?' );
}

/* !---------------------------------------------------------------------------- */
/* !	SETTINGS PAGE															 */
/* ----------------------------------------------------------------------------- */

// !CSS for labels width. --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

add_action( 'admin_print_styles-settings_page_mlang', 'sfput_print_settings_styles' );

function sfput_print_settings_styles() {
	if ( ! empty( $_GET['tab'] ) && 'url-translation' === $_GET['tab'] ) {
		echo '<style>#polylang-translated-slugs td label{min-width:8em;display:inline-block;}#polylang-translated-slugs input + label{min-width:0;margin:0 .25em;}</style>' . "\n";
	}
}


// !Add a new tab in Polylang settings page. -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

add_filter( 'pll_settings_tabs', 'sfput_settings_tab' );

function sfput_settings_tab( $tabs ) {
	$tabs['url-translation'] = __( 'URL Translation', 'sf-polylang-urls-translation' );
	return $tabs;
}


// !The settings sections and fields. --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

add_action( 'load-settings_page_mlang', 'sfput_settings_fields' );

function sfput_settings_fields() {
	if ( empty( $_GET['tab'] ) || 'url-translation' !== $_GET['tab'] ) {
		return;
	}

	global $polylang;
	$languages	= $polylang->model->get_languages_list();
	$options	= sfput_get_options();


	// Post types
	$post_types	= sfput_available_post_types();
	$post_types	= array_map( 'get_post_type_object', $post_types );

	if ( ! empty( $post_types ) ) {

		$section_title = '<span class="dashicons dashicons-admin-post"></span> ' . __( 'Post types', 'sf-polylang-urls-translation' );
		add_settings_section( 'post_types', $section_title, false, 'mlang' );

		foreach ( $post_types as $post_type ) {

			$archive = sfput_default_post_type_archive_slug( $post_type );
			$single  = sfput_default_post_type_singular_slug( $post_type );

			$defaults_text  = '<span class="description">' . __('Archive slug', 'sf-polylang-urls-translation') . '</span> ' . ( $archive ? sprintf( __( '(default: %s)', 'sf-polylang-urls-translation' ), '<code>' . $archive . '</code>' ) : __( 'disabled', 'sf-polylang-urls-translation' ) );
			$defaults_text .= ' - ';
			$defaults_text .= '<span class="description">' . __('Single slug', 'sf-polylang-urls-translation') . '</span> ' . ( $single ? sprintf( __( '(default: %s)', 'sf-polylang-urls-translation' ), '<code>' . $single . '</code>' ) : __( 'disabled', 'sf-polylang-urls-translation' ) );

			add_settings_field(
				'post_types-' . $post_type->name . '-title',
				$post_type->label,
				'sfput_description_field',
				'mlang',
				'post_types',
				array(
					'description' => $defaults_text,
				)
			);

			foreach ( $languages as $language ) {

				add_settings_field(
					'post_types-' . $post_type->name . '-' . $language->slug,
					'',
					'sfput_post_type_fields',
					'mlang',
					'post_types',
					array(
						'name'		=> $post_type->name,
						'label'		=> sprintf( '%s %s', $language->flag, $language->name ),
						'value'		=> ( ! empty( $options['post_types.archive'][ $post_type->name ][ $language->slug ] ) ? $options['post_types.archive'][ $post_type->name ][ $language->slug ] : '' ),
						'value2'	=> ( ! empty( $options['post_types.single'][ $post_type->name ][ $language->slug ] ) ? $options['post_types.single'][ $post_type->name ][ $language->slug ] : '' ),
						'lang'		=> $language->slug,
					)
				);

			}

		}

	}


	// !Taxonomies
	$taxonomies = sfput_available_taxonomies();
	$taxonomies = array_map( 'get_taxonomy', $taxonomies );

	if ( ! empty( $taxonomies ) ) {

		$section_title = '<span class="dashicons dashicons-category"></span> ' . __( 'Taxonomies', 'sf-polylang-urls-translation' );
		add_settings_section( 'taxonomies', $section_title, false, 'mlang' );

		foreach ( $taxonomies as $taxonomy ) {

			$def_slug = sfput_default_taxonomy_slug( $taxonomy );

			$defaults_text = sprintf( __( '(default: %s)', 'sf-polylang-urls-translation' ), '<code>' . $def_slug . '</code>' );

			add_settings_field(
				'taxonomies-' . $taxonomy->name . '-title',
				$taxonomy->label,
				'sfput_description_field',
				'mlang',
				'taxonomies',
				array(
					'description' => $defaults_text,
				)
			);

			foreach ( $languages as $language ) {

				add_settings_field(
					'taxonomies-' . $taxonomy->name . '-' . $language->slug,
					'',
					'sfput_taxonomy_field',
					'mlang',
					'taxonomies',
					array(
						'name'		=> $taxonomy->name,
						'label'		=> sprintf( '%s %s', $language->flag, $language->name ),
						'value'		=> ( ! empty( $options['taxonomies'][ $taxonomy->name ][ $language->slug ] ) ? $options['taxonomies'][ $taxonomy->name ][ $language->slug ] : '' ),
						'lang'		=> $language->slug,
					)
				);

			}

		}

	}


	// No fields to display?
	if ( empty( $post_types ) && empty( $taxonomies ) ) {

		add_settings_section( 'errors', '', false, 'mlang' );

		add_settings_field(
			'no-content',
			'',
			'sfput_description_field',
			'mlang',
			'errors',
			array(
				'description' => __( 'No custom post types or taxonomies? I\'m so useless :(', 'sf-polylang-urls-translation' ),
			)
		);

	}
}


// !The form. --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

add_action( 'pll_settings_active_tab_url-translation', 'sfput_settings_form' );

function sfput_settings_form() {
	?>
	<form id="polylang-translated-slugs" action="<?php echo admin_url( 'options.php' ); ?>" method="post">
		<?php
		do_settings_sections( 'mlang' );
		settings_fields( 'sfput_settings' );
		submit_button();
		?>
	</form>
	<?php
}


// !Fields. ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

function sfput_description_field( $args ) {
	echo $args['description'];
}


function sfput_post_type_fields( $args ) {
	$args = array_merge( array(
		'label_for'	=> '',
		'name'		=> '',
		'label'		=> '',
		'value'		=> '',
		'value2'	=> '',
		'lang'		=> '',
	), $args );

	$name  = $args['name'] ? $args['name'] : $args['label_for'];
	$id2   = sanitize_html_class( 'post_types-single-' . $name . '-' . $args['lang'] );
	$name2 = 'sfput_options[post_types.single][' . $name . '][' . $args['lang'] . ']';
	$id    = sanitize_html_class( 'post_types-archive-' . $name . '-' . $args['lang'] );
	$name  = 'sfput_options[post_types.archive][' . $name . '][' . $args['lang'] . ']';

	echo $args['label'] ? '<label for="' . $id . '">' . $args['label'] . '</label> ' : '';
	echo '<input id="' . $id . '" type="text" name="' . $name . '" value="' . $args['value'] . '" />';
	echo '<label for="' . $id2 . '"> - </label>';
	echo '<input id="' . $id2 . '" type="text" name="' . $name2 . '" value="' . $args['value2'] . '" />';
}


function sfput_taxonomy_field( $args ) {
	$args = array_merge( array(
		'label_for'	=> '',
		'name'		=> '',
		'label'		=> '',
		'value'		=> '',
		'lang'		=> '',
	), $args );

	$name  = $args['name'] ? $args['name'] : $args['label_for'];
	$id    = sanitize_html_class( 'taxonomies-' . $name . '-' . $args['lang'] );
	$name  = 'sfput_options[taxonomies][' . $name . '][' . $args['lang'] . ']';

	echo $args['label'] ? '<label for="' . $id . '">' . $args['label'] . '</label> ' : '';
	echo '<input id="' . $id . '" type="text" name="' . $name . '" value="' . $args['value'] . '" />';
}

/**/