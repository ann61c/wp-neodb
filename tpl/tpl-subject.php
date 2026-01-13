<div class="wrap">
    <h2>标记的条目</h2>
    <?php
    require_once WPN_PATH . '/src/subject-table.php';
    $table = new Subject_List_Table();
    $table->prepare_items(); ?>
    <div class="wpn-actions-wrapper">
        <div class="wpn-left-filters">
            <?php $table->views(); ?>
        </div>
        <form id="posts-filter" method="get" action="admin.php">
            <input type="hidden" name="page" value="subject" />
            <?php $table->search_box('搜索条目', 'subject-name'); ?>
        </form>
    </div>
    <?php
    $table->display();
    ?>
</div>