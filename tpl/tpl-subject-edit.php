<div class="wrap">
    <h2>编辑条目</h2>
    <?php $subject_id = $_GET['subject_id'];
    global $wpdb;
    $subject = $subject_id ? $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE id = '{$subject_id}'") : "";
    $fave = $subject_id ? $wpdb->get_row("SELECT * FROM $wpdb->douban_faves WHERE subject_id = '{$subject_id}'") : "";
    $action = $_GET['action'] ? $_GET['action'] : 'edit_fave';
    ?>
    <form method="post">
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row"><label for="url">条目</label></th>
                    <td>
                    <p><img src="<?php
                                    $type = $subject->neodb_id ? 'neodb_' : ($subject->tmdb_id ? 'tmdb' : '');
                                    $id = $subject->neodb_id ? $subject->neodb_id : ($subject->tmdb_id ? $subject->tmdb_id : $subject->douban_id);
                                    $e = ABSPATH . 'douban_cache/' . $type . $id . '.jpg';
                                    echo (is_file($e) ? home_url('/') . 'douban_cache/' . $type . $id . '.jpg' : $subject->poster); ?>" width="100" /></p>
                        <p><?php echo (is_file($e) ? '封面已缓存' : '因豆瓣原因，原始封面可能无法显示。'); ?></p>
                        <p><?php echo $subject->name; ?>
                            <?php 
                            $source_name = $subject->neodb_id ? 'NeoDB' : ($subject->tmdb_id ? 'TMDB' : '豆瓣');
                            $source_class = $subject->neodb_id ? 'db--titletag-neodb' : ($subject->tmdb_id ? 'db--titletag-tmdb' : 'db--titletag-douban');
                            ?>
                            <span class="db--titletag <?php echo $source_class; ?>"><?php echo $source_name; ?></span>
                        </p>
                        <p><?php echo $subject->card_subtitle; ?></p>
                    </td>
                </tr>
                <?php
                // Show source switcher based on edit mode
                $has_multiple_sources = false;
                $available_sources = [];
                
                if ($action == 'edit_subject') {
                    // For editing subject info, show all available sources
                    if (!empty($subject->douban_id)) {
                        $available_sources[] = ['name' => '豆瓣', 'id' => $subject->douban_id, 'param' => 'douban'];
                    }
                    if (!empty($subject->neodb_id)) {
                        $available_sources[] = ['name' => 'NeoDB', 'id' => $subject->neodb_id, 'param' => 'neodb'];
                    }
                    if (!empty($subject->tmdb_id)) {
                        $available_sources[] = ['name' => 'TMDB', 'id' => $subject->tmdb_id . ' (' . $subject->tmdb_type . ')', 'param' => 'tmdb'];
                    }
                } else {
                    // For editing user favorites, only show NeoDB (it has user marking data)
                    if (!empty($subject->neodb_id)) {
                        $available_sources[] = ['name' => 'NeoDB', 'id' => $subject->neodb_id, 'param' => 'neodb'];
                    }
                }
                
                // For edit_fave mode, show if there's any source; for edit_subject, show if multiple sources
                $show_refresh = $action == 'edit_fave' ? count($available_sources) >= 1 : count($available_sources) > 1;
                ?>
                <?php if ($show_refresh): ?>
                <tr valign="top">
                    <th scope="row"><label>数据来源</label></th>
                    <td>
                        <?php if ($action == 'edit_subject'): ?>
                            <p>当前条目关联了多个数据源，点击按钮可预览该来源的数据：</p>
                        <?php else: ?>
                            <p>可以从以下数据源同步您的标记信息（观看时间、状态、短评、评分）：</p>
                        <?php endif; ?>
                        <div class="refresh-row">
                            <?php foreach ($available_sources as $source): ?>
                                <button type="button" class="button source-refresh-btn" 
                                        data-source="<?php echo $source['param']; ?>">
                                    从 <?php echo $source['name']; ?> 刷新 (ID: <?php echo $source['id']; ?>)
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="description">
                            <?php if ($action == 'edit_subject'): ?>
                                💡 预览数据不会立即保存，您可以对比后再保存
                            <?php else: ?>
                                💡 刷新后的数据会自动填入下方表单，您可以修改后再保存
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
                <div id="wpn-snackbar" class="refresh-status"></div>
                <?php endif; ?>
                <?php if ($action == 'edit_subject') : ?>
                    <tr valign="top">
                        <th scope="row"><label for="url">海报地址</label></th>
                        <td>
                            <input type="text" name="poster" value="<?php echo $subject->poster ?>" class="regular-text">
                            <button type="button" class="button revert-btn" data-field="poster" style="display:none;" title="恢复原值"><span class="dashicons dashicons-undo"></span></button>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="url">名称</label></th>
                        <td>
                            <input type="text" name="name" value="<?php echo $subject->name ?>" class="regular-text">
                            <button type="button" class="button revert-btn" data-field="name" style="display:none;" title="恢复原值"><span class="dashicons dashicons-undo"></span></button>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="url">副标题</label></th>
                        <td>
                            <textarea name="card_subtitle" style="width: 600px;" rows="5" cols="30"><?php echo $subject->card_subtitle ?></textarea>
                            <button type="button" class="button revert-btn" data-field="card_subtitle" style="display:none;" title="恢复原值"><span class="dashicons dashicons-undo"></span></button>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="url">评分</label></th>
                        <td>
                            <input type="text" name="douban_score" value="<?php echo $subject->douban_score ?>" class="regular-text">
                            <button type="button" class="button revert-btn" data-field="douban_score" style="display:none;" title="恢复原值"><span class="dashicons dashicons-undo"></span></button>
                        </td>
                    </tr>
                <?php else : ?>
                    <tr valign="top">
                        <th scope="row"><label for="url">观看时间</label></th>
                        <td>
                            <input type="datetime-local" name="create_time" step="1" value="<?php echo get_date_from_gmt($fave->create_time, 'Y-m-d\TH:i:s'); ?>" class="regular-text"></input>
                            <button type="button" class="button revert-btn" data-field="create_time" style="display:none;" title="恢复原值"><span class="dashicons dashicons-undo"></span></button>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="status">状态</label></th>
                        <td>
                            <select name="status" id="status">
                                <option <?php if ($fave->status == 'done') echo 'selected="selected" '; ?>value="done">已看</option>
                                <option <?php if ($fave->status == 'doing') echo 'selected="doing" '; ?>value="doing">在看</option>
                                <option <?php if ($fave->status == 'mark') echo 'selected="mark" '; ?>value="mark">想看</option>
                                <option <?php if ($fave->status == 'dropped') echo 'selected="dropped" '; ?>value="dropped">不看了</option>
                                <option <?php if ($fave->status == '') echo 'selected="selected" '; ?>value="">取消标记</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="url">短评</label></th>
                        <td>
                            <textarea name="remark" style="width: 600px;" rows="5" cols="30" placeholder="输入短评"><?php echo $fave->remark ?></textarea>
                            <button type="button" class="button revert-btn" data-field="remark" style="display:none;" title="恢复原值"><span class="dashicons dashicons-undo"></span></button>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="url">评分</label></th>
                        <td>
                            <input name="score" type="number" value="<?php echo $fave->score ? $fave->score : '' ?>" min="0" max="10"></input>
                            <button type="button" class="button revert-btn" data-field="score" style="display:none;" title="恢复原值"><span class="dashicons dashicons-undo"></span></button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" name="wpn_action" value="<?php echo $action; ?>" />
        <input type="hidden" name="subject_id" value="<?php echo $subject->id ?>" />
        <input type="hidden" name="fave_id" value="<?php echo $fave->id ?>" />
        <input type="hidden" name="subject_type" value="<?php echo $fave->type ?>" />
        <div class="nm-submit-form">
            <input type="submit" class="button-primary" name="save" value="<?php _e('保存') ?>" />
        </div>
    </form>
</div>