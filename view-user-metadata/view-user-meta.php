<?php

if(!defined('ABSPATH')) exit;

/*
Plugin Name: View User Metadata
Plugin URI: https://neoboffin.com/plugins/view-user-metadata
Description: A lightweight plugin that is easy to use and enables Administrators to view metadata (user meta) associated with users.
Version: 1.3
Requires at least: 4.7
Requires PHP: 5.6
Author: Neoboffin LLC
Author URI: https://neoboffin.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: view-user-metadata
*/

class SS88_ViewUserMetadata {

    public static function init() {

        $C = __CLASS__;
        new $C;

    }

    function __construct() {

        add_action('admin_init', [$this, 'admin_init']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);

        if(is_admin()) {

            add_action('wp_ajax_SS88_VUM_DISMISS_NOTICE', [$this, 'ajax_dismiss_notice']);
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts_notice']);

        }

        add_action('admin_notices', [$this, 'show_unicornpanel_notice']);

    }

    function admin_init() {

        global $pagenow;

        if(!current_user_can('list_users')) return;

        if($pagenow == 'user-edit.php' || $pagenow == 'profile.php') {

            add_action('show_user_profile', [$this, 'showUserMeta']);
            add_action('edit_user_profile', [$this, 'showUserMeta']);
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        }

        add_action('wp_ajax_SS88_VUM_delete', [$this, 'deleteMeta']);
        add_action('wp_ajax_SS88_VUM_export', [$this, 'exportMeta']);

    }

    function getVersion() {

        static $Version = null;

        // Read the header once per request, the notice script asks for it on every admin page.
        if($Version === null) {

            $Data = get_file_data(__FILE__, ['Version' => 'Version']);
            $Version = $Data['Version'];

        }

        return $Version;

    }

    function admin_enqueue_scripts() {

        $Version = $this->getVersion();

        wp_enqueue_style('SS88_VUM-user', plugin_dir_url( __FILE__ ) . 'assets/css/user.css', false, $Version);
        wp_enqueue_script('SS88_VUM-jsuser', plugin_dir_url( __FILE__ ) . 'assets/js/user.js', ['wp-a11y'], $Version, true);

		wp_localize_script('SS88_VUM-jsuser', 'SS88_VUM_translations', [
			'error' => __('Error:', 'view-user-metadata'),
			'success' => __('Success!', 'view-user-metadata'),
			'locked_title' => __('Locked. Click to unlock deletion for this key.', 'view-user-metadata'),
			'unlocked_title' => __('Unlocked. Click to lock this key again.', 'view-user-metadata'),
			'unexpected_response' => __('The server returned an unexpected response.', 'view-user-metadata'),
			'invalid_export' => __('The export response was invalid.', 'view-user-metadata'),
			'missing_export' => __('Missing export parameters.', 'view-user-metadata'),
			'copied' => __('Copied!', 'view-user-metadata'),
			'copy_failed' => __('Copy failed. Please copy manually.', 'view-user-metadata'),
			'nonce' => wp_create_nonce('SS88_VUM_delete_nonce'),
			'export_nonce' => wp_create_nonce('SS88_VUM_export_nonce')
		]);

    }

