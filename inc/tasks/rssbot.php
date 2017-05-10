<?php
if(!defined('IN_MYBB')) die('This file cannot be accessed directly.');
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

task_rssbot($task);

/**
 * function for call
 */
function task_rssbot($lang)
{
    global $db, $task;
    new rssBotTask($lang, $db, $task);
}

class rssBotTask
{
    /**
     * @var array with errors
     */
    public $er;

    /**
     * @var DB
     */
    public $db;

    /**
     * @var current rss entry
     */
    public $rss;

    /**
     * @var task
     */
    public $task;

    /**
     * @var langs
     */
    public $lang;

    /**
     * @array with temporary feed files list 
     */
    public $tmpFiles   = [];

    /**
     * @array with entries data
     */
    public $d          = [
        'feeds'         => [],
        'items'         => [],
        'itemsHashes'   => [],
        'items2log'     => [],
        'items2save'    => [],
        'feedhashCheck' => []
    ];

    /**
     * @var current time/ts
     */
    public $t          = 0; //time

    /**
     * @var feed
     */
    public $f          = [];//feed

    /**
     * @var flag for dev
     */
    public $dev        = true;

    /**
     * @var current parse tag 
     */
    public $tag        = '';

    /**
     * @var sys temporary dir
     */
    public $tmpDir     = '';

    /**
     * @var current parse tag atributes
     */
    public $tagAttrs   = '';

    /**
     * @var feeds counter
     */
    public $feedCount  = 0; //feedCounter

    /**
     * @var feed items
     */
    public $feedItem   = []; //feedCounter

    /**
     * @var items
     */
    public $itemInside = false;

    /**
     * @var current encoding default
     */
    public $encoding   = 'UTF-8'; //@todo

    /**
     * @var minimal feed content lenght :-)
     */
    public $dieAntwort = 42;

    /**
     * Create a new instance.
     */
    public function __construct(&$lang, &$db, &$task)
    {
        $this->lang = $lang;
        $this->db = $db;
        $this->task = $task;
        $this->t = time();
        $this->tmpDir = sys_get_temp_dir();
        $this->getEncoding();
        $this->updateFeeds();
    }

    /**
     * get current encoding
     * @see encoding
     */
    private function getEncoding()
    {
        if(!empty($this->lang->settings['charset'])){
            $this->encoding = $this->lang->settings['charset'];
        }
    }

    /**
     * update feeds
     */
    public function updateFeeds()
    {
        $taskLog = '';
        $this->dbReadFeeds();
        if($this->d['feedsCnt']>0){
            $this->feedsCheck();
            if(count($this->tmpFiles)>0){
                foreach($this->tmpFiles as $k => $v){
                    $this->feedParse($v, $k);
                }
                $this->checkItems();
                $taskLog = $this->lang->rssbot['updated'] 
                    . $this->lang->rssbot['feeds'] . ': '
                    . count($this->d['feeds'])
                    . ', '
                    . $this->lang->rssbot['added'] . ': '
                    . ' '
                    . count($this->d['items'])
                    . $this->lang->rssbot['posts'];
            } else {
                $taskLog = $this->lang->rssbot['noRssToCheck'];
            }
        } else {
            $taskLog = $this->lang->rssbot['nothingToUpdate'];
        }
        $this->addTaskLog($taskLog);
    }

    /**
     * query for feed list from db for update
     * @return string with query
     */
    public function dbReadFeedsQuery()
    {
        return 'SELECT f.id,
                    f.pid,
                    f.tid,
                    f.fid,
                    f.html,
                    f.locked,
                    f.asread,
                    f.link,
                    f.toimport,
                    f.intervals,
                    f.posterid,
                    f.poster,
                    f.title,
                    f.prefix,
                    IFNULL(
                        (UNIX_TIMESTAMP(MAX(l.feedtime)) - (f.intervals*60)), 
                        (UNIX_TIMESTAMP(NOW()) - (f.intervals*60))
                    ) AS feedtime,
                    f.url
                FROM `' . TABLE_PREFIX . 'rssbot` AS f
                    LEFT JOIN `' . TABLE_PREFIX . 'rssbot_log` AS l ON (l.feedid = f.id)
                WHERE 1
                    AND f.enabled = 1
                      ORDER BY f.id DESC';
    }
    
