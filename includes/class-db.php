<?php
/**
 * Database schema manager for Real Treasury Gate.
 */
class RTG_DB {
	/**
	 * Install or update database tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$forms_table    = $wpdb->prefix . 'rtg_forms';
		$assets_table   = $wpdb->prefix . 'rtg_assets';
		$mappings_table = $wpdb->prefix . 'rtg_mappings';
		$leads_table    = $wpdb->prefix . 'rtg_leads';
		$tokens_table   = $wpdb->prefix . 'rtg_tokens';
		$events_table   = $wpdb->prefix . 'rtg_events';

		$sql_forms = "CREATE TABLE {$forms_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	name varchar(191) NOT NULL,
	fields_schema longtext NOT NULL,
	consent_text longtext NOT NULL,
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id)
) {$charset_collate};";

		$sql_assets = "CREATE TABLE {$assets_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	name varchar(191) NOT NULL,
	slug varchar(191) NOT NULL,
	type varchar(50) NOT NULL,
	config longtext NOT NULL,
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY slug (slug)
) {$charset_collate};";

		$sql_mappings = "CREATE TABLE {$mappings_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	form_id bigint(20) unsigned NOT NULL,
	asset_id bigint(20) unsigned NOT NULL,
	iframe_src_template longtext NOT NULL,
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY form_id (form_id),
	KEY asset_id (asset_id)
) {$charset_collate};";

		$sql_leads = "CREATE TABLE {$leads_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	email varchar(191) NOT NULL,
	form_data longtext NOT NULL,
	ip_hash varchar(191) NOT NULL,
	ua_hash varchar(191) NOT NULL,
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY email (email)
) {$charset_collate};";

		$sql_tokens = "CREATE TABLE {$tokens_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	lead_id bigint(20) unsigned NOT NULL,
	asset_id bigint(20) unsigned NOT NULL,
	token_hash varchar(64) NOT NULL,
	expires_at datetime NOT NULL,
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY lead_id (lead_id),
	KEY asset_id (asset_id),
	KEY token_hash (token_hash),
	KEY expires_at (expires_at)
) {$charset_collate};";

		$sql_events = "CREATE TABLE {$events_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	lead_id bigint(20) unsigned NOT NULL,
	form_id bigint(20) unsigned NOT NULL,
	asset_id bigint(20) unsigned NOT NULL,
	event_type varchar(100) NOT NULL,
	meta longtext NOT NULL,
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY lead_id (lead_id),
	KEY form_id (form_id),
	KEY asset_id (asset_id),
	KEY event_type (event_type),
	KEY created_at (created_at)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_forms );
		dbDelta( $sql_assets );
		dbDelta( $sql_mappings );
		dbDelta( $sql_leads );
		dbDelta( $sql_tokens );
		dbDelta( $sql_events );
	}
}
