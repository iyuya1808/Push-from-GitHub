<?php

/**
 * プラグイン編集フォーム
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if (! defined('ABSPATH')) {
	exit;
}

// エラーメッセージの表示
$github_push_error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
$github_push_error_message = isset($_GET['error_message']) ? urldecode(sanitize_text_field(wp_unslash($_GET['error_message']))) : '';

if (! empty($github_push_error)) {
	if (! empty($github_push_error_message)) {
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

$github_push_is_edit = ! empty($plugin_id) && ! empty($plugin);
$github_push_plugin_name = isset($plugin['plugin_name']) ? $plugin['plugin_name'] : '';
$github_push_repo_url = isset($plugin['repo_url']) ? $plugin['repo_url'] : '';
$github_push_branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';
$github_push_plugin_slug = isset($plugin['plugin_slug']) ? $plugin['plugin_slug'] : '';
$github_push_component_type = isset($plugin['type']) ? $plugin['type'] : 'plugin';
$github_push_theme_slug = isset($plugin['theme_slug']) ? $plugin['theme_slug'] : '';
$github_push_token = isset($plugin['token']) ? $plugin['token'] : '';
$github_push_use_tags = isset($plugin['use_tags']) ? $plugin['use_tags'] : false;

$github_push_show_plugin_fields = ('theme' !== $github_push_component_type);
$github_push_show_theme_fields = ('theme' === $github_push_component_type);
?>

<div class="wrap">
	<h1><?php echo $github_push_is_edit ? esc_html__('プラグイン / テーマを編集', 'push-from-github') : esc_html__('新しいプラグイン / テーマを追加', 'push-from-github'); ?></h1>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('github_push_save_plugin'); ?>
		<input type="hidden" name="action" value="github_push_save_plugin">
		<?php if ($github_push_is_edit) : ?>
			<input type="hidden" name="plugin_id" value="<?php echo esc_attr($plugin_id); ?>">
		<?php endif; ?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="repo_url"><?php echo esc_html__('GitHubリポジトリURL', 'push-from-github'); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="url" id="repo_url" name="repo_url" value="<?php echo esc_url($github_push_repo_url); ?>" class="regular-text" required>
						<p class="description">
							<?php echo esc_html__('管理したいGitHubリポジトリのURLを入力してください。', 'push-from-github'); ?>
							<br>
							<?php echo esc_html__('例: https://github.com/owner/repo または git@github.com:owner/repo.git', 'push-from-github'); ?>
							<br>
							<?php echo esc_html__('公開リポジトリと非公開リポジトリの両方に対応しています。非公開リポジトリの場合は、Personal Access Tokenの設定が必要です。', 'push-from-github'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php echo esc_html__('対象', 'push-from-github'); ?><span class="required">*</span></label>
					</th>
					<td>
						<fieldset id="github-push-component-type">
							<label style="margin-right: 1em;">
								<input type="radio" name="component_type" value="plugin" <?php checked($github_push_component_type, 'plugin'); ?>>
								<?php echo esc_html__('プラグイン', 'push-from-github'); ?>
							</label>
							<label>
								<input type="radio" name="component_type" value="theme" <?php checked($github_push_component_type, 'theme'); ?>>
								<?php echo esc_html__('テーマ', 'push-from-github'); ?>
							</label>
						</fieldset>
						<p class="description">
							<?php echo esc_html__('GitHubで管理しているWordPressプラグインまたはテーマを選択してください。', 'push-from-github'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="use_tags"><?php echo esc_html__('更新方法', 'push-from-github'); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="use_tags" name="use_tags" value="1" <?php checked($github_push_use_tags); ?>>
							<?php echo esc_html__('タグを使用する', 'push-from-github'); ?>
						</label>
						<p class="description">
							<?php echo esc_html__('プラグインの更新をどのように取得するかを選択します。', 'push-from-github'); ?>
							<br>
							<strong><?php echo esc_html__('タグを使用する場合:', 'push-from-github'); ?></strong>
							<?php echo esc_html__('GitHubのリリースタグ（例: v1.0.0, v1.2.3）から最新バージョンを取得します。セマンティックバージョニングに適しており、安定版のリリースを管理する場合に推奨されます。', 'push-from-github'); ?>
							<br>
							<strong><?php echo esc_html__('ブランチを使用する場合:', 'push-from-github'); ?></strong>
							<?php echo esc_html__('指定したブランチ（例: main, develop）の最新コミットから更新を取得します。継続的な開発や最新の変更を常に反映したい場合に適しています。', 'push-from-github'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="branch"><?php echo esc_html__('ブランチ名', 'push-from-github'); ?></label>
					</th>
					<td>
						<input type="text" id="branch" name="branch" value="<?php echo esc_attr($github_push_branch); ?>" class="regular-text">
						<p class="description">
							<?php echo esc_html__('更新を取得するGitHubブランチ名を指定してください。', 'push-from-github'); ?>
							<br>
							<?php echo esc_html__('一般的なブランチ名: main, master, develop, staging など', 'push-from-github'); ?>
							<br>
							<?php echo esc_html__('デフォルト: main（未入力の場合）', 'push-from-github'); ?>
							<br>
							<strong><?php echo esc_html__('注意:', 'push-from-github'); ?></strong>
							<?php echo esc_html__('「タグを使用する」にチェックが入っている場合は、この設定は無視され、タグから最新バージョンが取得されます。', 'push-from-github'); ?>
						</p>
					</td>
				</tr>
				<tr class="github-push-field github-push-field--plugin" <?php echo $github_push_show_plugin_fields ? '' : 'style="display:none;"'; ?>>
					<th scope="row">
						<label for="plugin_slug"><?php echo esc_html__('プラグインスラッグ', 'push-from-github'); ?> <span class="required">*</span></label>
					</th>
					<td>
						<select id="plugin_slug_select" class="regular-text" style="margin-bottom: 10px;">
							<option value=""><?php echo esc_html__('プラグインを選択してください', 'push-from-github'); ?></option>
							<?php foreach ($installed_plugins as $github_push_slug => $github_push_plugin_data) : ?>
								<?php if (! in_array($github_push_slug, $registered_slugs, true)) : ?>
									<option value="<?php echo esc_attr($github_push_slug); ?>" <?php selected($github_push_plugin_slug, $github_push_slug); ?>>
										<?php echo esc_html($github_push_plugin_data['Name']); ?> (<?php echo esc_html($github_push_slug); ?>)
									</option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
						<input type="text" id="plugin_slug" name="plugin_slug" value="<?php echo esc_attr($github_push_plugin_slug); ?>" class="regular-text" <?php echo ('plugin' === $github_push_component_type) ? 'required' : ''; ?>>
						<p class="description">
							<?php echo esc_html__('このGitHubリポジトリと連携するWordPressプラグインを指定してください。', 'push-from-github'); ?>
							<br>
							<?php echo esc_html__('上記のドロップダウンから既にインストールされているプラグインを選択するか、手動でプラグインスラッグを入力してください。', 'push-from-github'); ?>
							<br>
							<?php echo esc_html__('プラグインスラッグの形式: フォルダ名/メインファイル名.php', 'push-from-github'); ?>
							<br>
							<?php echo esc_html__('例: my-plugin/my-plugin.php, woocommerce/woocommerce.php', 'push-from-github'); ?>
							<br>
							<strong><?php echo esc_html__('注意:', 'push-from-github'); ?></strong>
							<?php echo esc_html__('既に他のGitHubリポジトリと連携されているプラグインは選択できません。', 'push-from-github'); ?>
						</p>
					</td>
				</tr>
				<tr class="github-push-field github-push-field--theme" <?php echo $github_push_show_theme_fields ? '' : 'style="display:none;"'; ?>>
					<th scope="row">
						<label for="theme_slug"><?php echo esc_html__('テーマスラッグ', 'push-from-github'); ?> <span class="required">*</span></label>
					</th>
					<td>
						<select id="theme_slug_select" class="regular-text" style="margin-bottom: 10px;">
							<option value=""><?php echo esc_html__('テーマを選択してください', 'push-from-github'); ?></option>
							<?php foreach ($installed_themes as $github_push_theme_slug_key => $github_push_theme_obj) : ?>
								<?php if (! in_array($github_push_theme_slug_key, $registered_theme_slugs, true)) : ?>
									<option value="<?php echo esc_attr($github_push_theme_slug_key); ?>" <?php selected($github_push_theme_slug, $github_push_theme_slug_key); ?>>
										<?php echo esc_html($github_push_theme_obj->get('Name')); ?> (<?php echo esc_html($github_push_theme_slug_key); ?>)
									</option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
						<input type="text" id="theme_slug" name="theme_slug" value="<?php echo esc_attr($github_push_theme_slug); ?>" class="regular-text" <?php echo ('theme' === $github_push_component_type) ? 'required' : ''; ?>>
						<p class="description">
							<?php echo esc_html__('このGitHubリポジトリと連携するWordPressテーマ（フォルダ名）を指定してください。', 'push-from-github'); ?>
							<br>
							<?php echo esc_html__('例: my-theme, twentytwentyfive など', 'push-from-github'); ?>
							<br>
							<?php echo esc_html__('既に他のリポジトリと紐付いているテーマは選択できません。', 'push-from-github'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="token"><?php echo esc_html__('Personal Access Token', 'push-from-github'); ?></label>
					</th>
					<td>
						<input type="password" id="token" name="token" value="<?php echo esc_attr($github_push_token); ?>" class="regular-text">
						<p class="description">
							<?php echo esc_html__('GitHub APIにアクセスするための認証トークンです。', 'push-from-github'); ?>
							<br>
							<strong><?php echo esc_html__('必要な場合:', 'push-from-github'); ?></strong>
							<?php echo esc_html__('非公開リポジトリにアクセスする場合は必須です。公開リポジトリでも使用可能で、APIレート制限が緩和されます（認証なし: 60リクエスト/時 → 認証あり: 5,000リクエスト/時）。', 'push-from-github'); ?>
							<br><br>
							<strong><?php echo esc_html__('トークンの作成方法:', 'push-from-github'); ?></strong>
							<?php echo esc_html__('GitHubの', 'push-from-github'); ?>
							<a href="https://github.com/settings/tokens" target="_blank"><?php echo esc_html__('Settings > Developer settings > Personal access tokens', 'push-from-github'); ?></a>
							<?php echo esc_html__('から「Generate new token (classic)」をクリックし、必要なスコープ（', 'push-from-github'); ?><code>repo</code><?php echo esc_html__(' または ', 'push-from-github'); ?><code>public_repo</code><?php echo esc_html__('）を選択してトークンを生成してください。', 'push-from-github'); ?>
							<br><br>
							<strong><?php echo esc_html__('注意:', 'push-from-github'); ?></strong>
							<?php echo esc_html__('トークンは機密情報です。他人に共有せず、漏洩した場合はすぐにGitHubで無効化してください。', 'push-from-github'); ?>
							<br>
							<a href="https://github.com/settings/tokens" target="_blank" class="button button-secondary" style="margin-top: 5px;">
								<?php echo esc_html__('GitHubでトークンを生成', 'push-from-github'); ?>
							</a>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="notice notice-warning inline" style="margin: 15px 0 10px 0; padding: 10px;">
			<p style="margin: 0;">
				<strong><?php echo esc_html__('重要: バージョン情報の取得について', 'push-from-github'); ?></strong>
				<br>
				<?php echo esc_html__('GitHubリポジトリにアップロードされているプラグイン/テーマのファイルには、WordPress標準のヘッダー形式（名前、Version など）が正しく記載されている必要があります。', 'push-from-github'); ?>
				<br>
				<?php echo esc_html__('ヘッダー形式が正しくない場合、バージョン情報を取得できず、バージョンが上がっても更新情報が表示されません。', 'push-from-github'); ?>
				<br>
				<?php echo esc_html__('プラグインの場合はメインPHPファイル、テーマの場合は style.css の先頭に以下のようなヘッダーが記載されているか確認してください:', 'push-from-github'); ?>
				<br>
				<code style="display: block; margin-top: 5px; padding: 5px; background: #f0f0f0;">
					<?php echo esc_html__('/**', 'push-from-github'); ?><br>
					<?php echo esc_html__('Plugin/Theme Name: 名前', 'push-from-github'); ?><br>
					<?php echo esc_html__('Version: 1.0.0', 'push-from-github'); ?><br>
					<?php echo esc_html__(' */', 'push-from-github'); ?>
				</code>
			<p style="margin-top: 12px;">
				<?php echo esc_html__('特にテーマの場合は、style.css の先頭に以下のようなヘッダーコメントを記述し、Version を更新することで正しく検知できます。', 'push-from-github'); ?>
			</p>
			<pre style="margin-top: 5px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; overflow: auto;">
/* 
Theme Name: technophere_corporate
Description: テクノフィアのホームページテーマ
Version: 1.0
Author: Yuya.I
*/
</pre>
			</p>
		</div>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('保存', 'push-from-github'); ?>">
			<a href="<?php echo esc_url(add_query_arg(array('page' => 'push-from-github'), admin_url('admin.php'))); ?>" class="button">
				<?php echo esc_html__('キャンセル', 'push-from-github'); ?>
			</a>
		</p>
	</form>
</div>