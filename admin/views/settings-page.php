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
$github_push_message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : '';
$github_push_error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';

if ($github_push_message === 'saved') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('設定を保存しました', 'push-from-github') . '</p></div>';
} elseif ($github_push_message === 'deleted') {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('対象を削除しました', 'push-from-github') . '</p></div>';
}

if ($github_push_error === 'missing_fields') {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('必須項目が入力されていません', 'push-from-github') . '</p></div>';
} elseif ($github_push_error === 'plugin_not_found') {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('プラグインが見つかりません', 'push-from-github') . '</p></div>';
} elseif (!empty($github_push_error)) {
	$github_push_error_message = isset($_GET['error_message']) ? urldecode(sanitize_text_field(wp_unslash($_GET['error_message']))) : '';
	if (!empty($github_push_error_message)) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($github_push_error_message) . '</p></div>';
	} else {
		// エラーコードに基づいてメッセージを表示
		$github_push_error_messages = array(
			'repo_not_found' => __('指定されたGitHubリポジトリが見つかりませんでした。', 'push-from-github'),
			'repo_access_error' => __('リポジトリにアクセスできませんでした。', 'push-from-github'),
			'branch_access_error' => __('指定されたブランチにアクセスできませんでした。', 'push-from-github'),
			'plugin_file_not_found' => __('プラグインファイルが見つかりませんでした。リポジトリがWordPressプラグインではない可能性があります。', 'push-from-github'),
			'plugin_file_read_error' => __('プラグインファイルの読み込みに失敗しました。', 'push-from-github'),
			'plugin_file_content_error' => __('プラグインファイルの内容が取得できませんでした。', 'push-from-github'),
			'plugin_file_decode_error' => __('プラグインファイルの内容をデコードできませんでした。', 'push-from-github'),
			'invalid_plugin_header' => __('プラグインヘッダーが正しく記載されていません。このリポジトリはWordPressプラグインではない可能性があります。', 'push-from-github'),
			'invalid_url' => __('無効なGitHub URLです。', 'push-from-github'),
		);
		$github_push_message = isset($github_push_error_messages[$github_push_error]) ? $github_push_error_messages[$github_push_error] : __('エラーが発生しました', 'push-from-github');
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($github_push_message) . '</p></div>';
	}
}
?>

<div class="wrap">
	<h1><?php echo esc_html__('Push from GitHub 設定', 'push-from-github'); ?></h1>

	<div class="github-push-description" style="margin: 20px 0;">
		<p><?php echo esc_html__('GitHubで管理されているWordPressプラグイン / テーマを登録・管理できます。非公開リポジトリにも対応しています。', 'push-from-github'); ?></p>
	</div>

	<div class="github-push-header">
		<a href="<?php echo esc_url(add_query_arg(array('page' => 'push-from-github', 'action' => 'edit'), admin_url('admin.php'))); ?>" class="button button-primary">
			<?php echo esc_html__('追加', 'push-from-github'); ?>
		</a>
	</div>

	<?php if (empty($plugins)) : ?>
		<div class="github-push-empty">
			<p><?php echo esc_html__('登録されている項目はありません。', 'push-from-github'); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__('名前', 'push-from-github'); ?></th>
					<th><?php echo esc_html__('タイプ', 'push-from-github'); ?></th>
					<th><?php echo esc_html__('リポジトリURL', 'push-from-github'); ?></th>
					<th><?php echo esc_html__('ブランチ/タグ', 'push-from-github'); ?></th>
					<th><?php echo esc_html__('現在のバージョン', 'push-from-github'); ?></th>
					<th><?php echo esc_html__('操作', 'push-from-github'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($plugins as $github_push_plugin_id => $plugin) : ?>
					<?php
					$github_push_github_api = GitHub_Push_Github_API::get_instance();
					// 現在のバージョンを取得（常に最新の情報を取得）
					$github_push_current_version = $github_push_github_api->get_current_version($github_push_plugin_id);
					// 更新チェック（キャッシュを使用）
					$github_push_update_info = $github_push_github_api->check_for_updates($github_push_plugin_id);
					$github_push_has_update = ! is_wp_error($github_push_update_info) && isset($github_push_update_info['update_available']) && $github_push_update_info['update_available'];
					?>
					<tr>
						<td>
							<strong><?php echo esc_html($plugin['plugin_name']); ?></strong>
							<?php if ($github_push_has_update) : ?>
								<span class="update-available"><?php echo esc_html__('更新あり', 'push-from-github'); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$type = isset($plugin['type']) ? $plugin['type'] : 'plugin';
							echo esc_html($type === 'theme' ? __('テーマ', 'push-from-github') : __('プラグイン', 'push-from-github'));
							?>
						</td>
						<td>
							<a href="<?php echo esc_url($plugin['repo_url']); ?>" target="_blank">
								<?php echo esc_html($plugin['repo_url']); ?>
							</a>
						</td>
						<td>
							<?php
							if (isset($plugin['use_tags']) && $plugin['use_tags']) {
								echo esc_html__('タグ', 'push-from-github');
							} else {
								echo esc_html(isset($plugin['branch']) ? $plugin['branch'] : 'main');
							}
							?>
						</td>
						<td>
							<?php echo esc_html($github_push_current_version); ?>
							<?php if ($github_push_has_update && isset($github_push_update_info['latest_version'])) : ?>
								→ <strong><?php echo esc_html($github_push_update_info['latest_version']); ?></strong>
							<?php endif; ?>
						</td>
						<td>
							<button class="button button-small check-update" data-plugin-id="<?php echo esc_attr($github_push_plugin_id); ?>">
								<?php echo esc_html__('更新チェック', 'push-from-github'); ?>
							</button>
							<?php if ($github_push_has_update) : ?>
								<button class="button button-primary button-small update-plugin" data-plugin-id="<?php echo esc_attr($github_push_plugin_id); ?>">
									<?php echo esc_html__('更新', 'push-from-github'); ?>
								</button>
							<?php endif; ?>
							<a href="<?php echo esc_url(add_query_arg(array('page' => 'push-from-github', 'action' => 'edit', 'plugin_id' => $github_push_plugin_id), admin_url('admin.php'))); ?>" class="button button-small">
								<?php echo esc_html__('編集', 'push-from-github'); ?>
							</a>
							<a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'github_push_delete_plugin', 'plugin_id' => $github_push_plugin_id), admin_url('admin-post.php')), 'github_push_delete_plugin')); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('本当に削除しますか？', 'push-from-github')); ?>');">
								<?php echo esc_html__('削除', 'push-from-github'); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div id="github-push-modal" class="github-push-modal" style="display: none;">
	<div class="github-push-modal-content">
		<span class="github-push-modal-close">&times;</span>
		<div class="github-push-modal-body">
			<p class="github-push-modal-message"></p>
		</div>
	</div>
</div>