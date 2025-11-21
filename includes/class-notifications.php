<?php

/**
 * 通知機能クラス
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if (! defined('ABSPATH')) {
	exit;
}

/**
 * 通知機能クラス
 */
class GitHub_Push_Notifications
{

	/**
	 * インスタンス
	 */
	private static $instance = null;

	/**
	 * シングルトンインスタンスを取得
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ
	 */
	private function __construct() {}

	/**
	 * 更新通知を送信
	 *
	 * @param string $plugin_id プラグインID
	 * @param string $version バージョン
	 */
	public function send_update_notification($plugin_id, $version)
	{
		$plugin = $this->get_plugin_data($plugin_id);

		if (! $plugin) {
			return;
		}

		$label = $this->get_component_label($plugin);
		$name = isset($plugin['plugin_name']) ? $plugin['plugin_name'] : '';

		$message = sprintf(
			// translators: %1$s: Component label, %2$s: Component name, %3$s: Version number
			__('%1$s「%2$s」が %3$s に更新されました。', 'push-from-github'),
			$label,
			$name,
			$version
		);

		// 管理画面通知
		$this->add_admin_notice($plugin_id, $message, 'success');

		// メール通知
		$options = get_option('github_push_options', array());

		if (isset($options['email_notifications']) && $options['email_notifications']) {
			$this->send_email_notification($plugin_id, $message);
		}
	}

	/**
	 * ロールバック通知を送信
	 *
	 * @param string $plugin_id プラグインID
	 */
	public function send_rollback_notification($plugin_id)
	{
		$plugin = $this->get_plugin_data($plugin_id);

		if (! $plugin) {
			return;
		}

		$label = $this->get_component_label($plugin);
		$name = isset($plugin['plugin_name']) ? $plugin['plugin_name'] : '';

		$message = sprintf(
			// translators: %1$s: Component label, %2$s: Component name
			__('%1$s「%2$s」がロールバックされました。', 'push-from-github'),
			$label,
			$name
		);

		// 管理画面通知
		$this->add_admin_notice($plugin_id, $message, 'info');

		// メール通知
		$options = get_option('github_push_options', array());

		if (isset($options['email_notifications']) && $options['email_notifications']) {
			$this->send_email_notification($plugin_id, $message);
		}
	}

	/**
	 * 更新可能通知を送信
	 *
	 * @param string $plugin_id プラグインID
	 * @param string $version バージョン
	 */
	public function send_update_available_notification($plugin_id, $version)
	{
		$plugin = $this->get_plugin_data($plugin_id);

		if (! $plugin) {
			return;
		}

		$label = $this->get_component_label($plugin);
		$name = isset($plugin['plugin_name']) ? $plugin['plugin_name'] : '';

		$message = sprintf(
			// translators: %1$s: Component label, %2$s: Component name, %3$s: Version number
			__('%1$s「%2$s」の新しいバージョン %3$s が利用可能です。', 'push-from-github'),
			$label,
			$name,
			$version
		);

		// 管理画面通知
		$this->add_admin_notice($plugin_id, $message, 'warning');

		// メール通知
		$options = get_option('github_push_options', array());

		if (isset($options['email_notifications']) && $options['email_notifications']) {
			$this->send_email_notification($plugin_id, $message);
		}
	}

	/**
	 * エラー通知を送信
	 *
	 * @param string $plugin_id プラグインID
	 * @param string $error_message エラーメッセージ
	 */
	public function send_error_notification($plugin_id, $error_message)
	{
		$plugin = $this->get_plugin_data($plugin_id);

		if (! $plugin) {
			return;
		}

		$label = $this->get_component_label($plugin);
		$name = isset($plugin['plugin_name']) ? $plugin['plugin_name'] : '';

		$message = sprintf(
			// translators: %1$s: Component label, %2$s: Component name, %3$s: Error message
			__('%1$s「%2$s」の更新中にエラーが発生しました: %3$s', 'push-from-github'),
			$label,
			$name,
			$error_message
		);

		// 管理画面通知
		$this->add_admin_notice($plugin_id, $message, 'error');

		// メール通知
		$options = get_option('github_push_options', array());

		if (isset($options['email_notifications']) && $options['email_notifications']) {
			$this->send_email_notification($plugin_id, $message);
		}
	}

