<?php
/**
 * Created by PhpStorm.
 * User: CPR007
 * Date: 2017/11/23
 * Time: 16:06
 */
include('./topSpeedBase.php');

//获取厂商 车系信息
class syncModel extends topSpeedBase {
    const MODULE_NAME = __CLASS__;
    public $apiUrl = 'http://api.jisuapi.com/car/car';
    public $imgPath = '';
    protected $modelData;//车系数据
    protected $seriesIdList;
    private $table = 'top_speed_model';
    private $seriesTable = 'top_speed_series';
    private $chunkNum = 50;//每次从redis list拉取条数
    protected $redisKey = 'seriesIdList';

    public function __construct() {
        parent::__construct();
        $this->imgPath = __DIR__ . '/img/model/';
        if (!is_dir($this->imgPath)) {
            mkdir($this->imgPath, 0777, true);
            chmod($this->imgPath, 0777);
        }
        $this->getSeriesIdList();
    }

    public function importData() {
        $imgList = [];
        if (!empty($this->modelData)) {
            $this->getDbInstance();
            self::$dbInstance->beginTransaction();
            $sql = "insert into $this->table (model_id,model_name,logo,price,depth,series_id,productionstate,yeartype,salestate,sizetype) values";
            foreach ($this->modelData as $v) {
                $imgList[]['url'] = $v['logo'];
                $v['localLogo'] = str_replace('\\', '/', $this->changeImgPath($v['logo'], $this->imgPath));
                $v['initial'] = isset($v['initial']) ? $v['initial'] : '';
                $v['depth'] = 4;
                $sql .= "('" . $v['id'] . "','" . $v['name'] . "','" . $v['localLogo'] . "','" . $v['price'] .
                    "'," . $v['depth'] . "," . $v['series_id'] . ",'" . $v['productionstate'] . "','" . $v['yeartype'] . "','" .
                    $v['salestate'] . "','" . $v['sizetype'] . "'),";
            }
            $sql = substr($sql, 0, -1);
            $sql .= " on DUPLICATE key update model_name=VALUES(model_name)";
            $num = self::$dbInstance->exec($sql);
            if ($num === false) {
                self::$dbInstance->rollBack();
                $errorInfo = self::$dbInstance->errorInfo();
                $this->writeLogs($this->table . '表新增数据失败，错误信息：' . $errorInfo[2], 2);

            } else {
                self::$dbInstance->commit();
                $this->writeLogs($this->table . '表新增数据成功，共新增更新' . $num . '条', 3);

            }
            //多线程下载图片
            $rs = $this->multiRequest($imgList, 0, false, 60, []);
            foreach ($rs as $v) {
                $this->saveAsImage($v['url'], $v['data'], $this->imgPath);
            }
            $this->modelData = [];
        }
    }

    public function changeImgPath($imgUrl, $path) {
        $filename = pathinfo($imgUrl, PATHINFO_BASENAME);
        return $path . $filename;
    }

    public function init() {
        $this->getRedisInstance();
        $idList = [];
        $len = self::$redisInstance->llen($this->redisKey);
        if ($len < 1) {
            $this->writeLogs('接口数据已同步完成', 3);
            exit;
        }
        $count = ceil($len / $this->chunkNum);
        while (true) {
            for ($i = 0; $i < $this->chunkNum; $i++) {
                if (!$idList[] = self::$redisInstance->lpop($this->redisKey)) {
                    array_pop($idList);
                    break;
                }
            }
            $this->syncData($idList);
            $idList = [];
            if ($count < 2) {
                break;
            }
            $count--;
        }
    }

    public function syncData($idList) {
        $params = [
            'appkey' => APP_KEY,

        ];
        $urlList = [];
        foreach ($idList as $k => $bv) {
            //if($k>0) break;//测试数据
            $params['parentid'] = $bv;
            $urlList[$k]['url'] = $this->apiUrl;
            $urlList[$k]['data'] = $params;
        }

        $rs = $this->multiRequest($urlList, 0, false, 30, []);
        $i = 0;
        foreach ($rs as $cv) {
            if ($cv['httpCode'] != 200) {
                $this->writeLogs($cv['errMsg'], 2);
                continue;
            }
            $indRes = json_decode($cv['data'], true);
            if (in_array($indRes['status'], $this->errorCode)) {
                $this->writeLogs('调用接口地址：' . $cv['url'] . "失败,失败原因:" . $indRes['msg'], 2);
                exit;
            } else if (in_array($indRes['status'], $this->warnCode)) {
                $this->writeLogs('调用接口地址：' . $cv['url'] . "返回警告信息:" . $indRes['msg'], 1);
                continue;
            }
            $this->writeLogs('调用接口成功，地址：' . $cv['url'], 3);
            $res = $indRes['result'];
            if (!empty($res['list'])) {
                foreach ($res['list'] as $v) {
                    preg_match('/parentid=(\d*)/', $cv['url'], $match);
                    $v['series_id'] = $match[1];
                    $this->modelData[] = $v;
                    $i++;
                }
            }

        }
        if (!empty($this->modelData)) {
            $this->importData();
        } else {
            return false;
        }
    }

    //获取品牌id list
    private function getSeriesIdList() {
        $this->getDbInstance();
        $sql = "select series_id from $this->seriesTable";
        $query = self::$dbInstance->query($sql);
        $res = $query->fetchAll(PDO::FETCH_ASSOC);
        $this->seriesIdList = array_column($res, 'series_id');
        $this->getRedisInstance();
        $len = self::$redisInstance->llen($this->redisKey);
        if ($len > 0) {
            return true;
        } else {
            if (!empty($this->seriesIdList)) {
                foreach ($this->seriesIdList as $v) {
                    self::$redisInstance->rpush($this->redisKey, $v);
                }
            }
        }
    }
}

$brandObj = new syncModel();
$brandObj->init();
