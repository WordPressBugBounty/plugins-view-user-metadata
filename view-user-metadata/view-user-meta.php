<?php

if(!defined('ABSPATH')) exit;

/*
Plugin Name: View User Metadata
Plugin URI: https://neoboffin.com/plugins/view-user-metadata
Description: A lightweight plugin that is easy to use and enables Administrators to view metadata (user meta) associated with users.
Version: 1.2.2
Author: Neoboffin LLC
Author URI: https://neoboffin.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

class SS88_ViewUserMetadata {

    protected $V = '1.2.2';

    public static function init() {

        $C = __CLASS__;
        new $C;

    }

    function __construct() {

        global $pagenow;

        if(!current_user_can('list_users')) return;

        if($pagenow == 'user-edit.php' || $pagenow == 'profile.php') {

            add_action('show_user_profile', [$this, 'showUserMeta']);
            add_action('edit_user_profile', [$this, 'showUserMeta']);
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        }

        if(is_admin()) {

            add_action('wp_ajax_SS88_VUM_delete', [$this, 'deleteMeta']);
            add_action('wp_ajax_SS88_VUM_export', [$this, 'exportMeta']);

        }

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);

    }

    function admin_enqueue_scripts() {

        wp_enqueue_style('SS88_VUM-user', plugin_dir_url( __FILE__ ) . 'assets/css/user.css', false, $this->V);
        wp_enqueue_script('SS88_VUM-jsuser', plugin_dir_url( __FILE__ ) . 'assets/js/user.js', [], $this->V, true);

		wp_localize_script('SS88_VUM-jsuser', 'SS88_VUM_translations', [
			'confirm_delete' => __('Are you sure you wish to permanently delete this key and value?', 'view-user-metadata'),
			'error' => __('Error:', 'view-user-metadata'),
			'success' => __('Success!', 'view-user-metadata'),
			'locked_title' => __('Locked. Click to unlock deletion for this key.', 'view-user-metadata'),
			'unlocked_title' => __('Unlocked. Click to lock this key again.', 'view-user-metadata'),
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
	<label for="SS88VUM-toggle">Toggle</label>
	<span id="SS88VUM-export-wrap">
		<button type="button" id="SS88VUM-export-trigger" class="button"><?php esc_html_e('Export', 'view-user-metadata'); ?></button>
		<span id="SS88VUM-export-menu">
			<button type="button" data-format="csv"><?php esc_html_e('CSV', 'view-user-metadata'); ?></button>
			<button type="button" data-format="json"><?php esc_html_e('JSON', 'view-user-metadata'); ?></button>
		</span>
	</span>
</h2>

<div id="SS88-VUM-table-wrapper" data-uid="<?php echo intval($U->ID); ?>">
    <table class="form-table" role="presentation" id="SS88-VUM-table">
        <tbody>
            <?php foreach($UserMeta as $Key => $Value) { $ValueSingle = get_user_meta($U->ID, $Key, true); $IsProtected = $this->isProtectedMetaKey($Key); ?>
            <tr>
                <th><div class="flex-wrap">
					<?php if($IsProtected) { ?>
						<button class="btn-lock is-locked" data-lock="true" title="<?php echo esc_attr__('Locked. Click to unlock deletion for this key.', 'view-user-metadata'); ?>" aria-label="<?php echo esc_attr__('Locked. Click to unlock deletion for this key.', 'view-user-metadata'); ?>" type="button"><span class="dashicons dashicons-lock"></span></button>
					<?php } ?>
					<?php echo esc_html($Key); ?>
					<button class="btn-delete<?php echo ($IsProtected) ? ' is-hidden' : ''; ?>" data-key="<?php echo esc_html($Key); ?>" data-uid="<?php echo intval($U->ID); ?>" title="Delete this entry" type="button"><span class="dashicons dashicons-trash"></span></button>
                </div></th>
                <td>
                    <?php echo wp_kses_post($this->outputValue($ValueSingle)); ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

		<?php

	}

    function outputValue($Value) {

        if(is_array($Value)) return '<pre>' . esc_html(wp_json_encode($Value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</pre>';
        else return $Value;

    }

	function isProtectedMetaKey($MetaKey) {

		global $wpdb;

		$ProtectedExactKeys = [
			'session_tokens',
			'_application_passwords',
			'dismissed_wp_pointers',
			'first_name',
			'last_name',
			'nickname',
			'description'
		];

		if(in_array($MetaKey, $ProtectedExactKeys, true)) return true;

		$ProtectedPrefixes = [
			'billing_',
			'shipping_',
			'google_',
			'stripe_',
			'mailchimp_',
		];

		foreach($ProtectedPrefixes as $Prefix) {

			if(strpos($MetaKey, $Prefix) === 0) return true;

		}

		$BlogPrefix = (isset($wpdb->prefix)) ? $wpdb->prefix : 'wp_';

		if($MetaKey === $BlogPrefix . 'capabilities') return true;
		if($MetaKey === $BlogPrefix . 'user_level') return true;
		if(preg_match('/^wp_.*capabilities$/', $MetaKey)) return true;
		if(preg_match('/^wp_.*user_level$/', $MetaKey)) return true;
		if(strpos($MetaKey, '_') === 0) return true;

		return false;

	}

	function deleteMeta() {

		if(!check_ajax_referer('SS88_VUM_delete_nonce', 'nonce', false)) {

			wp_send_json_error(['httpcode' => 403, 'body' => __('Security check failed. Please refresh and try again.', 'view-user-metadata')], 403);

		}

		$UserID = isset($_POST['uid']) ? intval(wp_unslash($_POST['uid'])) : 0;
		$MetaKey = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';

		if(empty($MetaKey) || $UserID === 0) {

			wp_send_json_error(['httpcode' => -1, 'body' => __('Either the meta key or user ID was not supplied. Please refresh and try again.', 'view-user-metadata')]);

		}

		if(!current_user_can('edit_user', $UserID)) {

			wp_send_json_error(['httpcode' => -1, 'body' => __('You are not allowed to delete metadata for this user.', 'view-user-metadata')], 403);

		}

		$MetaExists = metadata_exists('user', $UserID, $MetaKey);

		if(!$MetaExists) {

			wp_send_json_error(['httpcode' => -1, 'body' => __('The meta key does not exist for this user. Nothing to delete.', 'view-user-metadata')]);

		}

		$DeleteMeta = delete_user_meta($UserID, $MetaKey);

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

		$UserID = isset($_POST['uid']) ? intval(wp_unslash($_POST['uid'])) : 0;
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

		foreach($UserMeta as $Key => $Value) {

			$ValueSingle = get_user_meta($UserID, $Key, true);
			$Rows[] = [
				'key' => $Key,
				'value' => $ValueSingle
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

			if(is_array($Value) || is_object($Value)) $Value = wp_json_encode($Value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			else if(is_bool($Value)) $Value = $Value ? 'true' : 'false';
			else if($Value === null) $Value = '';

			$CSVRows[] = '"' . str_replace('"', '""', (string) $Row['key']) . '","' . str_replace('"', '""', (string) $Value) . '"';

		}

		$Content = implode("\n", $CSVRows);

		wp_send_json_success([
			'filename' => 'user-meta-' . $UserID . '-' . $DateStamp . '.csv',
			'mime' => 'text/csv',
			'content' => $Content
		]);

	}

    function plugin_action_links($actions) {
        $mylinks = [
            '<a href="https://wordpress.org/support/plugin/view-user-metadata/" target="_blank" rel="noopener noreferrer">Need help?</a>',
        ];
        return array_merge( $actions, $mylinks );
    }

}

if(method_exists('SS88_ViewUserMetadata', 'init')) add_action('plugins_loaded', ['SS88_ViewUserMetadata', 'init']);
