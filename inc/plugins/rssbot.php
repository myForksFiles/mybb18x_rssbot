<?php
if (!defined('IN_MYBB')) header('Location: /');
/**
 * Task for plugin RSS Bot / beta only for test env
 *
 * PHP version 5.5+
 *
 * @category   MyBB Task for plugin
 * @package    plugin RSS Bot for MyBB 1.8+
 * @author     KlubZAFIRA.pl
 * @copyright  2016 KlubZAFIRA.pl
 * @license    Creative Commons BY-NC-SA 4.0 license
 * @version    0.14beta
 * @link       https://github.com/myForksFiles/mybb18x_rssbot
 *
 * @changeLog
 * 2016-08-06 init
 * 2016-08-14 doc/optimisation/fixes
 *- -***
 */

/**
 * set up hooks
 */
if (defined('IN_ADMINCP')) {

    $plugins->add_hook('admin_config_action_handler', ['rssBot', 'adminAction']);
    $plugins->add_hook('admin_config_menu', ['rssBot', 'adminMenu']);
    $plugins->add_hook('admin_load', ['rssBot', 'adminLoad']);
    //$plugins->add_hook('admin_config_settings_change', ['rssBot', 'admin_config_settings_change']);

    function rssbot_info()
    {
        return rssbot::info();
    }

    function rssbot_is_installed()
    {
        return rssBot::isInstalled();
    }

    function rssbot_install()
    {
        rssBot::installBot();
    }

    function rssbot_uninstall()
    {
        rssBot::uninstallBot();
    }

} else {
    $plugins->add_hook('pre_output_page', ['rssBot', 'pluginThanks']);
}

class rssBot
{

    /**
     * @var constat with filename
     */
    const RSSBOTFILE = 'rssbot';

    /**
     * call simple acp redirect
     */
    public function redirect($options = null)
    {
        $url = self::getLink($options);
        admin_redirect($url);
    }

    /**
     * get link in ACP
     * @param strin with _GET vars for url
     */
    public function getLink($options = null)
    {
        $url = 'index.php?module=config-rssbot';
        if ($options != null) {
            $url .= '&' . $options;
        }
        return $url;
    }

    /**
     * donationForm in acp
     * @todo
     */
    public function donationForm()
    {
        $table = new Table;
    }

    /**
     * tabs for acp
     */
    public function pageTabs()
    {
        $lang = self::loadLang();
        return [
            'rssbot' => [
                'title' => $lang['list'],
                'link' => self::getLink(),
                'description' => $lang['list_description']
            ],
            'add' => [
                'title' => $lang['add'],
                'link' => self::getLink() . '&action=add',
                'description' => $lang['add_description']
            ],
            'edit' => [
                'title' => $lang['edit'],
                'link' => self::getLink() . '&action=edit',
                'description' => $lang['edit_description']
            ],
            'settings' => [
                'title' => $lang['settings'],
                'link' => self::getLink() . '&action=settings',
                'description' => $lang['settings_description']
            ],
        ];
    }

    /**
     * acp action
     */
    public function adminAction(&$actions)
    {
        $actions['rssbot'] = ['active' => 'rssbot'];
        return $actions;
    }

    /**
     * Say thanks to plugin author - paste link to author website.
     * Please don't remove this code if you didn't make donate
     * It's the only way to say thanks without donate :)
     */
    static function pluginThanks(&$content)
    {
        global $session, $rssBotThx4plugin;

        if (!isset($rssBotThx4plugin) && $session->is_spider) {
            $thx = '<div style="margin:auto; text-align:center;">';
            $thx .= 'This forum uses <a href="{url}">{url}</a> ';
            $thx .= 'MyBB {plugin} addons.</div>';
            $thx .= '</body>';
            $info = self::info();
            $thx = str_replace('{url}', $info['authorsite'], $thx);
            $thx = str_replace('{plugin}', $info['name'], $thx);
            $content = str_replace('</body>', $thx, $content);
            $rssBotThx4plugin = true;
        }
    }

    /*
     * inject element to menu via ref
     */
    static function adminMenu(&$menu)
    {
        $lang = self::loadLang();
        $menu[] = [
            'id' => 'rssbot',
            'title' => $lang['rssBotTitle'],
            'link' => self::getLink()
        ];
    }

    /**
     * load lang
     */
    public function loadLang()
    {
        global $lang;
        $rssBotLang = $lang->load('rssbot');
        $rssBotLang = $lang->rssbot;
        return $rssBotLang;
    }

