<?php
/**
 * プラグイン編集フォーム
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $plugin_id ) && ! empty( $plugin );
$plugin_name = isset( $plugin['plugin_name'] ) ? $plugin['plugin_name'] : '';
$repo_url = isset( $plugin['repo_url'] ) ? $plugin['repo_url'] : '';
$branch = isset( $plugin['branch'] ) ? $plugin['branch'] : 'main';
$plugin_slug = isset( $plugin['plugin_slug'] ) ? $plugin['plugin_slug'] : '';
$token = isset( $plugin['token'] ) ? $plugin['token'] : '';
$use_tags = isset( $plugin['use_tags'] ) ? $plugin['use_tags'] : false;
?>

<div class="wrap">
	<h1><?php echo $is_edit ? esc_html__( 'プラグインを編集', 'github-push' ) : esc_html__( '新しいプラグインを追加', 'github-push' ); ?></h1>
	
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'github_push_save_plugin' ); ?>
		<input type="hidden" name="action" value="github_push_save_plugin">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="plugin_id" value="<?php echo esc_attr( $plugin_id ); ?>">
		<?php endif; ?>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="repo_url"><?php echo esc_html__( 'GitHubリポジトリURL', 'github-push' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="url" id="repo_url" name="repo_url" value="<?php echo esc_url( $repo_url ); ?>" class="regular-text" required>
						<p class="description"><?php echo esc_html__( '例: https://github.com/owner/repo または git@github.com:owner/repo.git', 'github-push' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="use_tags"><?php echo esc_html__( '更新方法', 'github-push' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="use_tags" name="use_tags" value="1" <?php checked( $use_tags ); ?>>
							<?php echo esc_html__( 'タグを使用する', 'github-push' ); ?>
						</label>
						<p class="description">
							<?php echo esc_html__( 'チェックを入れると、GitHubのタグ（例: v1.0.0）から最新バージョンを取得します。', 'github-push' ); ?>
							<br>
							<?php echo esc_html__( 'チェックを外すと、指定したブランチ（例: main）の最新コミットから更新を取得します。', 'github-push' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="branch"><?php echo esc_html__( 'ブランチ名', 'github-push' ); ?></label>
					</th>
					<td>
						<input type="text" id="branch" name="branch" value="<?php echo esc_attr( $branch ); ?>" class="regular-text">
						<p class="description"><?php echo esc_html__( 'タグを使用する場合は無視されます。デフォルト: main', 'github-push' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="plugin_slug"><?php echo esc_html__( 'プラグインスラッグ', 'github-push' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<select id="plugin_slug_select" class="regular-text" style="margin-bottom: 10px;">
							<option value=""><?php echo esc_html__( 'プラグインを選択してください', 'github-push' ); ?></option>
							<?php foreach ( $installed_plugins as $slug => $plugin_data ) : ?>
								<?php if ( ! in_array( $slug, $registered_slugs, true ) ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $plugin_slug, $slug ); ?>>
										<?php echo esc_html( $plugin_data['Name'] ); ?> (<?php echo esc_html( $slug ); ?>)
									</option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
						<input type="text" id="plugin_slug" name="plugin_slug" value="<?php echo esc_attr( $plugin_slug ); ?>" class="regular-text" required>
						<p class="description">
							<?php echo esc_html__( '上記のドロップダウンから選択するか、手動で入力してください。', 'github-push' ); ?>
							<?php echo esc_html__( '例: my-plugin/my-plugin.php', 'github-push' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="token"><?php echo esc_html__( 'Personal Access Token', 'github-push' ); ?></label>
					</th>
					<td>
						<input type="password" id="token" name="token" value="<?php echo esc_attr( $token ); ?>" class="regular-text">
						<p class="description">
							<?php echo esc_html__( '非公開リポジトリにアクセスする場合は必須です。', 'github-push' ); ?>
							<a href="https://github.com/settings/tokens" target="_blank"><?php echo esc_html__( 'トークンを生成', 'github-push' ); ?></a>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( '保存', 'github-push' ); ?>">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'github-push' ), admin_url( 'admin.php' ) ) ); ?>" class="button">
				<?php echo esc_html__( 'キャンセル', 'github-push' ); ?>
			</a>
		</p>
	</form>
</div>

