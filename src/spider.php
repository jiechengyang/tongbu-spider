<?php

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

class ArrayToolkit
{
    public static function get(array $array, $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    public static function column(array $array, $columnName)
    {
        if (function_exists('array_column')) {
            return array_column($array, $columnName);
        }
        if (empty($array)) {
            return [];
        }
        $column = [];
        foreach ($array as $item) {
            if (isset($item[$columnName])) {
                $column[] = $item[$columnName];
            }
        }
        return $column;
    }

    public static function parts(array $array, array $keys)
    {
        foreach (array_keys($array) as $key) {
            if (!in_array($key, $keys)) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    public static function index(array $array, $name)
    {
        $indexedArray = [];
        if (empty($array)) {
            return $indexedArray;
        }
        foreach ($array as $item) {
            if (isset($item[$name])) {
                $indexedArray[$item[$name]] = $item;
                continue;
            }
        }
        return $indexedArray;
    }
}

class CurlHttpClient
{
    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var int
     */
    private $connectTimeout = 5000;

    /**
     * @var int
     */
    protected $socketTimeout = 5000;

    /**
     * @var array curl config
     */
    protected $config = [];

    /**
     * HttpClient
     *
     * @param array $headers HTTP header
     */
    public function __construct($headers = [])
    {
        $this->headers = $this->buildHeaders($headers);
    }

    /**
     * 连接超时
     *
     * @param int $ms 毫秒
     */
    public function setConnectionTimeoutInMillis($ms)
    {
        $this->connectTimeout = $ms;
    }

    /**
     * 响应超时
     *
     * @param int $ms 毫秒
     */
    public function setSocketTimeoutInMillis($ms)
    {
        $this->socketTimeout = $ms;
    }

    /**
     * 配置
     *
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * 请求预处理
     *
     * @param resource $ch
     */
    public function prepare($ch)
    {
        foreach ($this->config as $key => $value) {
            curl_setopt($ch, $key, $value);
        }
    }

    /**
     * @param string $url
     * @param array  $data    HTTP POST BODY
     * @param array  $param   HTTP URL
     * @param array  $headers HTTP header
     *
     * @return array
     */
    public function post($url, $data = [], $params = [], $headers = [])
    {
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));

        $ch = curl_init();
        $this->prepare($ch);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (0 === $code) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);

        return [
            'code' => $code,
            'content' => $content,
        ];
    }

    /**
     * @param string $url
     * @param array  $datas   HTTP POST BODY
     * @param array  $param   HTTP URL
     * @param array  $headers HTTP header
     *
     * @return array
     */
    public function multiPost($url, $datas = [], $params = [], $headers = [])
    {
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));

        $chs = [];
        $result = [];
        $mh = curl_multi_init();
        foreach ($datas as $data) {
            $ch = curl_init();
            $chs[] = $ch;
            $this->prepare($ch);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
            curl_multi_add_handle($mh, $ch);
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            usleep(100);
        } while ($running);

        foreach ($chs as $ch) {
            $content = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result[] = [
                'code' => $code,
                'content' => $content,
            ];
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);

        return $result;
    }

    /**
     * @param string $url
     * @param array  $param   HTTP URL
     * @param array  $headers HTTP header
     *
     * @return array
     */
    public function get($url, $params = [], $headers = [])
    {
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));

        $ch = curl_init();
        $this->prepare($ch);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (0 === $code) {
            throw new Exception(curl_error($ch));
        }
        $res =  [
            'code' => $code,
            'content' => $content,
        ];

        $res['error'] = !$content ? array(curl_errno($ch), curl_error($ch)) : "";
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $res['headerin'] = substr($content, 0, $headerSize);
        $res['body'] = substr($content, $headerSize);
        curl_close($ch);

        return $res;
    }

    /**
     * 构造 header
     *
     * @param array $headers
     *
     * @return array
     */
    private function buildHeaders($headers)
    {
        $result = [];
        foreach ($headers as $k => $v) {
            $result[] = sprintf('%s:%s', $k, $v);
        }

        return $result;
    }

    /**
     * @param string $url
     * @param array  $params 参数
     *
     * @return string
     */
    private function buildUrl($url, $params)
    {
        if (!empty($params)) {
            $str = http_build_query($params);

            return $url . (false === strpos($url, '?') ? '?' : '&') . $str;
        }

        return $url;
    }
}

/**
 * 非阻塞的执行shell
 *
 * @param [type] $cmd
 * @param string $log
 * @return void
 */
function execInBackGround($cmd, $log = '')
{
    if (substr(php_uname(), 0, 7) == "Windows") {
        //            pclose(popen("start /B {$cmd}", "r"));
        pclose(popen("start /B " . $cmd . " 1> $log 2>&1", "r"));
    } else {
        //            shell_exec( $cmd . " 1> $log 2>&1" );
        shell_exec("nohup $cmd > /dev/null & echo $!");
    }
}

function mkdirOutputDir()
{
    $path = dirname(__FILE__) . '/videos';
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    return $path;
}