    /**
     * plugin info for acp
     */
    static function info()
    {
        return [
            'name' => 'RSS Bot Plugin',
            'description' => 'Fetch RSS feeds and post as Bot',
            'website' => 'https://github.com/myForksFiles/mybb18x_rssbot',
            'author' => 'KlubZAFIRA.pl',
            'authorsite' => 'http://KlubZAFIRA.pl',
            'version' => '0.1b',
            'codename' => 'rssBot',
            'compatibility' => '18*'
        ];
    }

    /**
     * register task in acp
     * @return bool
     */
    static function installTask()
    {
        global $db;
        $lang = self::loadLang();
        $task = [
            'title' => $lang['rssBotTitle'],
            'description' => $lang['rssBotTitle_task'],
            'file' => self::RSSBOTFILE,
            'minute' => '5,10,15,20,25,30,35,40,45,50,55',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'weekday' => '*',
            'nextrun' => TIME_NOW,
            'lastrun' => 0,
            'enabled' => 1,
            'logging' => 1,
            'locked' => 0
        ];
        if ($db->insert_query('tasks', $task)) {
            return true;
        }
        return false;
    }

    /**
     * check if taks is registererd
     * @return bool
     */
    static function checkTask()
    {
        global $db;
        $query = 'SELECT COUNT(tid) AS cnt FROM '
            . TABLE_PREFIX . 'tasks WHERE file = "' . self::RSSBOTFILE . '"';
        $query = $db->query($query);
        $cnt = $db->fetch_array($query);
        $cnt = (int)$cnt['cnt'];
        if ($cnt < 1) {
            if (self::installTask()) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * boot example entry
     * @return array
     */
    static function installBotExample()
    {
        return [
            'id' => '',
            'pid' => 1,
            'tid' => 1,
            'fid' => 1,
            'enabled' => 0,
            'html' => 1,
            'locked' => 1,
            'asread' => 1,
            'link' => 1,
            'toimport' => 5,
            'intervals' => 360,
            'updated' => date('Y-m-d H:i:s'),
            'posterid' => 'rssBot',
            'poster' => 'rssBot',
            'title' => 'KlubZAFIRA.pl',
            'prefix' => '{rssBot}',
            'url' => 'http://feeds.feedburner.com/FanKlubOpelZafiraPolska'
        ];
    }

    /**
     * sql queries with tables for plugin
     * @return array
     */
    static function installBotTables($collation)
    {
        return [
            'CREATE TABLE IF NOT EXISTS `' . TABLE_PREFIX . 'rssbot` (
`id` int(10) NOT NULL,
  `pid` int(10) unsigned NOT NULL DEFAULT "0",
  `tid` int(10) unsigned NOT NULL DEFAULT "0",
  `fid` int(10) unsigned NOT NULL DEFAULT "0",
  `enabled` tinyint(1) NOT NULL DEFAULT "1",
  `html` tinyint(1) NOT NULL DEFAULT "1",
  `locked` tinyint(1) NOT NULL DEFAULT "0",
  `asread` tinyint(1) NOT NULL DEFAULT "0",
  `link` tinyint(1) NOT NULL DEFAULT "0",
  `toimport` tinyint(1) NOT NULL DEFAULT "1",
  `intervals` smallint(4) NOT NULL DEFAULT "360",
  `updated` datetime NOT NULL,
  `posterid` int(10) unsigned DEFAULT NULL,
  `poster` tinytext ' . $collation . ',
  `title` tinytext ' . $collation . ' NOT NULL,
  `prefix` tinytext ' . $collation . ',
  `url` tinytext ' . $collation . ' NOT NULL
) ENGINE=MyISAM ' . $collation . ';',
            'ALTER TABLE `' . TABLE_PREFIX . 'rssbot` ADD PRIMARY KEY (`id`);',
            'ALTER TABLE `' . TABLE_PREFIX . 'rssbot` ADD KEY `id` (`id`);',
            'ALTER TABLE `' . TABLE_PREFIX . 'rssbot` MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;',
            'CREATE TABLE IF NOT EXISTS `' . TABLE_PREFIX . 'rssbot_log` (
`id` int(10) NOT NULL,
  `pid` int(10) unsigned NOT NULL DEFAULT "0",
  `tid` int(10) unsigned NOT NULL DEFAULT "0",
  `feedid` int(10) NOT NULL,
  `feedhash` tinytext ' . $collation . ' NOT NULL,
  `feedtime` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM ' . $collation . ';',
            'ALTER TABLE `' . TABLE_PREFIX . 'rssbot_log` ADD PRIMARY KEY (`id`);',
            'ALTER TABLE `' . TABLE_PREFIX . 'rssbot_log` ADD KEY `id` (`id`);',
            'ALTER TABLE `' . TABLE_PREFIX . 'rssbot_log` MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;'
        ];
    }

    /**
     * install bot
     * @todo other DBs
     */
    static function installBot()
    {
        global $db;
        switch ($db->type) {
            case 'pgsql':
                break;
            case 'sqlite':
                break;
            case 'mysql':
            default:
                $collation = $db->build_create_table_collation();
                $queries = self::installBotTables($collation);
                foreach ($queries as $v) {
                    //$db->write_query($v);
                    $db->query($v);
                }
                $db->insert_query('rssbot', self::installBotExample());
                self::checkTask();
                break;
        }
    }

    /**
     * check if plugin installed
     * @return bool
     */
    static function isInstalled()
    {
        global $db;
        return $db->table_exists('rssbot');
    }

    /**
     * unregister plugin and drop tables
     */
    static function uninstallBot()
    {
        if (!isInstalled()) {
            global $db;
            $db->drop_table('rssbot');// Drop the Table
            $db->drop_table('rssbot_log');// Drop the Table
            $db->query('DELETE FROM '
                . TABLE_PREFIX . 'tasks WHERE file = "'
                . self::RSSBOTFILE . '";'); // Delete the task just in case
        }
    }

    /**
     * load forum list
     * @return array
     */
    public function getForumsList()
    {
        $this->forumList = [];
        $query = 'SELECT fid, name FROM '
            . TABLE_PREFIX . 'forums WHERE type = "f"';
        $query = $this->$db->query($query);
        $this->forumList[0] = '';
        while ($row = $this->$db->fetch_array($query)) {
            $this->forumList[$row['fid']] = $row['name'];
        }
    }

    /**
     * load acp page
     */
    public function adminLoad()
    {
        global $page;
        if ($page->active_action != 'rssbot') return false;

        global $mybb, $db;
        $lang = self::loadLang();

        $page->add_breadcrumb_item($lang['rssBotTitle']);
        $page->output_header($lang['rssBotTitle']);

        self::pageActions();

        $page->output_footer();
    }

    /**
     * load forums list
     */
    public function dbForumList()
    {
        global $db;
        $query = 'SELECT fid,'
            . ' CONCAT(name, " - ", SUBSTRING(description, 1, 30), "...") AS name'
            . ' FROM ' . TABLE_PREFIX . 'forums '
            . ' WHERE type = "f" ORDER BY fid ASC'; //@todo
        $query = $db->query($query);
        $res = [];
        $res[0] = '';
        while ($row = $db->fetch_array($query)) {
            $res[$row['fid']] = strip_tags($row['name']);
        }
        return $res;
    }

    /**
     * load all bot task list
     * @return array
     */
    public function dbList()
    {
        global $db;
        $query = 'SELECT id, title, enabled, SUBSTRING(url, 1, 50) AS url'
            . ' FROM ' . TABLE_PREFIX . 'rssbot ORDER BY id DESC';
        $query = $db->query($query);
        $res = [];
        while ($row = $db->fetch_array($query)) {
            $res[] = $row;
        }
        return $res;
    }

    /**
     * load selected bot task
     * @return array
     */
    public function dbRow()
    {
        global $db;
        $id = self::getId();
        if ($id > 0) {
            $query = 'SELECT * FROM ' . TABLE_PREFIX . 'rssbot WHERE id = ' . $id;
            $query = $db->query($query);
            $row = $db->fetch_array($query);
        }
        return $row;
    }

    /**
     * acp page actions
     */
    public function pageActions()
    {
        global $mybb;
        $action = $mybb->input['action'];
        switch ($action) {
            case 'settings':
                self::botSettings();
                break;
            case 'delete':
                self::removeEntry();
                break;
            case 'edit':
                if (isset($_POST['save'])) self::saveData();
                self::pageEdit();
                break;
            case 'add':
                if (isset($_POST['save'])) self::saveData();
                self::pageAdd();
                break;
            case 'list':
            case '':
            default:
                self::pageList();
                break;
        }
    }

    /**
     * acp page with task list
     */
    public function pageList()
    {
        global $page;
        $page->output_nav_tabs(self::pageTabs(), 'rssbot');
        $lang = self::loadLang();

        $table = new Table;
        $table->construct_header($lang['id']);
        $table->construct_header($lang['enabled']);
        $table->construct_header($lang['name']);
        $table->construct_header($lang['url']);
        $table->construct_header($lang['options']);

        $rows = [];
        $rows = self::dbList();
        if (count($rows) > 0) {
            foreach ($rows as $v) {
                $table->construct_cell($v['id']);
                $table->construct_cell(
                    $v['enabled'] ? $lang['enabled'] : $lang['disabled']);
                $table->construct_cell($v['title']);
                $table->construct_cell(strlen($v['url']) == 50 ? $v['url'] . '...' : $v['url']);
                $url = '';
                $url2 = '';
                $url = '<a href="' . self::getLink('action=edit&id=' . $v['id']) . '">';
                $url2 = str_replace('edit', 'delete', $url);
                $url2 .= $lang['delete'] . '</a>';
                $url .= $lang['edit'] . '</a>';
                $table->construct_cell($url . ' | ' . $url2);
                $table->construct_row();
            }
        } else {
            $table->construct_cell('', ['colspan' => 2]);
            $table->construct_cell($lang['empty']);
            $table->construct_cell('', ['colspan' => 2]);

            $table->construct_row();
        }
        $table->output($lang['rssBotTitle'] . ' - ' . $lang['list']);
    }

    /**
     * prepare array with data
     * @todo _POST
     * @return array
     */
    public function pageData($data = [])
    {
        $data = [
            'id' => isset($data['id']) ? $data['id'] : 0,
            'input' => [
                'title' => isset($data['title']) ? $data['title'] : '',
                'url' => isset($data['url']) ? $data['url'] : '',
                'poster' => isset($data['poster']) ? $data['poster'] : 'RSSBot',
                'toimport' => isset($data['toimport']) ? (int)$data['toimport'] : '',
                'intervals' => isset($data['intervals']) ? $data['intervals'] : 720,
                'prefix' => isset($data['prefix']) ? $data['prefix'] : '',
                'tid' => isset($data['tid']) ? (int)$data['tid'] : 0,
            ],
            'checkbox' => [
                'enabled' => $data['enabled'] > 0 ? 1 : 0,
                'html' => $data['html'] > 0 ? 1 : 0,
                'locked' => $data['locked'] > 0 ? 1 : 0,
                'asread' => $data['asread'] > 0 ? 1 : 0,
            ],
            'fid' => isset($data['fid']) ? (int)$data['fid'] : 0,
            'updated' => isset($data['updated']) ? $data['updated'] : '',
        ];

        return $data;
    }

    /**
     * load acp page add task
     */
    public function pageAdd()
    {
        $data = self::pageData([]);
        self::pageForm('add', $data);
    }

    /**
     * load acp page edit
     */
    public function pageEdit()
    {
        $row = [];
        $row = self::dbRow();
        $data = self::pageData($row);
        self::pageForm('edit', $data);
    }

    /**
     * diable form for empty data
     */
    public function pageFormDisable()
    {
        return '
<script>
var form = document.getElementById("rssEdit");
var elements = form.elements;
for (var i = 0, len = elements.length; i < len; ++i){
    elements[i].readOnly = true;
    elements[i].disabled = true;
    elements[i].style = "background-color: #eee;";
}
</script>';
    }

    /**
     * acp page form buttons
     * @return string with html
     */
    public function pageFormButtons($actions, $id, $action, $text, $code)
    {
        return '<input type="hidden" name="id" value="' . $id . '" />
             <input type="hidden" name="action" value="' . $actions . '" />
             <input type="hidden" name="save" value="1" />
             <input type="hidden" name="my_post_key" value="' . $code . '" />
             <input type="submit" value="' . $text . '" />';
    }

    /**
     * prepare and build html form for acp page
     */
    public function pageForm($action, $data = [])
    {
        global $page, $mybb;
        $page->output_nav_tabs(self::pageTabs(), $action);
        $lang = self::loadLang();
        $url = self::getLink('action=' . $action);
        $form = new Form($url, 'post', 'rssEdit');
        $table = new Table;
        $forumList = $form->generate_select_box('fid', self::dbForumList(), [$data['fid']]);

        foreach ($data['input'] as $k => $v) {
            $table->construct_cell($lang[$k]);
            $i = $form->generate_text_box($k, $v, ['size' => 70]);
            $i .= $lang[$k . '_note'];
            $table->construct_cell($i);
            $table->construct_row();
        }

        $table->construct_cell($lang['fid']);
        $table->construct_cell($forumList);
        $table->construct_row();

        foreach ($data['checkbox'] as $k => $v) {
            $table->construct_cell($lang[$k]);
            $check = [];
            if ($v > 0) $check = ['checked' => true];
            $i = $form->generate_check_box($k, 1, $lang[$k . '_note'], $check);
            $table->construct_cell($i);
            $table->construct_row();
        }

        $table->construct_cell(
            self::pageFormButtons(
                $action,
                $data['id'],
                $action,
                $lang['save'],
                $mybb->post_code
            ),
            ['colspan' => 2, 'align' => 'right']);

        $table->construct_row();
        $table->output($lang['rssBotTitle'] . ' - ' . $lang[$action]);
        $form->end;
        if (isset($data['id']) && $data['id'] < 1 && $action == 'edit') {
            echo self::pageFormDisable();
        }
    }

    /**
     * @return array
     */
    public function botSettingsFetchType()
    {
        return [
            'auto',
            'curl',
            'fsock',
            'file',
            'fileget',
        ];
    }

    /**
     * plugin settings form
     * @todo
     */
    public function botSettingsForm()
    {
        $form = new Form($url, 'post', 'rssBotSettings');
        $txt = '<b>RSS Bot</b> not available yet';
        $txt .= '<br /><br />';
        $txt .= 'rss fetch type: ';
        $txt .= $form->generate_select_box('fetchType', self::botSettingsFetchType(), [0]);
        $txt .= '<br />';
        $check = ['checked' => false];
        $txt .= $form->generate_check_box('dir/cache/rss', 1, $lang['_note'] . 'dir /cache/rss', $check);
        $txt .= '<br />';
        $txt .= $form->generate_check_box('wordfilter', 1, $lang['_note'] . 'word filter', $check);
        return $txt;
    }

    /**
     * load acp plugin settings page
     * @todo
     */
    public function botSettings()
    {
        global $page;
        $page->output_nav_tabs(self::pageTabs(), 'settings');
        $lang = self::loadLang();

        $url = self::getLink('action=' . 'settings');
        $txt = '';
        $txt .= self::botSettingsForm();
        $txt .= '<br /><br /><hr />';
        $text = self::info();
        foreach ($text as $k => $v) {
            if (stristr($v, 'http')) {
                $v = '<a href="' . $v . '">' . $v . '</a>';
            }
            $txt .= '<br />' . $lang[$k] . ': ' . $v;
        }
        $txt .= '<br /><br />';
        $table = new Table;
        $table->construct_header($lang['settings']);
        $table->construct_cell('');
        $table->construct_row();
        $table->construct_cell($txt);
        $table->construct_row();
        $table->construct_cell('');
        $table->construct_row();
        $table->output($lang['rssBotTitle'] . ' - ' . $lang[$action]);
    }

    /**
     * save data from form
     */
    public function saveData()
    {
        $data = self::checkData($_POST);
        switch ($_POST['action']) {
            case 'add':
                self::saveInsert($data);
                break;
            case 'edit':
                self::saveUpdate($data);
                break;
        }
    }

    /**
     * check data
     * @todo
     */
    public function checkData($data)
    {
        $data = [
            'id' => $data['id'],
            'pid' => $data['pid'],
            'tid' => $data['tid'],
            'fid' => $data['fid'],
            'enabled' => (int)$data['enabled'],
            'html' => (int)$data['html'],
            'locked' => (int)$data['locked'],
            'asread' => (int)$data['asread'],
            'link' => $data['link'],
            'toimport' => $data['toimport'],
            'intervals' => $data['intervals'],
            'updated' => date('Y-m-d H:i:s'),
            'posterid' => $data['posterid'],
            'poster' => $data['poster'],
            'title' => $data['title'],
            'prefix' => $data['prefix'],
            'url' => $data['url'],
        ];
        return $data;
    }

    /**
     * @return current id(task) from _GET
     */
    public function getId()
    {
        return (int)filter_var($_REQUEST['id'], FILTER_VALIDATE_INT);
    }

    /**
     * save/update bot task
     */
    public function saveUpdate($data)
    {
        global $db;
        $res = $db->update_query('rssbot', $data, 'id = ' . $data['id'], 1);
        if ($res) {
            self::redirect();
        } else {
            self::redirect('action=edit&id=' . $res);
        }
    }

    /**
     * save/new bot task
     */
    public function saveInsert($data)
    {
        global $db;
        $res = $db->insert_query('rssbot', $data);
        if ($res > 0) {
            self::redirect('action=edit&id=' . $res);
        } else {
            self::redirect('action=add');
        }
    }

    /**
     * remove bot task
     */
    public function removeEntry()
    {
        $id = self::getId();
        if ($id > 0) {
            global $db;
            $res = $db->delete_query('rssbot', 'id = ' . $id, 1);
            if ($res) {
                self::redirect();
            }
        } else {
            echo 'error removing entry';
        }
    }

}
