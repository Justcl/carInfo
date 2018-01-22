<?php
define('APP_KEY', '');//接口请求key 可去极速官网申请
define('SYNC_ENVIRON', 'test');
set_time_limit(0);
function p($data) {
    echo '<pre>';
    print_r($data);
}

class topSpeedBase {
    public $fileLogDir = '';
    const MODULE_NAME = 'base';
    public $logMode = [
        1 => 'warning',
        2 => 'error',
        3 => 'success'
    ];
    public $writeDbList = [
        'dev'   =>['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pwd' => '', 'dbname' => 'test'],
        'test' => [],
        'pro' => [],
        'online' => [],
    ];
    private $redisConfig = ['host' => '127.0.0.1', 'port' => 6379, 'timeout' => 0, 'auth' => '', 'db' => 1];
    public static $writeDbConfig;
    public static $dbInstance;
    public static $logId;
    public $errorCode = [101, 102, 103, 104, 105, 106, 107, 108, 109];
    public $warnCode = [201, 202, 205];
    public static $redisInstance;

    public function __construct() {
        $this->fileLogDir = __DIR__ . '/log/';
        if (!is_dir($this->fileLogDir)) {
            mkdir($this->fileLogDir, 0777, true);
            chmod($this->fileLogDir, 0777);
        }

        self::$writeDbConfig = $this->writeDbList[SYNC_ENVIRON];
        $this->endDate = date("Y-m-d");
    }

    /**
     * 获取db连接
     * @return bool
     */
    public function getDbInstance() {
        $dns = 'mysql:host=' . self::$writeDbConfig['host'] . ';port=' . self::$writeDbConfig['port'] . ';dbname=' . self::$writeDbConfig['dbname'];
        try {
            if (!self::$dbInstance) {
                self::$dbInstance = new PDO($dns, self::$writeDbConfig['user'], self::$writeDbConfig['pwd'], array(PDO::ATTR_PERSISTENT => true));
            }
            self::$dbInstance->exec("SET NAMES UTF8");//设置数据库编码
            self::$dbInstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);//设置警告模式
        } catch (Exception $e) {
            $this->writeLogs(__FILE__ . "文件第" . __LINE__ . "行，" . $e->getMessage(), 2);
            exit;
        }
    }

    public function getRedisInstance() {
        try {
            if (!self::$redisInstance) {
                self::$redisInstance = new Redis();
            }
            self::$redisInstance->connect($this->redisConfig['host'], $this->redisConfig['port'], $this->redisConfig['timeout']);
            self::$redisInstance->auth($this->redisConfig['auth']);
            self::$redisInstance->select($this->redisConfig['db']);
        } catch (Exception $e) {
            $this->writeLogs(__FILE__ . "文件第" . __LINE__ . "行，" . $e->getMessage(), 2);
            return false;
        }
    }

    public function saveAsImage($url, $file, $path) {
        if ($url) {
            $filename = pathinfo($url, PATHINFO_BASENAME);
            $fp = fopen($path . $filename, 'a');
            //文件锁
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $file);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        } else {
            return false;
        }
    }

    /**
     * @param $url
     * @param int $requestType 1 post 其他 get
     * @param array $dataArr
     * @return array|bool
     */
    public function request($url, $requestType = 0, $dataArr = []) {
        if (empty($url)) {
            return false;
        }
        if ($requestType == 1) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataArr);
        } else {
            if (!empty($dataArr)) {
                $url = $url . '?' . http_build_query($dataArr);
            }
            $ch = curl_init($url);
        }
        if (strpos($url, 'https') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        $return = [];
        $return['httpCode'] = $info['http_code'];
        $return['errCode'] = curl_errno($ch);
        $return['errMsg'] = curl_error($ch);
        if ($data !== false) {
            $return['data'] = $data;
        }
        curl_close($ch);
        return $return;
    }

    /**
     * @param $urls
     * @param int $requestType
     * @param bool $timeoutType
     * @param int $timeout
     * @param array $header
     * @return array|bool
     */
    public function multiRequest($urls, $requestType = 1, $timeoutType = true, $timeout = 10, $header = []) {
        if (!is_array($urls)) {
            return false;
        }

        $mh = curl_multi_init();
        $curl_array = [];
        foreach ($urls as $k => $v) {
            if ($requestType == 1) {
                $curl_array[$k] = curl_init();
                curl_setopt($curl_array[$k], CURLOPT_URL, $v['url']);
                curl_setopt($curl_array[$k], CURLOPT_POST, 1);
                curl_setopt($curl_array[$k], CURLOPT_POSTFIELDS, $v['data']);
            } else {
                if (!empty($v['data'])) {
                    $url = $v['url'] . '?' . http_build_query($v['data']);
                } else {
                    $url = $v['url'];
                }
                $curl_array[$k] = curl_init($url);
            }
            curl_setopt($curl_array[$k], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_array[$k], CURLOPT_HTTPHEADER, $header);
            if ($timeoutType) {
                curl_setopt($curl_array[$k], CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_setopt($curl_array[$k], CURLOPT_TIMEOUT, $timeout);
            }

            curl_multi_add_handle($mh, $curl_array[$k]);
        }

        $running = NULL;
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);

        $res = [];
        foreach ($urls as $k => $v) {
            $tmpData = curl_multi_getcontent($curl_array[$k]);
            $tmpInfo = curl_getinfo($curl_array[$k]);
            $tmpRes = [];
            $tmpRes['httpCode'] = $tmpInfo['http_code'];
            $tmpRes['errNo'] = curl_errno($curl_array[$k]);
            $tmpRes['errMsg'] = curl_error($curl_array[$k]);
            $tmpRes['url'] = !empty($v['data']) ? $v['url'] . '?' . http_build_query($v['data']) : $v['url'];
            if ($tmpData !== false) {
                $tmpRes['data'] = $tmpData;
            }
            $res[$k] = $tmpRes;
            curl_multi_remove_handle($mh, $curl_array[$k]);
            curl_close($curl_array[$k]);
        }
        curl_multi_close($mh);
        return $res;
    }

    public function getLogId() {
        if (self::$logId === NULL) {
            self::$logId = uniqid();
        }
        return self::$logId;
    }


    public function writeLogs($msg, $mode) {
        $callClass = get_called_class();
        $moduleName = $callClass::MODULE_NAME;
        $logFile = $this->fileLogDir . $moduleName . '_' . date("Y_m_") . 'logs';
        $tag = $this->logMode[$mode];
        $logId = $this->getLogId();
        $nowData = date("Y-m-d H:i:s");
        $msg = "[$nowData][$tag][$logId] $msg" . PHP_EOL;
        file_put_contents($logFile, $msg, FILE_APPEND);
    }

}