	function showUserMeta($U) {

        if(!current_user_can('edit_user', $U->ID)) return;

        $UserMeta = get_user_meta($U->ID);
        ksort($UserMeta, SORT_STRING | SORT_FLAG_CASE);

		?>

<h2 id="SS88-VUM-heading">
	<?php esc_html_e('View User Meta', 'view-user-metadata'); ?>
	<input type="checkbox" id="SS88VUM-toggle" />
	<label for="SS88VUM-toggle"><?php esc_html_e('Toggle', 'view-user-metadata'); ?></label>
	<button type="button" id="SS88VUM-raw-toggle" class="button" aria-pressed="false" aria-controls="SS88-VUM-table-wrapper"><?php esc_html_e('Show raw values', 'view-user-metadata'); ?></button>
	<span id="SS88VUM-export-wrap">
		<button type="button" id="SS88VUM-export-trigger" class="button" aria-expanded="false" aria-controls="SS88VUM-export-menu"><?php esc_html_e('Export', 'view-user-metadata'); ?></button>
		<span id="SS88VUM-export-menu">
			<button type="button" data-format="csv"><?php esc_html_e('CSV', 'view-user-metadata'); ?></button>
			<button type="button" data-format="json"><?php esc_html_e('JSON', 'view-user-metadata'); ?></button>
		</span>
	</span>
</h2>

<div id="SS88-VUM-table-wrapper" data-uid="<?php echo intval($U->ID); ?>">
    <table class="form-table" role="presentation" id="SS88-VUM-table">
        <tbody>
            <?php
            foreach($UserMeta as $Key => $Values) {

                $RawValues = array_values((array) $Values);
                $Values = $this->getMetaValues($Values);
                $Count = count($Values);
                $IsProtected = $this->isProtectedMetaKey($Key);
                $CanDelete = !($U->ID === get_current_user_id() && $this->isRoleMetaKey($Key));

                if($Count > 1) {
                    /* translators: 1: meta key, 2: number of values */
                    $ConfirmText = sprintf(_n('Are you sure you wish to permanently delete "%1$s" and its %2$d value?', 'Are you sure you wish to permanently delete "%1$s" and all %2$d of its values?', $Count, 'view-user-metadata'), $Key, $Count);
                }
                else {
                    /* translators: %s: meta key */
                    $ConfirmText = sprintf(__('Are you sure you wish to permanently delete "%s" and its value?', 'view-user-metadata'), $Key);
                }
            ?>
            <tr>
                <th><div class="ss88-vum-flex">
					<?php if(!$CanDelete) { ?>
						<span class="ss88-vum-lock is-fixed" role="img" title="<?php echo esc_attr__('You cannot delete your own role or user level.', 'view-user-metadata'); ?>" aria-label="<?php echo esc_attr__('You cannot delete your own role or user level.', 'view-user-metadata'); ?>"><span class="dashicons dashicons-lock"></span></span>
					<?php } else if($IsProtected) { ?>
						<button class="ss88-vum-lock is-locked" data-lock="true" title="<?php echo esc_attr__('Locked. Click to unlock deletion for this key.', 'view-user-metadata'); ?>" aria-label="<?php echo esc_attr__('Locked. Click to unlock deletion for this key.', 'view-user-metadata'); ?>" type="button"><span class="dashicons dashicons-lock"></span></button>
					<?php } ?>
					<?php echo esc_html($Key); ?>
					<button class="ss88-vum-copy ss88-vum-copy-key" data-key="<?php echo esc_attr($Key); ?>" title="<?php esc_attr_e('Copy key', 'view-user-metadata'); ?>" aria-label="<?php esc_attr_e('Copy key', 'view-user-metadata'); ?>" type="button"><span class="dashicons dashicons-admin-page"></span></button>
					<?php if($CanDelete) { ?>
					<button class="ss88-vum-delete<?php echo ($IsProtected) ? ' is-hidden' : ''; ?>" data-key="<?php echo esc_attr($Key); ?>" data-confirm="<?php echo esc_attr($ConfirmText); ?>" data-uid="<?php echo intval($U->ID); ?>" title="<?php esc_attr_e('Delete this entry', 'view-user-metadata'); ?>" type="button"><span class="dashicons dashicons-trash"></span></button>
					<?php } ?>
                </div></th>
                <td>
                    <button class="ss88-vum-copy ss88-vum-copy-value" title="<?php esc_attr_e('Copy value', 'view-user-metadata'); ?>" aria-label="<?php esc_attr_e('Copy value', 'view-user-metadata'); ?>" type="button"><span class="dashicons dashicons-admin-page"></span></button>
                    <?php if($Count > 1) { ?>
                    <span class="ss88-vum-multi"><?php
                        /* translators: %d: number of values stored under this meta key */
                        echo esc_html(sprintf(_n('%d value', '%d values', $Count, 'view-user-metadata'), $Count));
                    ?></span>
                    <?php } ?>
                    <div class="ss88-vum-formatted"><?php foreach($Values as $Value) echo $this->outputValue($Value); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- outputValue() escapes. ?></div>
                    <div class="ss88-vum-raw"><?php foreach($RawValues as $Raw) echo $this->outputRawValue($Raw); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- outputRawValue() escapes. ?></div>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

		<?php

	}

	/**
	 * Unserialize every row stored under a meta key. A key can hold more than one row.
	 */
	function getMetaValues($Values) {

		return array_values(array_map('maybe_unserialize', (array) $Values));

	}

	/**
	 * Return a meta value as escaped HTML.
	 */
    function outputValue($Value) {

        if(is_object($Value)) return '<pre class="ss88-vum-value">' . esc_html(print_r($Value, true)) . '</pre>'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- displaying the stored object is the purpose of this plugin.

        if(is_array($Value)) {

            // exportValue() keeps class names and non-public properties of nested objects.
            $JSON = wp_json_encode($this->exportValue($Value), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if($JSON === false) $JSON = print_r($Value, true); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- fallback display.

            return '<pre class="ss88-vum-value">' . esc_html($JSON) . '</pre>';

        }

        if($Value === '') return '<div class="ss88-vum-value is-empty" data-empty="1">' . esc_html__('(empty)', 'view-user-metadata') . '</div>';
        if($Value === null) return '<div class="ss88-vum-value is-empty">null</div>';

        return '<div class="ss88-vum-value">' . esc_html($this->scalarToString($Value)) . '</div>';

    }

	/**
	 * Return the value exactly as stored in the database, as escaped HTML.
	 */
	function outputRawValue($Raw) {

		$Raw = (string) $Raw;

		if($Raw === '') return '<div class="ss88-vum-value is-empty" data-empty="1">' . esc_html__('(empty)', 'view-user-metadata') . '</div>';

		return '<pre class="ss88-vum-value">' . esc_html($Raw) . '</pre>';

	}

	function scalarToString($Value) {

		if(is_bool($Value)) return $Value ? 'true' : 'false';
		if($Value === null) return '';

		return (string) $Value;

	}

	/**
	 * Strip the table prefix from per-site keys, e.g. wp_user-settings or wp_2_user-settings becomes user-settings.
	 * Returns null when the key does not start with the prefix.
	 */
	function stripSitePrefix($MetaKey) {

		global $wpdb;

		$BasePrefix = (isset($wpdb->base_prefix)) ? $wpdb->base_prefix : 'wp_';

		if(!preg_match('/^' . preg_quote($BasePrefix, '/') . '(?:\d+_)?(.+)$/', (string) $MetaKey, $Match)) return null;

		return $Match[1];

	}

	/**
	 * Matches {prefix}capabilities / {prefix}user_level for the main site and every site on a multisite network.
	 */
	function isRoleMetaKey($MetaKey) {

		return in_array($this->stripSitePrefix($MetaKey), ['capabilities', 'user_level'], true);

	}

	/**
	 * Keys written by WordPress core.
	 */
	function isCoreMetaKey($MetaKey) {

		if($this->isRoleMetaKey($MetaKey)) return true;

		$CoreKeys = [
			// Profile fields, see _get_additional_user_keys().
			'nickname',
			'first_name',
			'last_name',
			'description',
			'rich_editing',
			'syntax_highlighting',
			'comment_shortcuts',
			'admin_color',
			'use_ssl',
			'show_admin_bar_front',
			'locale',
			// Sessions and admin state.
			'session_tokens',
			'_application_passwords',
			'_new_email',
			'dismissed_wp_pointers',
			'default_password_nag',
			'community-events-location',
			'enable_custom_fields',
			'nav_menu_recently_edited',
			'wporg_favorites',
			// Multisite.
			'primary_blog',
			'source_domain',
		];

		if(in_array($MetaKey, $CoreKeys, true)) return true;

		$SiteKeys = [
			'user-settings',
			'user-settings-time',
			'dashboard_quick_press_last_post_id',
			'persisted_preferences',
			'media_library_mode',
		];

		if(in_array($this->stripSitePrefix($MetaKey), $SiteKeys, true)) return true;

		return false;

	}

	function isProtectedMetaKey($MetaKey) {

		$MetaKey = (string) $MetaKey;
		$IsProtected = false;

		if($this->isCoreMetaKey($MetaKey)) $IsProtected = true;

		// Our own plugins, e.g. SS88_2FAVE_Enabled and SS88_LicenseKeys.
		else if(stripos($MetaKey, 'ss88_') === 0) $IsProtected = true;

		else {

			$ProtectedPrefixes = [
				'billing_',
				'shipping_',
				'google_',
				'stripe_',
				'mailchimp_',
			];

			foreach($ProtectedPrefixes as $Prefix) {

				if(strpos($MetaKey, $Prefix) === 0) {

					$IsProtected = true;
					break;

				}

			}

		}

		/**
		 * Filters whether a meta key is locked against accidental deletion.
		 *
		 * @param bool   $IsProtected Whether the key is locked.
		 * @param string $MetaKey     The meta key.
		 */
		return (bool) apply_filters('ss88_vum_is_protected_key', $IsProtected, $MetaKey);

	}

	function deleteMeta() {

		if(!check_ajax_referer('SS88_VUM_delete_nonce', 'nonce', false)) {

			wp_send_json_error(['httpcode' => 403, 'body' => __('Security check failed. Please refresh and try again.', 'view-user-metadata')], 403);

		}

		$UserID = isset($_POST['uid']) ? absint(wp_unslash($_POST['uid'])) : 0;
		// The key must match the stored meta_key exactly, so it is not sanitized. It is only used in prepared queries.
		$MetaKey = (isset($_POST['key']) && is_string($_POST['key'])) ? wp_unslash($_POST['key']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if($MetaKey === '' || $UserID === 0) {

			wp_send_json_error(['httpcode' => -1, 'body' => __('Either the meta key or user ID was not supplied. Please refresh and try again.', 'view-user-metadata')]);

		}

		if(!current_user_can('edit_user', $UserID)) {

			wp_send_json_error(['httpcode' => 403, 'body' => __('You are not allowed to delete metadata for this user.', 'view-user-metadata')], 403);

		}

		if($UserID === get_current_user_id() && $this->isRoleMetaKey($MetaKey)) {

			wp_send_json_error(['httpcode' => 403, 'body' => __('You cannot delete your own role or user level, as this could lock you out of the site.', 'view-user-metadata')], 403);

		}

		$MetaExists = metadata_exists('user', $UserID, $MetaKey);

		if(!$MetaExists) {

			wp_send_json_error(['httpcode' => -1, 'body' => __('The meta key does not exist for this user. Nothing to delete.', 'view-user-metadata')]);

		}

		// delete_metadata() unslashes the key itself, so re-slash it to keep backslashes intact.
		$DeleteMeta = delete_user_meta($UserID, wp_slash($MetaKey));

		if($DeleteMeta) {

			wp_send_json_success(['body' => __('The meta key and value was deleted.', 'view-user-metadata')]);

		}
		else {

			wp_send_json_error(['httpcode' => -1, 'body' => __('The meta key and value was not deleted.', 'view-user-metadata')]);

		}

	}

	function exportMeta() {

		if(!check_ajax_referer('SS88_VUM_export_nonce', 'nonce', false)) {

			wp_send_json_error(['httpcode' => 403, 'body' => __('Security check failed. Please refresh and try again.', 'view-user-metadata')], 403);

		}

		$UserID = isset($_POST['uid']) ? absint(wp_unslash($_POST['uid'])) : 0;
		$Format = isset($_POST['format']) ? sanitize_key(wp_unslash($_POST['format'])) : '';

		if($UserID === 0 || !in_array($Format, ['csv', 'json'], true)) {

			wp_send_json_error(['httpcode' => -1, 'body' => __('A valid export format and user ID are required.', 'view-user-metadata')]);

		}

		if(!current_user_can('edit_user', $UserID)) {

			wp_send_json_error(['httpcode' => 403, 'body' => __('You are not allowed to export metadata for this user.', 'view-user-metadata')], 403);

		}

		$UserMeta = get_user_meta($UserID);
		ksort($UserMeta, SORT_STRING | SORT_FLAG_CASE);

		$Rows = [];

		foreach($UserMeta as $Key => $Values) {

			$Values = array_map([$this, 'exportValue'], $this->getMetaValues($Values));
			$Rows[] = [
				'key' => (string) $Key,
				'value' => (count($Values) === 1) ? $Values[0] : $Values
			];

		}

		$DateStamp = gmdate('Ymd-His');

		if($Format === 'json') {

			$Content = wp_json_encode($Rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			if($Content === false) {

				wp_send_json_error(['httpcode' => -1, 'body' => __('Unable to generate JSON export.', 'view-user-metadata')]);

			}

			wp_send_json_success([
				'filename' => 'user-meta-' . $UserID . '-' . $DateStamp . '.json',
				'mime' => 'application/json',
				'content' => $Content
			]);

		}

		$CSVRows = [];
		$CSVRows[] = '"key","value"';

		foreach($Rows as $Row) {

			$Value = $Row['value'];

			if(is_array($Value)) $Value = wp_json_encode($Value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			else $Value = $this->scalarToString($Value);

			$CSVRows[] = $this->csvCell($Row['key']) . ',' . $this->csvCell($Value);

		}

		// The BOM lets Excel detect UTF-8.
		$Content = "\xEF\xBB\xBF" . implode("\r\n", $CSVRows);

		wp_send_json_success([
			'filename' => 'user-meta-' . $UserID . '-' . $DateStamp . '.csv',
			'mime' => 'text/csv;charset=utf-8',
			'content' => $Content
		]);

	}

	/**
	 * Objects become arrays tagged with their class, with non-public properties included, so the JSON export keeps them.
	 */
	function exportValue($Value, $Depth = 0) {

		if(!is_array($Value) && !is_object($Value)) return $Value;

		// Guards against objects that reference themselves.
		if($Depth >= 32) return '*RECURSION*';

		$Out = [];

		if(is_object($Value)) $Out['__class'] = get_class($Value);

		foreach((array) $Value as $Key => $Item) {

			// Casting an object to an array prefixes private/protected property names with null bytes.
			if(is_object($Value)) {

				$Key = (string) $Key;
				$Pos = strrpos($Key, "\0");
				if($Pos !== false) $Key = substr($Key, $Pos + 1);

			}

			$Out[$Key] = $this->exportValue($Item, $Depth + 1);

		}

		return $Out;

	}

	/**
	 * Quote a CSV cell. Values that a spreadsheet would run as a formula get a leading apostrophe.
	 */
	function csvCell($Value) {

		$Value = (string) $Value;

		if($Value !== '' && in_array($Value[0], ['=', '+', '-', '@', "\t", "\r"], true)) $Value = "'" . $Value;

		return '"' . str_replace('"', '""', $Value) . '"';

	}

	function ajax_dismiss_notice() {

		check_ajax_referer('ss88_vum_dismiss', 'nonce');

		if(!current_user_can('manage_options')) {
			wp_send_json_error('', 403);
		}

		$Type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';

		$AllowedTypes = ['unicornpanel'];

		if(in_array($Type, $AllowedTypes, true)) {

			// Shared with other SS88 plugins so dismissing in one hides it in all
			update_option('SS88_2FAVE_notice_dismissed_' . $Type, true);

		}

		wp_send_json_success();

	}

	function show_unicornpanel_notice() {

		if(!current_user_can('manage_options')) return;

		if(get_option('SS88_2FAVE_notice_dismissed_unicornpanel')) return;

		echo '<div class="notice notice-info is-dismissible SS88_VUM ss88-unicornpanel-notice" data-type="unicornpanel"><p>';
		echo '<span style="display:flex;gap:15px;align-items:center;">';
		echo '<img src="' . esc_url(plugin_dir_url( __FILE__ ) . 'assets/img/unicornpanel-logo.svg') . '" alt="Unicorn Panel" width="60" height="60" style="display:block;flex:0 0 auto;" />';
		echo '<span>';
		echo '<strong>' . esc_html__('We just released Unicorn Panel, our new hosting control panel built for multi-server fleets', 'view-user-metadata') . '</strong><br>';
		echo esc_html__('Provision, manage, and monitor every website, database, mailbox, and DNS zone across your entire fleet from one place. Free for 2 servers. Reselling included.', 'view-user-metadata');
		echo ' <a href="' . esc_url('https://unicornpanel.com?ref=WordPress&utm_campaign=WPdashboard_VUM') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Learn more', 'view-user-metadata') . ' &rarr;</a>';
		echo '<br><small style="opacity:0.7;">' . sprintf(
			/* translators: %s: linked plugin name */
			esc_html__('Brought to you by the %s plugin', 'view-user-metadata'),
			'<a href="' . esc_url('https://wordpress.org/plugins/view-user-metadata/') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View User Metadata', 'view-user-metadata') . '</a>'
		) . '</small>';
		echo '</span></span></p></div>';

	}

	function admin_enqueue_scripts_notice() {

		if(!current_user_can('manage_options') || get_option('SS88_2FAVE_notice_dismissed_unicornpanel')) return;

		wp_enqueue_script('SS88_VUM-notice', plugin_dir_url( __FILE__ ) . 'assets/js/notice.js', [], $this->getVersion(), true);
		wp_localize_script('SS88_VUM-notice', 'SS88_VUM_NoticeData', array('ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce('ss88_vum_dismiss')));

	}

    function plugin_action_links($actions) {
        $mylinks = [
            '<a href="https://wordpress.org/support/plugin/view-user-metadata/" target="_blank" rel="noopener noreferrer">' . esc_html__('Need help?', 'view-user-metadata') . '</a>',
        ];
        return array_merge( $actions, $mylinks );
    }

}

if(method_exists('SS88_ViewUserMetadata', 'init')) add_action('plugins_loaded', ['SS88_ViewUserMetadata', 'init']);
