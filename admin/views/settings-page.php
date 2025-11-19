<?php

/**
 * 設定画面テンプレート
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if (! defined('ABSPATH')) {
	exit;
}

// メッセージの表示
$message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

if ($message === 'saved') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('プラグインを保存しました', 'github-push') . '</p></div>';
} elseif ($message === 'deleted') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('プラグインを削除しました', 'github-push') . '</p></div>';
}

if ($error === 'missing_fields') {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('必須項目が入力されていません', 'github-push') . '</p></div>';
} elseif ($error === 'plugin_not_found') {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('プラグインが見つかりません', 'github-push') . '</p></div>';
} elseif (!empty($error)) {
	$error_message = isset($_GET['error_message']) ? urldecode(sanitize_text_field($_GET['error_message'])) : '';
	if (!empty($error_message)) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
	} else {
		// エラーコードに基づいてメッセージを表示
		$error_messages = array(
			'repo_not_found' => __('指定されたGitHubリポジトリが見つかりませんでした。', 'github-push'),
			'repo_access_error' => __('リポジトリにアクセスできませんでした。', 'github-push'),
			'branch_access_error' => __('指定されたブランチにアクセスできませんでした。', 'github-push'),
			'plugin_file_not_found' => __('プラグインファイルが見つかりませんでした。リポジトリがWordPressプラグインではない可能性があります。', 'github-push'),
			'plugin_file_read_error' => __('プラグインファイルの読み込みに失敗しました。', 'github-push'),
			'plugin_file_content_error' => __('プラグインファイルの内容が取得できませんでした。', 'github-push'),
			'plugin_file_decode_error' => __('プラグインファイルの内容をデコードできませんでした。', 'github-push'),
			'invalid_plugin_header' => __('プラグインヘッダーが正しく記載されていません。このリポジトリはWordPressプラグインではない可能性があります。', 'github-push'),
			'invalid_url' => __('無効なGitHub URLです。', 'github-push'),
		);
		$message = isset($error_messages[$error]) ? $error_messages[$error] : __('エラーが発生しました', 'github-push');
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
	}
}
?>

<div class="wrap">
	<h1><?php echo esc_html__('WP Push from GitHub 設定', 'github-push'); ?></h1>

	<div class="github-push-description" style="margin: 20px 0;">
		<p><?php echo esc_html__('GitHubで管理されているWordPressプラグインを登録・管理できます。非公開リポジトリにも対応しています。', 'github-push'); ?></p>
	</div>

	<div class="github-push-header">
		<a href="<?php echo esc_url(add_query_arg(array('page' => 'github-push', 'action' => 'edit'), admin_url('admin.php'))); ?>" class="button button-primary">
			<?php echo esc_html__('追加', 'github-push'); ?>
		</a>
	</div>

	<?php if (empty($plugins)) : ?>
		<div class="github-push-empty">
			<p><?php echo esc_html__('登録されているプラグインはありません。', 'github-push'); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__('プラグイン名', 'github-push'); ?></th>
					<th><?php echo esc_html__('リポジトリURL', 'github-push'); ?></th>
					<th><?php echo esc_html__('ブランチ/タグ', 'github-push'); ?></th>
					<th><?php echo esc_html__('現在のバージョン', 'github-push'); ?></th>
					<th><?php echo esc_html__('操作', 'github-push'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($plugins as $plugin_id => $plugin) : ?>
					<?php
					$github_api = GitHub_Push_Github_API::get_instance();
					// 現在のバージョンを取得（常に最新の情報を取得）
					$current_version = $github_api->get_current_version($plugin_id);
					// 更新チェック（キャッシュを使用）
					$update_info = $github_api->check_for_updates($plugin_id);
					$has_update = ! is_wp_error($update_info) && isset($update_info['update_available']) && $update_info['update_available'];
					?>
					<tr>
						<td>
							<strong><?php echo esc_html($plugin['plugin_name']); ?></strong>
							<?php if ($has_update) : ?>
								<span class="update-available"><?php echo esc_html__('更新あり', 'github-push'); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url($plugin['repo_url']); ?>" target="_blank">
								<?php echo esc_html($plugin['repo_url']); ?>
							</a>
						</td>
						<td>
							<?php
							if (isset($plugin['use_tags']) && $plugin['use_tags']) {
								echo esc_html__('タグ', 'github-push');
							} else {
								echo esc_html(isset($plugin['branch']) ? $plugin['branch'] : 'main');
							}
							?>
						</td>
						<td>
							<?php echo esc_html($current_version); ?>
							<?php if ($has_update && isset($update_info['latest_version'])) : ?>
								→ <strong><?php echo esc_html($update_info['latest_version']); ?></strong>
							<?php endif; ?>
						</td>
						<td>
							<button class="button button-small check-update" data-plugin-id="<?php echo esc_attr($plugin_id); ?>">
								<?php echo esc_html__('更新チェック', 'github-push'); ?>
							</button>
							<?php if ($has_update) : ?>
								<button class="button button-primary button-small update-plugin" data-plugin-id="<?php echo esc_attr($plugin_id); ?>">
									<?php echo esc_html__('更新', 'github-push'); ?>
								</button>
							<?php endif; ?>
							<a href="<?php echo esc_url(add_query_arg(array('page' => 'github-push', 'action' => 'edit', 'plugin_id' => $plugin_id), admin_url('admin.php'))); ?>" class="button button-small">
								<?php echo esc_html__('編集', 'github-push'); ?>
							</a>
							<a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'github_push_delete_plugin', 'plugin_id' => $plugin_id), admin_url('admin-post.php')), 'github_push_delete_plugin')); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('本当に削除しますか？', 'github-push')); ?>');">
								<?php echo esc_html__('削除', 'github-push'); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	
	<div class="github-push-developer-info" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 13px;">
		<p>
			<?php echo esc_html__('開発者:', 'github-push'); ?> 
			<a href="https://technophere.codm" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('テクノフィア', 'github-push'); ?></a>
		</p>
	</div>
</div>

<div id="github-push-modal" class="github-push-modal" style="display: none;">
	<div class="github-push-modal-content">
		<span class="github-push-modal-close">&times;</span>
		<div class="github-push-modal-body">
			<p class="github-push-modal-message"></p>
		</div>
	</div>
</div>