function m3u8ToMp4($url, $outputPath)
{
    $cmd = sprintf("ffmpeg -i \"%s\" -vcodec copy -acodec copy -absf aac_adtstoasc %s.mp4", $url, $outputPath);
    shell_exec($cmd);
}


//https://tongbu.eduyun.cn/tbkt/tbkthtml/ItemJsonData.js?t=

$itemJsonurl = "https://tongbu.eduyun.cn/tbkt/tbkthtml/ItemJsonData.js?t" . time();
$caseListJsonUrl = "https://tongbu.eduyun.cn/tbkt/tbkthtml/CaseListJsonData.js?t=" . time();
// $curl = new CurlHttpClient([
//     'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
//     'Accept-Encoding' => 'gzip, deflate, br',
//     'content-type' =>  'application/javascript'
// ]);

// $curl->setConfig([
//     CURLOPT_SSL_VERIFYHOST => 0,
//     CURLOPT_SSL_VERIFYPEER => 0,
//     CURLOPT_ACCEPT_ENCODING => 'gzip, deflate,br'
// ]);

// $result = $curl->get($url);

// if ($result['code'] != 200 || empty($result['content'])) {
//     list($errno, $errmsg) = $result['error'];
//     echo 'curl error:', $errmsg, PHP_EOL;
//     exit(0);
// }

$body = file_get_contents($itemJsonurl);
$body = trim(str_replace("var xueduanJson = ", "", $body));
$body = rtrim($body, ";");
$data = json_decode($body, true);
$list = ArrayToolkit::get($data, 'xueDuan', null);
if (empty($list)) {
    echo 'nothing itemData';
    exit(0);
}

$caseListBody = file_get_contents($caseListJsonUrl);
$caseListBody = trim(str_replace("var caseJson = ", "", $caseListBody));
$caseListBody = rtrim($caseListBody, ";");
$cData = json_decode($caseListBody, true);
// TODO: check already existing video file filters
$clist = ArrayToolkit::get($cData, 'clist', null);
if (empty($clist)) {
    echo 'nothing caseListData';
    exit(0);
}
$outputDir = mkdirOutputDir();
$clistIndexCode = ArrayToolkit::index($clist, 'caseCode');

$xueDuanList = ArrayToolkit::index($list, 'xueDuanCode');
$xueDuanCode = 'xd0001';
$njCode = 'njx002';
$targetXdList = ArrayToolkit::get($xueDuanList, $xueDuanCode);
$nianJiList = $targetXdList['nianJiList'];
$njItems = ArrayToolkit::index($nianJiList, 'njCode');
$targetNjList = ArrayToolkit::get($njItems, $njCode);
$subjectsList = $targetNjList['subjectsList'];
$xkCode = 'sx0001';
$xKList = ArrayToolkit::index($subjectsList, 'xkCode');
$targetXkList = ArrayToolkit::get($xKList, $xkCode);
$danYuanList = $targetXkList["danYuanList"];
foreach ($danYuanList as $key => $value) {
    if (empty($value['caseList'])) {
        continue;
    }

    $casCodes = ArrayToolkit::column($value['caseList'], 'caseCode');
    foreach ($casCodes as $casCode) {
        if (!isset($clistIndexCode[$casCode])) {
            continue;
        }

        $citem = $clistIndexCode[$casCode];
        if (empty($citem['caseBeanList'][0]['picUrl'])) {
            continue;
        }

        echo '获取到', $casCode, PHP_EOL;
        $picUrl = $citem['caseBeanList'][0]['picUrl'];
        echo 'pic url:', $picUrl, PHP_EOL;
        $parseUrlData = explode("pic", $picUrl);
        $urlFix = $parseUrlData[0];
        $casename = str_replace("00001000.jpg", " ", $parseUrlData[1]);
        $m3u8Url = sprintf("%svideo%s.m3u8", $urlFix, $casename);
        $headers = @get_headers($m3u8Url);
        if ( false !== strpos($headers[0],'404') || false !== strpos($headers[0],'400')) {
            $casename = str_replace("00001000.jpg", "", $parseUrlData[1]);
            $m3u8Url = sprintf("%svideo%s.m3u8", $urlFix, $casename);
        }

        echo 'm3u8 url:', $m3u8Url, PHP_EOL;
        m3u8ToMp4($m3u8Url, sprintf("%s/%s", $outputDir, $casCode));
        sleep(5);
        //https://d006.eduyun.cn/videoworks/mda-kifp6ufyczkiwbjx/ykt_tbkt_hls_1080_7_CJ_1/video/数学广角练习 .m3u8
    }
}
//https://d006.eduyun.cn/videoworks/mda-kienqf0hgtgc0yen/ykt_tbkt_hls_1080_7_CJ_1/video/《古诗二首》（第一课时）改一.m3u8
//https://d006.eduyun.cn/videoworks/mda-kienqf0hgtgc0yen/ykt_tbkt_hls_1080_7_CJ_1/pic/《古诗二首》（第一课时）改一00001000.jpg
//https://tongbu.eduyun.cn/tbkt/tbkthtml/wk/weike/2020CJ/2020CJ02YWTB001.html
exit(0);