    /**
     * load feed list from db for update
     */
    public function dbReadFeeds()
    {
        $query = $this->dbReadFeedsQuery();
        $res = $this->db->query($query);
        $this->d['feedsCnt'] = $this->db->num_rows($res);
        $this->d['feeds'] = [];
        while ($row = $this->db->fetch_array($res)){
            if($row['id']>0){
                if($this->t > $row['feedtime']){
                    $hashurl = $this->getHash($row['url']);
                    $this->urlHashId[$hashurl] = $row['id'];
                    $this->d['feeds'][$row['id']] = $row;
                }
            }
        }
  }

    /**
     * generates md5 hash
     * @return string
     */
    public function getHash($url)
    {
        if(! empty($url)){
            $hash = md5($url);
            return $hash;
        }
    }

    /**
     * task log in acp
     */
    public function addTaskLog($msg)
    {
        if(!empty($msg)){
            add_task_log($this->task, $msg);
        }
    }

    /**
     * check feeds
     */
    public function feedsCheck()
    {
        foreach ($this->d['feeds'] as $v){
            $this->rss = '';
            $fileName = '';
            $fileName = $this->tmpDir . '/rssBot_'.$this->t.'_'.$this->getHash($v['url']);
            if ($this->feedGet($v['url'])){
                @file_put_contents($fileName, $this->rss);
                if(file_exists($fileName)){
                    $this->tmpFiles[$v['id']] = $fileName;
                }
            } else {
                $this->addTaskLog($this->lang->rssbot['feedAccessError'] . ': ' . $v['url']);
            }
        }
    }

    /**
     * get feed from url 
     * @todo swith from settings
     */   
    public function feedGet($url)
    {
        $result = false;
        if ($this->feedsByCurl($url)){
            $result = true;
        } else if ($this->feedsByFnfile($url)){
            $result = true;
        }
        return $result;
    }

    /**
     * check feed lenght
     */
    public function feedLen()
    {
        if(strlen($this->rss) > $this->dieAntwort){
            return true;
        }
        return false;
    }

