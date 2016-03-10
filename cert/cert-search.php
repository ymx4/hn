<?php
/*
Template Name: 证书查询页面
*/

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

		<?php while ( have_posts() ) : the_post();?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				</header><!-- .entry-header -->

				<div class="entry-content">
					<?php
						the_content();

						global $wpdb;
						$table_name = $wpdb->prefix . 'cert';
						$paged = max( 1, get_query_var('page') );
						$per_page = get_option('posts_per_page', 10);
						$offset = ($paged-1)*$per_page;
						$total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
						$pages = ceil($total/$per_page);

						$where = '';
						if (!empty($_POST['nq'])) {
							$where .= ' `name` like %s';
							$params[] = '%' . $wpdb->esc_like($_POST['nq']) . '%';
						}
						if (!empty($_POST['iq'])) {
							$where .= ' `identity` like %s';
							$params[] = '%' . $wpdb->esc_like($_POST['iq']) . '%';
						}
						if ($where) {
							array_unshift($params, "SELECT COUNT(id) FROM $table_name WHERE" . $where);
							$total = $wpdb->get_var(call_user_func_array(array($wpdb, 'prepare'), $params));
							$params[0] = "SELECT * FROM $table_name WHERE" . $where;
							$cert_list = $wpdb->get_results(call_user_func_array(array($wpdb, 'prepare'), $params), ARRAY_A);
						} else {
				            $total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
				            $cert_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);
				        }
			        ?>
					<form method="post">
						姓名：<input type="text" name="nq" value="<?php echo isset($_POST['nq']) ? esc_attr($_POST['nq']) : ''; ?>" />
						身份证：<input type="text" name="iq" value="<?php echo isset($_POST['iq']) ? esc_attr($_POST['iq']) : ''; ?>" />
						<input type="submit" value="搜索" />
					</form>
			        <?php

						if($cert_list){
					?>
					<div>共有 <?php echo $total;?> 条</div>
					<table class="table table-striped table-bordered table-hover reg-table">
						<thead>
							<tr>
								<th>姓名</th>
								<th>身份证</th>
								<th>证书编号</th>
							</tr>
						</thead>
						<tbody>
					<?php
							foreach( $cert_list as $cert ){
					?>
							<tr>
								<td><?php echo $cert['name']; ?></td>
								<td><?php echo $cert['identity']; ?></td>
								<td><?php echo $cert['cert_number']; ?></td>
							</tr>
					<?php
							}
					?>
						</tbody>
					</table>
					<?php
							//TODO echo dmeng_pager($paged, $pages);
						} else {
					?>
					<div>未查询到相关证书</div>
					<?php } ?>
				</div><!-- .entry-content -->

			</article>
		<?php endwhile; // end of the loop. ?>
	</main><!-- .site-main -->

	<?php get_sidebar( 'content-bottom' ); ?>

</div><!-- .content-area -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
