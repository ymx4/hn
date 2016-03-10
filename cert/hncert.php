<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Hncert_List_Table extends WP_List_Table
{
    function __construct()
    {
        global $page;

        parent::__construct(array(
            'singular' => 'cert',
            'plural' => 'certs',
        ));
    }

    function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    function column_name($item)
    {
        $actions = array(
            'edit' => sprintf('<a href="?page=cert_form&id=%s">%s</a>', $item['id'], '编辑'),
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], '删除'),
        );

        return sprintf('%s %s',
            $item['name'],
            $this->row_actions($actions)
        );
    }

    function get_columns()
    {
        $columns = array(
            'name' => '姓名',
            'identity' => '身份证',
            'cert_number' => '证书编号',
        );
        return $columns;
    }

    function get_bulk_actions()
    {
        $actions = array(
            'delete' => '删除'
        );
        return $actions;
    }

    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cert';

        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (is_array($ids)) $ids = implode(',', $ids);

            if (!empty($ids)) {
                $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
            }
        }
    }

    function prepare_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cert';

        $per_page = 10;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged']) - 1) : 0;
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'id';
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

        if (!empty($_REQUEST['certq'])) {
            $certq = '%' . $wpdb->esc_like($_REQUEST['certq']) . '%';
            $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE `name` like %s or `identity` like %s or `cert_number` like %s", $certq, $certq, $certq));
            $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE `name` like %s or `identity` like %s or `cert_number` like %s ORDER BY $orderby $order LIMIT %d OFFSET %d", $certq, $certq, $certq, $per_page, $per_page * $paged), ARRAY_A);
        } else {
            $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
            $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $per_page * $paged), ARRAY_A);
        }

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
}

function hncert_admin_menu()
{
    add_menu_page('证书', '证书', 'edit_pages', 'cert_list', 'hncert_cert_list_page_handler');
    add_submenu_page('cert_list', '添加', '添加', 'edit_pages', 'cert_form', 'hncert_cert_form_page_handler');
}

add_action('admin_menu', 'hncert_admin_menu');

function hncert_cert_list_page_handler()
{
    global $wpdb;

    $table = new Hncert_List_Table();
    $table->prepare_items();

    $message = '';
    ?>
<div class="wrap">

    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2>证书<a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=cert_form');?>">添加</a>
    </h2>
    <?php echo $message; ?>

    <form id="cert-table" method="GET">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
        <p class="search-box">
            <label class="screen-reader-text" for="post-search-input">搜索证书:</label>
            <input type="text" name="certq" value="<?php echo isset($_REQUEST['certq']) ? $_REQUEST['certq'] : ''; ?>">
            <input type="submit" class="button" value="搜索证书">
        </p>
        <?php $table->display() ?>
    </form>

</div>
<?php
}

function hncert_cert_form_page_handler()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'cert';

    $message = '';
    $notice = '';

    $default = array(
        'id' => 0,
        'name' => '',
        'identity' => '',
        'cert_number' => '',
    );

    if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'cert-nonce')) {

        $item = shortcode_atts($default, $_REQUEST);

        $item_valid = hncert_validate($item);
        if ($item_valid === true) {
            if ($item['id'] == 0) {
                $item['create_time'] = $item['update_time'] = time();
                $result = $wpdb->insert($table_name, $item);
                $item['id'] = $wpdb->insert_id;
                if ($result) {
                    $message = '保存成功！';
                } else {
                    $notice = '保存出错！';
                }
            } else {
                $item['update_time'] = time();
                $result = $wpdb->update($table_name, $item, array('id' => $item['id']));
                if ($result) {
                    $message = '保存成功！';
                } else {
                    $notice = '保存出错！';
                }
            }
        } else {
            $notice = $item_valid;
        }
    } else {
        $item = $default;
        if (isset($_REQUEST['id'])) {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $_REQUEST['id']), ARRAY_A);
            if (!$item) {
                $item = $default;
                $notice = '证书未找到';
            }
        }
    }

    add_meta_box('cert_form_meta_box', '录入证书', 'hncert_cert_form_meta_box_handler', 'cert', 'normal', 'default');

    ?>
<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2>证书 <a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=cert_list');?>">返回</a>
    </h2>

    <?php if (!empty($notice)): ?>
    <div id="notice" class="error"><p><?php echo $notice; ?></p></div>
    <?php endif;?>
    <?php if (!empty($message)): ?>
    <div id="message" class="updated"><p><?php echo $message; ?></p></div>
    <?php endif;?>

    <form id="form" method="POST">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('cert-nonce')?>"/>
        <input type="hidden" name="id" value="<?php echo $item['id'] ?>"/>

        <div class="metabox-holder" id="poststuff">
            <div id="post-body">
                <div id="post-body-content">
                    <?php do_meta_boxes('cert', 'normal', $item); ?>
                    <input type="submit" value="保存" id="submit" class="button-primary" name="submit">
                </div>
            </div>
        </div>
    </form>
</div>
<?php
}

function hncert_cert_form_meta_box_handler($item)
{
    ?>

<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
    <tbody>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="name">姓名</label>
        </th>
        <td>
            <input id="name" name="name" type="text" style="width: 95%" value="<?php echo esc_attr($item['name'])?>"
                   size="50" class="code" required>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="identity">身份证</label>
        </th>
        <td>
            <input id="identity" name="identity" type="text" style="width: 95%" value="<?php echo esc_attr($item['identity'])?>"
                   size="50" class="code" required>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="cert_number">证书编号</label>
        </th>
        <td>
            <input id="cert_number" name="cert_number" type="text" style="width: 95%" value="<?php echo esc_attr($item['cert_number'])?>"
                   size="50" class="code" required>
        </td>
    </tr>
    </tbody>
</table>
<?php
}

function hncert_validate($item)
{
    $messages = array();

    if (empty($item['name'])) $messages[] = '姓名必填';
    if (empty($item['identity']) || strlen($item['identity']) != 18) $messages[] = '身份证填写有误';
    if (empty($item['cert_number'])) $messages[] = '证书编号必填';

    if (empty($messages)) return true;
    return implode('<br />', $messages);
}