	/**
	 * 管理画面通知を追加
	 *
	 * @param string $plugin_id プラグインID
	 * @param string $message メッセージ
	 * @param string $type 通知タイプ
	 */
	private function add_admin_notice($plugin_id, $message, $type = 'info')
	{
		// Transientを使用して、一度表示したら削除されるようにする
		$transient_key = 'github_push_notice_' . md5($plugin_id . $message . time());
		set_transient($transient_key, array(
			'plugin_id' => $plugin_id,
			'message' => $message,
			'type' => $type,
		), 30); // 30秒間のみ有効

		// ログ用にオプションにも保存（表示用ではない）
		$notices = get_option('github_push_notices', array());

		$notices[] = array(
			'plugin_id' => $plugin_id,
			'message' => $message,
			'type' => $type,
			'created_at' => current_time('mysql'),
		);

		// 最新50件のみ保持
		if (count($notices) > 50) {
			$notices = array_slice($notices, -50);
		}

		update_option('github_push_notices', $notices);
	}

	/**
	 * メール通知を送信
	 *
	 * @param string $plugin_id プラグインID
	 * @param string $message メッセージ
	 */
	private function send_email_notification($plugin_id, $message)
	{
		$admin_email = get_option('admin_email');

		if (empty($admin_email)) {
			return;
		}

		$subject = sprintf(
			'[%s] %s',
			get_bloginfo('name'),
			__('Push from GitHub 通知', 'push-from-github')
		);

		$body = $message . "\n\n";
		// translators: %s: Site URL
		$body .= sprintf(__('サイト: %s', 'push-from-github'), home_url()) . "\n";
		// translators: %s: Date and time
		$body .= sprintf(__('日時: %s', 'push-from-github'), current_time('mysql')) . "\n";

		wp_mail($admin_email, $subject, $body);
	}

	/**
	 * 管理画面通知を表示
	 */
	public function display_admin_notices()
	{
		global $wpdb;

		// Transientから通知を取得（一度表示したら削除される）
		$transients = $wpdb->get_col($wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like('_transient_github_push_notice_') . '%'
		));

		$displayed_notices = array();

		foreach ($transients as $transient_name) {
			// _transient_プレフィックスを削除
			$transient_key = str_replace(array('_transient_', '_transient_timeout_'), '', $transient_name);

			// timeoutでない場合のみ処理
			if (strpos($transient_name, '_transient_timeout_') === false) {
				$notice = get_transient($transient_key);

				if ($notice && is_array($notice)) {
					$displayed_notices[] = $notice;
					// 表示後は削除
					delete_transient($transient_key);
				}
			}
		}

		// 通知を表示
		foreach ($displayed_notices as $notice) {
			$type = isset($notice['type']) ? $notice['type'] : 'info';
			$class = 'notice notice-' . $type . ' is-dismissible';

			echo '<div class="' . esc_attr($class) . '">';
			echo '<p>' . esc_html($notice['message']) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * プラグインデータを取得
	 *
	 * @param string $plugin_id プラグインID
	 * @return array|false プラグイン情報またはfalse
	 */
	private function get_plugin_data($plugin_id)
	{
		$plugins = get_option('github_push_plugins', array());

		if (! isset($plugins[$plugin_id])) {
			return false;
		}

		return $plugins[$plugin_id];
	}

	/**
	 * 対象の種類ラベルを取得
	 *
	 * @param array $plugin プラグイン情報
	 * @return string ラベル
	 */
	private function get_component_label($plugin)
	{
		return (isset($plugin['type']) && $plugin['type'] === 'theme')
			? __('テーマ', 'push-from-github')
			: __('プラグイン', 'push-from-github');
	}
}
