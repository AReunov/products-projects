<?php
/*
Plugin Name: Products & Projects
Description: Test job for PRI HCS: Projects assets, Products assets etc.
Version: 0.1
Author: Andrey Reunov
Author URI: mailto:andrey.reunov@gmail.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class ProductsProjects {

	function __construct() {
		add_action( 'init', array( $this, 'load_text_domain' ), 5 );
		add_action( 'init', array( $this, 'register_post_types' ), 5 );
		add_action( 'admin_notices', array( $this, 'products_projects_admin_notices' ), 0 );
		add_action( 'admin_init', array( $this, 'products_projects_row_actions' ), 5 );
		add_action( 'admin_action_create_project', array( $this, 'create_project_from_product' ) );
		add_action( 'acf/init', array( $this, 'register_products_projects_fields' ), 6 );

	}

	function load_text_domain() {
		load_plugin_textdomain( 'products-projects', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Deactivates this plugin if ACF is not installed or not activated and shows notice message about that
	 */
	function products_projects_admin_notices() {
		if ( ! empty ( $GLOBALS['pagenow'] ) && 'plugins.php' === $GLOBALS['pagenow'] ) {
			if ( class_exists( 'acf' ) ) {
				return;
			}

			// Suppress "Plugin activated" notice.
			unset( $_GET['activate'] );

			$errors = array();

			$plugin_name     = get_file_data( __FILE__, array( 'Plugin Name' ), 'plugin' );
			$errors[] = __( 'Please install and ACF Pro plugin. You can download it <a href="https://github.com/wp-premium/advanced-custom-fields-pro" target="_blank">here</a>' );

			printf(
				'<div class="error"><p>%1$s</p>
        <p><i>%2$s</i> has been deactivated.</p></div>',
				join( '</p><p>', $errors ),
				$plugin_name[0]
			);
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	}

	function products_projects_row_actions() {
		add_filter( 'page_row_actions', array( $this, 'pp_row_actions' ), 10, 2 );
	}

	/**
	 * Adds 'Create project' link to action list
	 */
	function pp_row_actions( $actions, $post ) {
		if ( $post->post_type == 'product' ) {
			$actions['create_project'] = '<a href="' . $this->create_project_link( $post->ID ) . '">' . esc_html__( 'Create project', 'products-projects' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * @param int $id Product post ID
	 *
	 * @return string URL for Create project action
	 */
	function create_project_link( $id = 0 ) {

		if ( ! $post = get_post( $id ) ) {
			return;
		}

		$action = '?action=create_project&post=' . $post->ID;

		return wp_nonce_url( admin_url( 'admin.php' . $action ), 'create-project_' . $post->ID );
	}

	/**
	 * Creates New Project and Project Assets assigned to it, from Product and it's Assets
	 */
	function create_project_from_product() {
		// Get the original post
		$id = ( isset( $_GET['post'] ) ? $_GET['post'] : $_POST['post'] );

		check_admin_referer( 'create-project_' . $id );

		$post = get_post( $id );

		if ( isset( $post ) && $post != null ) {

			// Creating new 'Project'
			$new_post    = array(
				'post_type'   => 'project',
				'post_status' => 'publish',
				'post_title'  => $post->post_title,
				'post_name'   => $post->post_name
			);
			$new_post_id = wp_insert_post( wp_slash( $new_post ) );

			if ( have_rows( 'assets', $post->ID ) ) {
				while ( have_rows( 'assets', $post->ID ) ) {
					the_row();
					$field_asset = get_sub_field( 'product_asset' );
					$field_id    = get_sub_field( 'id' );

					// Creating new project asset (clone of product asset)
					$new_project_asset_id = wp_insert_post( wp_slash( array(
						'post_status' => 'publish',
						'post_title'  => $field_asset->post_title,
						'post_type'   => 'project_assets',
						'post_name'   => $field_asset->post_name
					) ) );

					// Setting fields data for projects asset we just created
					$product_asset_fields = get_fields( $field_asset );
					foreach ( $product_asset_fields as $key => $value ) {
						update_field( $key, $value, $new_project_asset_id );
					}

					// Project's fields (ID and link to project asset)
					add_row( 'assets', array(
						'project_assets' => $new_project_asset_id,
						'id'             => $field_id,
					), $new_post_id );
				}
			}

			// Redirect to Projects list admin page
			$sendback = add_query_arg( 'post_type', 'project', admin_url( 'edit.php' ) );
			wp_redirect( add_query_arg( array( 'cloned' => 1, 'ids' => $post->ID ), $sendback ) );
		}
	}

	function register_post_types() {
		register_post_type( 'product',
			array(
				'labels'          => array(
					'name'               => __( 'Products', 'products-projects' ),
					'singular_name'      => __( 'Product', 'products-projects' ),
					'add_new'            => __( 'Add Product', 'products-projects' ),
					'add_new_item'       => __( 'Add New Product', 'products-projects' ),
					'edit_item'          => __( 'Edit Product', 'products-projects' ),
					'new_item'           => __( 'New Product', 'products-projects' ),
					'view_item'          => __( 'View Product', 'products-projects' ),
					'search_items'       => __( 'Search Products', 'products-projects' ),
					'not_found'          => __( 'No Products found', 'products-projects' ),
					'not_found_in_trash' => __( 'No Products found in Trash', 'products-projects' ),
				),
				'public'          => true,
				'_builtin'        => false,
				'capability_type' => 'page',
				'hierarchical'    => true,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 5,
			) );

		register_post_type( 'project',
			array(
				'labels'          => array(
					'name'               => __( 'Projects', 'products-projects' ),
					'singular_name'      => __( 'Project', 'products-projects' ),
					'add_new'            => __( 'Add Project', 'products-projects' ),
					'add_new_item'       => __( 'Add New Project', 'products-projects' ),
					'edit_item'          => __( 'Edit Project', 'products-projects' ),
					'new_item'           => __( 'New Project', 'products-projects' ),
					'view_item'          => __( 'View Project', 'products-projects' ),
					'search_items'       => __( 'Search Projects', 'products-projects' ),
					'not_found'          => __( 'No Projects found', 'products-projects' ),
					'not_found_in_trash' => __( 'No Projects found in Trash', 'products-projects' ),
				),
				'public'          => true,
				'_builtin'        => false,
				'capability_type' => 'page',
				'hierarchical'    => true,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 5,
			) );

		register_post_type( 'project_assets',
			array(
				'labels'          => array(
					'name'               => __( 'Project assets', 'products-projects' ),
					'singular_name'      => __( 'Project asset', 'products-projects' ),
					'add_new'            => __( 'Add Project Asset', 'products-projects' ),
					'add_new_item'       => __( 'Add New Project Asset', 'products-projects' ),
					'edit_item'          => __( 'Edit Project Asset', 'products-projects' ),
					'new_item'           => __( 'New Project Asset', 'products-projects' ),
					'view_item'          => __( 'View Project Asset', 'products-projects' ),
					'search_items'       => __( 'Search Project Assets', 'products-projects' ),
					'not_found'          => __( 'No Project Asset found', 'products-projects' ),
					'not_found_in_trash' => __( 'No Project Asset found in Trash', 'products-projects' ),
				),
				'public'          => true,
				'_builtin'        => false,
				'capability_type' => 'page',
				'hierarchical'    => true,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=project',
				'menu_position'   => 5,
			) );

		register_post_type( 'product_assets',
			array(
				'labels'          => array(
					'name'               => __( 'Product assets', 'products-projects' ),
					'singular_name'      => __( 'Product asset', 'products-projects' ),
					'add_new'            => __( 'Add Product Asset', 'products-projects' ),
					'add_new_item'       => __( 'Add New Product Asset', 'products-projects' ),
					'edit_item'          => __( 'Edit Product Asset', 'products-projects' ),
					'new_item'           => __( 'New Product Asset', 'products-projects' ),
					'view_item'          => __( 'View Product Asset', 'products-projects' ),
					'search_items'       => __( 'Search Product Assets', 'products-projects' ),
					'not_found'          => __( 'No Product Asset found', 'products-projects' ),
					'not_found_in_trash' => __( 'No Product Asset found in Trash', 'products-projects' ),
				),
				'public'          => true,
				'_builtin'        => false,
				'capability_type' => 'page',
				'hierarchical'    => true,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=product',
				'menu_position'   => 5,
			) );
	}

	function register_products_projects_fields() {
		acf_add_local_field_group( array(
			'key'                   => 'group_5c8fcccbb4b0c',
			'title'                 => 'Assets fields',
			'fields'                => array(
				array(
					'key'               => 'field_5c8fcd3665685',
					'label'             => 'Type',
					'name'              => 'type',
					'type'              => 'select',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '20',
						'class' => '',
						'id'    => '',
					),
					'choices'           => array(
						'email'  => 'Email',
						'banner' => 'Banner',
					),
					'default_value'     => array(),
					'allow_null'        => 0,
					'multiple'          => 0,
					'ui'                => 0,
					'return_format'     => 'value',
					'ajax'              => 0,
					'placeholder'       => '',
				),
				array(
					'key'               => 'field_5c8fcd8d65686',
					'label'             => 'HTML',
					'name'              => 'html',
					'type'              => 'wysiwyg',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_5c8fcd3665685',
								'operator' => '==',
								'value'    => 'email',
							),
						),
					),
					'wrapper'           => array(
						'width' => '80',
						'class' => '',
						'id'    => '',
					),
					'default_value'     => '',
					'tabs'              => 'all',
					'toolbar'           => 'full',
					'media_upload'      => 0,
					'delay'             => 0,
				),
				array(
					'key'               => 'field_5c8fcd1e65684',
					'label'             => 'URL',
					'name'              => 'url',
					'type'              => 'text',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_5c8fcd3665685',
								'operator' => '==',
								'value'    => 'banner',
							),
						),
					),
					'wrapper'           => array(
						'width' => '80',
						'class' => '',
						'id'    => '',
					),
					'default_value'     => '',
					'placeholder'       => '',
					'prepend'           => '',
					'append'            => '',
					'maxlength'         => '',
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'product_assets',
					),
				),
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'project_assets',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'seamless',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => 1,
			'description'           => 'Fields for Product Assets',
		) );

		acf_add_local_field_group( array(
			'key'                   => 'group_5c8fd1e2674f7',
			'title'                 => 'Product fields',
			'fields'                => array(
				array(
					'key'               => 'field_5c8fd221ecc23',
					'label'             => 'Assets',
					'name'              => 'assets',
					'type'              => 'repeater',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'collapsed'         => '',
					'min'               => 0,
					'max'               => 0,
					'layout'            => 'table',
					'button_label'      => '',
					'sub_fields'        => array(
						array(
							'key'               => 'field_5c8fd263ecc25',
							'label'             => 'Assets',
							'name'              => 'product_asset',
							'type'              => 'post_object',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '90',
								'class' => '',
								'id'    => '',
							),
							'post_type'         => array(
								0 => 'product_assets',
							),
							'taxonomy'          => '',
							'allow_null'        => 0,
							'multiple'          => 0,
							'return_format'     => 'object',
							'ui'                => 1,
						),
						array(
							'key'               => 'field_5c8fd248ecc24',
							'label'             => 'ID',
							'name'              => 'id',
							'type'              => 'text',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '10',
								'class' => '',
								'id'    => '',
							),
							'default_value'     => '',
							'placeholder'       => '',
							'prepend'           => '',
							'append'            => '',
							'maxlength'         => '',
						),
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'product',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'seamless',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => 1,
			'description'           => '',
		) );

		acf_add_local_field_group( array(
			'key'                   => 'group_5c8fdb1f66ecd',
			'title'                 => 'Project fields',
			'fields'                => array(
				array(
					'key'               => 'field_5c8fdb1f6fc65',
					'label'             => 'Project Assets',
					'name'              => 'assets',
					'type'              => 'repeater',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'collapsed'         => '',
					'min'               => 0,
					'max'               => 0,
					'layout'            => 'table',
					'button_label'      => '',
					'sub_fields'        => array(
						array(
							'key'               => 'field_5c8fdb1f71b8b',
							'label'             => 'Asset',
							'name'              => 'project_assets',
							'type'              => 'post_object',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '90',
								'class' => '',
								'id'    => '',
							),
							'post_type'         => array(
								0 => 'project_assets',
							),
							'taxonomy'          => '',
							'allow_null'        => 0,
							'multiple'          => 0,
							'return_format'     => 'object',
							'ui'                => 1,
							'disabled'          => 1
						),
						array(
							'key'               => 'field_5c8fdb1f71fb8',
							'label'             => 'ID',
							'name'              => 'id',
							'type'              => 'text',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '10',
								'class' => '',
								'id'    => '',
							),
							'default_value'     => '',
							'placeholder'       => '',
							'prepend'           => '',
							'append'            => '',
							'maxlength'         => '',
						),
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'project',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'seamless',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => 1,
			'description'           => '',
		) );
	}
}

new ProductsProjects();