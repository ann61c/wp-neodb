<div class="wrap">
    <h2>插件设置</h2>
    <p>请查看 <a href="https://fatesinger.com/101050" target="_blank">帮助文章</a> 了解更多使用详情。</p>
    <form method="post" action="options.php">
        <?php
        settings_fields('db_setting_group');
        ?>
        <h3>账号设置</h3>
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('neodb_url'); ?>">NeoDB 实例地址</label></th>
                    <td>
                        <input name="<?php echo db_setting_key('neodb_url'); ?>" type="text" value="<?php echo db_get_setting('neodb_url') ?: 'https://neodb.social'; ?>" class="regular-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('neodb_token'); ?>">NeoDB Token (可选)</label></th>
                    <td>
                        <input name="<?php echo db_setting_key('neodb_token'); ?>" type="text" value="<?php echo db_get_setting('neodb_token'); ?>" class="regular-text" />
                        <p class="description">可在 <a href="https://neodb.social/developer/" target="_blank">https://neodb.social/developer/</a> 点击 <code>Test Access Token</code> 后，点击 <code>Generate</code> 获取。</p>
                        <p class="description">需要 NeoDB Token 才可从 NeoDB 获取你的书影音游戏等数据。文章页中显示来自 NeoDB 的数据不需要 Token。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('api_key'); ?>">TMDB API Key</label></th>
                    <td>
                        <input name="<?php echo db_setting_key('api_key'); ?>" type="text" value="<?php echo db_get_setting('api_key'); ?>" class="regular-text" />
                        <p class="description">可在 <a href="https://www.themoviedb.org/settings/api" target="_blank">https://www.themoviedb.org/settings/api</a> 自行申请</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('id'); ?>">豆瓣 ID</label></th>
                    <td>
                        <input name="<?php echo db_setting_key('id'); ?>" type="text" value="<?php echo db_get_setting('id'); ?>" class="regular-text" />
                        <p class="description">点击你的个人主页，URL 类似为<code>https://www.douban.com/people/54529369/</code>，<code>54529369</code>就是你的 ID</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3>显示设置</h3>
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('perpage'); ?>">每页显示条目数</label></th>
                    <td>
                        <input name="<?php echo db_setting_key('perpage'); ?>" type="text" value="<?php echo db_get_setting('perpage') ?: '70'; ?>" class="regular-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="<?php echo db_setting_key('dark_mode');
                            $mode = db_get_setting("dark_mode") ?: 'light'; ?>">暗黑模式</label>
                    </th>
                    <td>
                        <label for="mode-light">
                            <input type="radio" name="<?php echo db_setting_key('dark_mode'); ?>" id="mode-light" value="light" <?php if ($mode == 'light') {
                                echo 'checked="checked"';
                            } ?>>浅色模式</label>
                        <label for="mode-dark">
                            <input type="radio" name="<?php echo db_setting_key('dark_mode'); ?>" id="mode-dark" value="dark" <?php if ($mode == 'dark') {
                                echo 'checked="checked"';
                            } ?>>深色模式</label>
                        <label for="mode-auto">
                            <input type="radio" name="<?php echo db_setting_key('dark_mode'); ?>" id="mode-auto" value="auto" <?php if ($mode == 'auto') {
                                echo 'checked="checked"';
                            } ?>>跟随系统</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('show_remark'); ?>">展示短评</label></th>
                    <td>
                        <label for="<?php echo db_setting_key('show_remark'); ?>">
                            <input type="checkbox" name="<?php echo db_setting_key('show_remark'); ?>" id="show_remark" value="1" <?php if (db_get_setting("show_remark")) {
                                echo 'checked="checked"';
                            } ?>>
                        </label>
                        <p class="description">开启后文章引入单条目时如果标记过则展示短评和标记时间</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('show_type'); ?>">开启分类</label></th>
                    <td>
                        <label for="<?php echo db_setting_key('show_type'); ?>">
                            <input type="checkbox" name="<?php echo db_setting_key('show_type'); ?>" id="show_remark" value="1" <?php if (db_get_setting("show_type")) {
                                echo 'checked="checked"';
                            } ?>>
                        </label>
                        <p class="description">默认只展示看过的条目，开启后会展示想看/在看/看过</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('home_render'); ?>">首页渲染</label></th>
                    <td>
                        <label for="<?php echo db_setting_key('home_render'); ?>">
                            <input type="checkbox" name="<?php echo db_setting_key('home_render'); ?>" id="show_remark" value="1" <?php if (db_get_setting("home_render")) {
                                echo 'checked="checked"';
                            } ?>>
                        </label>
                        <p class="description">默认只会在文章页自动渲染条目链接，开启后在非文章页也会渲染。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="url">自定义CSS</label></th>
                    <td>
                        <textarea name="<?php echo db_setting_key('css'); ?>" class="wpn-textarea"><?php echo db_get_setting('css'); ?></textarea>
                        <p class="description">请输入合法的CSS。</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3>高级设置</h3>
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('download_image'); ?>">下载图片</label></th>
                    <td>
                        <label for="<?php echo db_setting_key('download_image'); ?>">
                            <input type="checkbox" name="<?php echo db_setting_key('download_image'); ?>" id="download_image" value="1" <?php if (db_get_setting("download_image")) {
                                echo 'checked="checked"';
                            } ?>>
                        </label>
                        <p class="description">开启后将封面图片下载到本地。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('disable_scripts'); ?>">静态文件</label></th>
                    <td>
                        <label for="<?php echo db_setting_key('disable_scripts'); ?>">
                            <input type="checkbox" name="<?php echo db_setting_key('disable_scripts'); ?>" id="disable_scripts" value="1" <?php if (db_get_setting("disable_scripts")) {
                                echo 'checked="checked"';
                            } ?>>
                        </label>
                        <p class="description">开启后将不加载插件自带的静态文件。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('top250'); ?>">豆瓣电影Top250</label></th>
                    <td>
                        <label for="<?php echo db_setting_key('top250'); ?>">
                            <input type="checkbox" name="<?php echo db_setting_key('top250'); ?>" id="top250" value="1" <?php if (db_get_setting("top250")) {
                                echo 'checked="checked"';
                            } ?>>
                        </label>
                        <p class="description">开启该选项则会定期同步豆瓣电影<code>top250</code> 清单，当条目在清单中时展示<code>top250</code> 标识。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="<?php echo db_setting_key('book_top250'); ?>">豆瓣图书Top250</label></th>
                    <td>
                        <label for="<?php echo db_setting_key('top250'); ?>">
                            <input type="checkbox" name="<?php echo db_setting_key('book_top250'); ?>" id="top250" value="1" <?php if (db_get_setting("book_top250")) {
                                echo 'checked="checked"';
                            } ?>>
                        </label>
                        <p class="description">开启该选项则会定期同步豆瓣图书<code>top250</code> 清单，当条目在清单中时展示<code>top250</code> 标识。</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="wpn-submit-form">
            <input type="submit" class="button-primary" name="save" value="<?php _e('保存') ?>" />
        </div>
    </form>
    <style>
    .wpn-textarea {
        width: 600px;
        height: 120px;
    }
    </style>
</div>