    /**
     * get feed file by cURL
     */
    public function feedsByCurl($url)
    {
        $result = false;
        if (function_exists('curl_init')){
            $cUrl = curl_init();
            curl_setopt($cUrl, CURLOPT_URL, $url);
            curl_setopt($cUrl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($cUrl, CURLOPT_HEADER, false);
            $this->rss = curl_exec($cUrl);
            curl_close($cUrl);
            $result = $this->feedLen();
        }
        return $result;
    }

    /**
     * switch get file by php file functions
     */
    public function feedsByFnfile($url)
    {
        $result = false;
        if ($this->feedsByFopen($url)){
            $result = true;
        } else if ($this->feedsByFsock($url)){
            $result = true;
        } else if ($this->feedsByFile($url)){
            $result = true;
        }
        return $result;
    }

    /**
     * get feed by fopen
     */
    public function feedsByFopen($url)
    {
        $result = false;
        $f = @fopen($url, 'r');
        if ($f){
            $this->rss = '';
            while (!feof($f)){
                $this->rss.= fread($f, 8192);
            }
            fclose($f);
            $result = $this->feedLen();
        }
        return $result;
    }

    /**
     * get feed by fsock
     */
    public function feedsByFsock($url)
    {
        $result = false;
        $url = parse_url($url);
        $type = $this->feedsByFsockScheme($url);
        
        $f = fsockopen($type['scheme'] . $url['host'], $type['port'], $errno, $errstr, 60);
        if ($f){
            $head = $this->feedsByFsockHeader($url);
            $header = '';
            fwrite($f, $head);
            do {
                $header .= fgets($f, 128);
            } while (strpos($header, "\r\n\r\n") === false);
            
            $this->rss = '';
            while (!feof($f)){
                $this->rss.= fgets($f, 128);
            }
            fclose($f);
            $result = $this->feedLen();
        }
        return $result;
    }

    /**
     * header for fsock
     */
    private function feedsByFsockHeader($url)
    {
        $h = 'GET ' . $url['path'];
        $h.= (@$url['query'] != '' ? '?' . $url['query'] : '');
        $h.= '  HTTP/1.1';
        $h.= "\r\n";
        $h.= 'Host: ' . $url['host'];
        $h.= "\r\n";
        $h.= 'Connection: Close';
        $h.= "\r\n\r\n";
        return $h;
    }

    /**
     * scheme settings for fsock
     */
    private function feedsByFsockScheme($url)
    {
        switch ($url['scheme']){
            case 'https':
                $scheme = 'ssl://';
                $port = 443;
                break;
            case 'http':
            default:
                $scheme = '';
                $port = 80;
        }
        return [
            'scheme' => $scheme,
            'port'   => $port
        ];
    }

    /**
     * get feed by file_get_contents
     */
    public function feedsByFile($url)
    {
        $result = false;
        $this->rss = '';
        $this->rss = @file_get_contents($url);
        $result = $this->feedLen();
        return $result;
    }

    /**
     * parse feed
     * @return array
     */
    public function feedParse($feed, $id)
    {
        $results = false;

        $this->feedItem = [];
        $this->feedCount = 0;
        $this->tag = '';
        $this->tagAttrs = '';
        $this->itemInside = false;
        $file = file_get_contents($feed);
        if ($this->feedParseXml($file, $id)){
            $results = true;
        }
        return $results;
    }

    /**
     * xml_parser
     */
    public function feedParseXml($feed, $id)
    {
        $result = false;
        $xmlParser = xml_parser_create($this->encoding);
        xml_set_element_handler($xmlParser, [$this, 'elStart'], [$this, 'elEnd']);
        xml_set_character_data_handler($xmlParser, [$this, 'elData']);

        if (!xml_parse($xmlParser, $feed)){// Error reading xml data
            xml_parser_free($xmlParser);
            $this->addTaskLog($this->lang->rssbot['feedContentError'] . ': ' . $feed);
        } else {
            xml_parser_free($xmlParser);
            if(count($this->feedItem)>0){
                //array_reverse($this->feedItem);
                $_i = 0;
                foreach ($this->feedItem as $k => $v){
                    if($_i < $this->d['feeds'][$id]['toimport']){
                        $itemHash = md5($this->feedItem[$k]['link'] 
                            . $this->feedItem[$k]['updated']);
                        $itemHashQuote = "'" . $itemHash . "'";
                        if(!in_array($itemHashQuote, $this->d['itemsHashes'])){
                            $this->feedItem[$k]['feedId'] = $id;
                            $this->feedItem[$k]['itemHash'] = $itemHash;
                            $this->feedItem[$k]['hash'] 
                                = $this->getHash($this->d['feeds'][$id]['url']);
                            $this->feedItem[$k]['updated'] = date(
                                'Y-m-d H:i', 
                                strtotime($this->feedItem[$k]['updated'])
                            );
                            $this->d['itemsHashes'][] = $itemHashQuote;

                            if($this->d['feeds'][$id]['html'] < 1){
                                $this->feedItem[$k]['updated'] 
                                    = strip_tags($this->feedItem[$k]['updated']);
                            }
                            
                            if($this->d['feeds'][$id]['link'] < 1){
                                $this->feedItem[$k]['link'] = '';
                            }

                            $this->d['items'][] = $this->feedItem[$k];
                        }
                    }
                    $_i++;
                }
                $result = true;
            }
        }
        return $result;
    }

    /**
     * xml_parser elements
     */
    public function elStart($parser, $name, $attrs)
    {
        if ($this->itemInside){
            $this->tag = $name;
            $this->tagAttrs = $attrs;
        } elseif ($name == 'ITEM' || $name == 'ENTRY'){
            $this->itemInside = true;
        }
    }

    /**
     * xml_parser elements
     */
    public function elEnd($parser, $name)
    {
        if ($name == 'ITEM' || $name == 'ENTRY'){
            $this->feedCount++;
            $this->feedItem[$this->feedCount] = [];
            $this->feedItem[$this->feedCount]['feedhash'] = '';
            $this->feedItem[$this->feedCount]['updated'] = '';
            $this->feedItem[$this->feedCount]['title'] = '';
            $this->feedItem[$this->feedCount]['description'] = '';
            $this->feedItem[$this->feedCount]['link'] = '';
            $this->tagAttrs = '';
            $this->itemInside = false;
        }
    }

    /**
     * xml_parser elements
     */
    public function elData($parser, $data)
    {
        if ($this->itemInside && !empty($data)){
            $data = trim($data);
            switch ($this->tag){
                case 'title':
                case 'TITLE':
                    $this->feedItem[$this->feedCount]['title'] .= $data;
                    break;
                case 'UPDATED':
                case 'updated':
                    $this->feedItem[$this->feedCount]['updated'] .= $data;
                    break;
                case 'description':
                case 'DESCRIPTION':
                case 'summary':
                case 'SUMMARY':
                case 'content':
                case 'CONTENT':
                    $this->feedItem[$this->feedCount]['description'] .= $data;
                    break;
                case 'link':
                case 'LINK':
                    $this->feedItem[$this->feedCount]['link'] .= $data;
                        if (isset($this->tagAttrs['HREF'])){
                            $this->feedItem[$this->feedCount]['link']
                                .= $this->tagAttrs['HREF'];
                        }
                        if (isset($this->tagAttrs['href'])){
                            $this->feedItem[$this->feedCount]['link']
                                .= $this->tagAttrs['href'];
                        }
                    break;
            }
        }
    }

    /**
     * xml check items
     */
    public function checkItems()
    {
        if(count($this->d['items'])>0){
            $this->checkItemsHashes();
            foreach ($this->d['items'] as $k => $v){
                if (!in_array($v['itemHash'], $this->d['feedhashCheck'])){
                    $this->populatePostData($v);
                }
            }
            $this->saveItems();
        }
    }

    /**
     * save items
     */
    public function saveItems()
    {
        if(count($this->d['newPosts'])>0){
            $logItem = $this->populatePost();
            if(count($logItem)>0){
                $this->db->insert_query_multiple('rssbot_log', $logItem);
            }
         }
    }

    /**
     * check if elements already in DB
     */
    public function checkItemsHashes()
    {
        if(count($this->d['itemsHashes'])>0){
            $query = 'SELECT feedhash
                    FROM `' . TABLE_PREFIX . 'rssbot_log`
                    WHERE 1
                        AND feedhash IN ('.implode(',', $this->d['itemsHashes']).')';
            $res = $this->db->query($query);
            while ($row = $this->db->fetch_array($res)){
                $this->d['feedHashCheck'][] = $row['feedhash'];
            }
        }
    }

    /**
     * prepare post data to insert
     */
    public function populatePostData($v)
    {
        if (strlen($v['title']) > 70){
            $v['title']  = substr($msg_title, 0, 70);
        }
        $v['title'] = trim($v['title']);
        $v['description'] = trim($v['description']);
            
        $post = [
            'locked'    => (int)$this->d['feeds'][$v['feedId']]['locked'],
            'asRead'    => (int)$this->d['feeds'][$v['feedId']]['asread'],
            'feedID'    => $v['feedId'],
            'itemHash'  => $v['itemHash'],

            'tid'       => (int)$this->d['feeds'][$v['feedId']]['tid'],
            'icon'      => '',
            'uid'       => $this->d['feeds'][$v['feedId']]['posterid'],
            'username'  => $this->d['feeds'][$v['feedId']]['poster'],
            'subject'   => $this->d['feeds'][$v['feedId']]['prefix'] . $v['title'],
            'icon'      => '',
            'message'   => '[b]' 
                . $v['feedId']['title'] . '[/b]' . "\r\n"
                . $v['feedId']['description'] 
                . "\r\n" . $v['updated']
                . "\r\n" . $v['link'],
            'ipaddress' => '127.0.0.1',
            'posthash'  => ''
        ];

        if((int)$this->d['feeds'][$data['id']]['fid']){
            $post['fid'] = $data['fid'];
        }

        $this->d['newPosts'][] = $post;
  }

    /**
     * insert post to DB
     */
    public function populatePost()
    {
        if(count($this->d['newPosts'])>0){
            require_once MYBB_ROOT . 'inc/datahandlers/post.php';
            require_once MYBB_ROOT . 'inc/functions_indicators.php';
            $postHandler = new PostDataHandler('insert');

            $logItem = [];
            foreach($this->d['newPosts'] as $v){
                $feedID = $v['feedID'];
                unset($v['feedID']);

                $itemHash = $v['itemHash'];
                unset($v['itemHash']);

                $asRead = 0;
                if($v['asread']>0){
                    $asRead = 1;
                }
                unset($v['asread']);
                if($v['locked']>0){
                    $v['modoptions'] = ['closethread' => 1];
                }
                unset($v['locked']);
                
                
                if($v['tid']>0){
                    $postHandler->action = 'post';
                    $postHandler->set_data($v);
                    $isValid = $postHandler->validate_post();
                    $info    = $postHandler->insert_post();
                } else {
                    $postHandler->action = 'thread';
                    $postHandler->set_data($v);
                    $isValid = $postHandler->validate_thread();
                    $info    = $postHandler->insert_thread();
                }

                $tId = (int)$info['tid'];
                $pId = (int)$info['pid'];

                if($asRead){
                    mark_thread_read($tId, $v['fid']);
                }

                $postLog = [
                    //'id'       => '',
                    'pid'      => $pId,
                    'tid'      => $v['tid'],
                    'feedid'   => $feedID,
                    'feedhash' => $itemHash
                    //'feedtime' => $this->t
                ];
                $logItem[] = $postLog;
                if(!$isValid){
                    $post_errors = $postHandler->get_friendly_errors();
                    return false;
                }
            }
            return $logItem;
        } else {
            //nothing @todo
        }
    }

}