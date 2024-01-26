<?php
/**
 * Plugin Class.
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\ShowAdditionalFieldOnAttendeePage
 */

namespace Tribe\Extensions\ShowAdditionalFieldOnAttendeePage;

use TEC\Common\Contracts\Service_Provider;

/**
 * Class Plugin
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\ShowAdditionalFieldOnAttendeePage
 */
class Plugin extends Service_Provider {
	/**
	 * Stores the version for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Stores the base slug for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const SLUG = 'show-additional-field-on-attendee-page';

	/**
	 * Stores the base slug for the extension.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const FILE = TRIBE_EXTENSION_SHOW_ADDITIONAL_FIELD_ON_ATTENDEE_PAGE_FILE;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin Directory.
	 */
	public $plugin_dir;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin path.
	 */
	public $plugin_path;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin URL.
	 */
	public $plugin_url;

	/**
	 * @since 1.0.0
	 *
	 * @var Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private $settings;

	/**
	 * Set up the Extension's properties.
	 *
	 * This always executes even if the required plugins are not present.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Set up the plugin provider properties.
		$this->plugin_path = trailingslashit( dirname( static::FILE ) );
		$this->plugin_dir  = trailingslashit( basename( $this->plugin_path ) );
		$this->plugin_url  = plugins_url( $this->plugin_dir, $this->plugin_path );

		// Register this provider as the main one and use a bunch of aliases.
		$this->container->singleton( static::class, $this );
		$this->container->singleton( 'extension.show_additional_field_on_attendee_page', $this );
		$this->container->singleton( 'extension.show_additional_field_on_attendee_page.plugin', $this );
		$this->container->register( PUE::class );

		if ( ! $this->check_plugin_dependencies() ) {
			// If the plugin dependency manifest is not met, then bail and stop here.
			return;
		}

		// Do the settings.
		// TODO: Remove if not using settings
		$this->get_settings();

		// Start binds.

		add_action( 'tribe_events_tickets_generate_filtered_attendees_list', [ $this, 'tribe_export_custom_set_up' ] );

		// Adding an extra column header
		add_filter( 'tribe_tickets_attendee_table_columns', [ $this, 'tec_et_add_extra_column' ] );

		// Adding the values to the column
		add_filter( 'tribe_events_tickets_attendees_table_column', [ $this, 'tec_et_populate_extra_column' ], 10, 3 );


		// End binds.

		$this->container->register( Hooks::class );
		$this->container->register( Assets::class );
	}

	/**
	 * Handler.
	 *
	 * @param int $event_id The post ID of the event.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	function tribe_export_custom_set_up( int $event_id ) {
		//Add Handler for Community Tickets to Prevent Notices in Exports

		if ( ! is_admin() ) {
			$screen_base = 'tribe_events_page_tickets-attendees';

		} else {
			$screen      = get_current_screen();
			$screen_base = $screen->base;
		}

		$filter_name = "manage_{$screen_base}_columns";

		add_filter( $filter_name, [ $this, 'tribe_export_custom_add_columns' ], 100 );
		add_filter( 'tribe_events_tickets_attendees_table_column', [ $this, 'tec_et_populate_extra_column' ], 10, 3 );
	}

	/**
	 * Add column to the Attendee export file.
	 *
	 * @param array $columns An array of columns that go into the Attendee export file.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	function tribe_export_custom_add_columns( array $columns ): array {
		$custom_column = $this->get_column_info();
		$columns[ $custom_column['slug'] ] = $custom_column['name'];

		return $columns;
	}

	/**
	 * Populate the extra column with its value.
	 *
	 * @param string $value  The current value of the column.
	 * @param array  $item
	 * @param string $column The column slug.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	function tec_et_populate_extra_column( string $value, array $item, string $column ): string {
		$custom_column = $this->get_column_info();

		$event_id      = $item[ 'event_id' ];
		$custom_fields = tribe_get_custom_fields( $event_id );
		$ledger        = $custom_fields[ $custom_column['name'] ];

		if ( isset( $ledger ) ) {
			if ( $custom_column['slug'] == $column ) {
				$value = $ledger;
			}
		} else {
			$value = 'n/a';
		}

		return $value;
	}

	/**
	 * Add column to the Attendees page.
	 *
	 * @param array $columns An array of columns.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	function tec_et_add_extra_column( array $columns ): array {
		$custom_column = $this->get_column_info();
		/**
		 * Choose below after which column you would like to add the purchase time
		 * 'cb', 'ticket', 'primary_info', 'security', 'status', 'check_in'
		 */
		$insert_after_column = 'primary_info';

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( $key == $insert_after_column ) {
				$new_columns[ $custom_column['slug'] ] = $custom_column['name'];
			}
		}

		return $new_columns;
	}

	/**
	 * Returns the additional field column information.
	 *
	 * @return array
	 */
	public function get_column_info() {
		$base_name = 'Ledger Code';
		$name_capitalized = ucwords( $base_name );
		$slug = strtolower( str_replace( ' ', '_', $base_name ) );

		return [
			'name'    => $name_capitalized,
			'slug'    => $slug,
		];
	}

	/**
	 * Checks whether the plugin dependency manifest is satisfied or not.
	 *
	 * @return bool Whether the plugin dependency manifest is satisfied or not.
	 * @since 1.0.0
	 *
	 */
	protected function check_plugin_dependencies() {
		$this->register_plugin_dependencies();

		return tribe_check_plugin( static::class );
	}

	/**
	 * Registers the plugin and dependency manifest among those managed by Tribe Common.
	 *
	 * @since 1.0.0
	 */
	protected function register_plugin_dependencies() {
		$plugin_register = new Plugin_Register();
		$plugin_register->register_plugin();

		$this->container->singleton( Plugin_Register::class, $plugin_register );
		$this->container->singleton( 'extension.show_additional_field_on_attendee_page', $plugin_register );
	}

	/**
	 * Get this plugin's options prefix.
	 *
	 * Settings_Helper will append a trailing underscore before each option.
	 *
	 * @return string
	 *
	 * @see \Tribe\Extensions\ShowAdditionalFieldOnAttendeePage\Settings::set_options_prefix()
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_options_prefix() {
		return (string) str_replace( '-', '_', 'tec-labs-show-additional-field-on-attendee-page' );
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_settings() {
		if ( empty( $this->settings ) ) {
			$this->settings = new Settings( $this->get_options_prefix() );
		}

		return $this->settings;
	}

	/**
	 * Get all of this extension's options.
	 *
	 * @return array
	 *
	 * TODO: Remove if not using settings
	 */
	public function get_all_options() {
		$settings = $this->get_settings();

		return $settings->get_all_options();
	}

	/**
	 * Get a specific extension option.
	 *
	 * @param        $option
	 * @param string $default
	 *
	 * @return array
	 *
	 * TODO: Remove if not using settings
	 */
	public function get_option( $option, $default = '' ) {
		$settings = $this->get_settings();

		return $settings->get_option( $option, $default );
	}
}
