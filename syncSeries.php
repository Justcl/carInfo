<?php
/**
 * Created by PhpStorm.
 * User: CPR007
 * Date: 2017/11/23
 * Time: 16:06
 */
include('./topSpeedBase.php');

//获取厂商 车系信息
class syncSeries extends topSpeedBase {
    const MODULE_NAME = __CLASS__;
    public $apiUrl = 'http://api.jisuapi.com/car/type';
    public $imgPath = '';
    protected $seriesData;//车系数据
    protected $factoryData;//厂商数据
    protected $brandIdList;
    private $table = 'top_speed_series';
    private $brandTable = 'top_speed_brand';
    private $factoryTable = 'top_speed_factory';
    private $chunkNum = 100;
    protected $redisKey = 'brandIdList';

    public function __construct() {
        parent::__construct();
        $this->imgPath = __DIR__ . '/img/series/';
        if (!is_dir($this->imgPath)) {
            mkdir($this->imgPath, 0777, true);
            chmod($this->imgPath, 0777);
        }
        $this->getBrandIdList();
    }

    public function importData() {
        if (!empty($this->seriesData) && !empty($this->factoryData)) {
            $this->getDbInstance();
            self::$dbInstance->beginTransaction();
            $imgList = [];
            $sql = "insert into $this->table (series_id,series_name,series_full_name,initial,logo,salestate,depth,brand_id,factory_id) values";
            foreach ($this->seriesData as $v) {
                $imgList[]['url'] = $v['logo'];
                $v['localLogo'] = str_replace('\\', '/', $this->changeImgPath($v['logo'], $this->imgPath));
                $v['initial'] = isset($v['initial']) ? $v['initial'] : '';
                $sql .= "('" . $v['id'] . "','" . $v['name'] . "','" . $v['fullname'] . "','" . $v['initial'] . "','" .
                    $v['localLogo'] . "','" . $v['salestate'] . "'," . $v['depth'] . "," . $v['brand_id'] . "," . $v['factory_id'] . "),";
            }
            $sql = substr($sql, 0, -1);
            $sql .= " on DUPLICATE key update series_name=VALUES(series_name)";
            $factorySql = "insert into $this->factoryTable (factory_id,factory_name,factory_fullname,initial,brand_id) values";
            foreach ($this->factoryData as $v) {
                $factorySql .= "('" . $v['factory_id'] . "','" . $v['factory_name'] . "','" . $v['factory_fullname'] . "','" . $v['initial'] . "'," .
                    $v['brand_id'] . "),";
            }
            $factorySql = substr($factorySql, 0, -1);
            $factorySql .= " on DUPLICATE key update factory_name=VALUES(factory_name)";
            $seriesNum = self::$dbInstance->exec($sql);
            $factoryNum = self::$dbInstance->exec($factorySql);
            if ($seriesNum === false && $factoryNum === false) {
                self::$dbInstance->rollBack();
                $errorInfo = self::$dbInstance->errorInfo();
                $this->writeLogs($this->table . '表新增数据失败，错误信息：' . $errorInfo[2], 2);
                $this->writeLogs($this->factoryTable . '表新增数据失败，错误信息：' . $errorInfo[2], 2);

            } else {
                self::$dbInstance->commit();
                $this->writeLogs($this->table . '表新增数据成功，共新增更新' . $seriesNum . '条', 3);
                $this->writeLogs($this->factoryTable . '表新增数据成功，共新增更新' . $factoryNum . '条', 3);

            }
            //多线程下载图片 图片较多设置时间长点
            $rs = $this->multiRequest($imgList, 0, false, 60, []);
            foreach ($rs as $v) {
                $this->saveAsImage($v['url'], $v['data'], $this->imgPath);
            }
            $this->seriesData = [];
            $this->factoryData = [];
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
            //if($bv>2) break;//测试数据
            $params['parentid'] = $bv;
            $urlList[$k]['url'] = $this->apiUrl;
            $urlList[$k]['data'] = $params;
        }
        $rs = $this->multiRequest($urlList, 0, true, 30, []);
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
            if (!empty($res)) {
                foreach ($res as $v) {
                    $this->factoryData[$i]['factory_id'] = $v['id'];
                    $this->factoryData[$i]['factory_name'] = $v['name'];
                    $this->factoryData[$i]['factory_fullname'] = $v['fullname'];
                    $this->factoryData[$i]['initial'] = $v['initial'];
                    preg_match('/parentid=(\d*)/', $cv['url'], $match);
                    $this->factoryData[$i]['brand_id'] = $match[1];
                    if (!empty($v['list'])) {
                        foreach ($v['list'] as $vv) {
                            $vv['brand_id'] = $match[1];
                            $vv['factory_id'] = $v['id'];
                            $this->seriesData[] = $vv;
                        }
                    }
                    $i++;
                }
                $this->importData();
            }
        }
    }

    //获取品牌id list
    private function getBrandIdList() {
        $this->getDbInstance();
        $sql = "select brand_id from $this->brandTable";
        $query = self::$dbInstance->query($sql);
        $res = $query->fetchAll(PDO::FETCH_ASSOC);
        $this->brandIdList = array_column($res, 'brand_id');
        $this->getRedisInstance();
        $len = self::$redisInstance->llen($this->redisKey);
        if ($len > 0) {
            return true;
        } else {
            if (!empty($this->brandIdList)) {
                foreach ($this->brandIdList as $v) {
                    self::$redisInstance->rpush($this->redisKey, $v);
                }
            }
        }
    }
}

$brandObj = new syncSeries();
$brandObj->init();
