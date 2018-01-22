<?php
/**
 * Created by PhpStorm.
 * User: CPR007
 * Date: 2017/11/23
 * Time: 16:06
 */
include('./topSpeedBase.php');

//获取厂商 车系信息
class syncModelDetail extends topSpeedBase {
    const MODULE_NAME = __CLASS__;
    public $apiUrl = 'http://api.jisuapi.com/car/detail';
    protected $modelDetailData;//车系数据
    protected $modelIdList;
    private $table = 'top_speed_model_detail';
    private $modelTable = 'top_speed_model';
    protected $chunkNum = 200;//每次从redis list拉取条数
    protected $redisKey = 'modelIdList';

    public function __construct() {
        parent::__construct();
        $this->getModelIdList();
    }

    public function importData() {
        if (!empty($this->modelDetailData)) {
            $this->getDbInstance();
            self::$dbInstance->beginTransaction();
            $sql = "insert into $this->table (model_id,fields) values";
            foreach ($this->modelDetailData as $v) {
                $sql .= "(" . $v['model_id'] . ",'" . $v['fields'] . "'),";
            }
            $sql = substr($sql, 0, -1);
            $sql .= " on DUPLICATE key update fields=VALUES(fields)";
            $num = self::$dbInstance->exec($sql);
            if ($num === false) {
                self::$dbInstance->rollBack();
                $errorInfo = self::$dbInstance->errorInfo();
                $this->writeLogs($this->table . '表新增数据失败，执行sql语句' . $sql . ',错误信息：' . $errorInfo[2], 2);
                die;
            } else {
                self::$dbInstance->commit();
                $this->writeLogs($this->table . '表新增数据成功，共新增更新' . $num . '条', 3);

            }
            $this->modelDetailData = [];
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
            $params['carid'] = $bv;
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
            //$this->writeLogs('调用接口成功，地址：' . $cv['url'], 3);
            $res = $indRes['result'];
            if (!empty($res)) {
                preg_match('/carid=(\d*)/', $cv['url'], $match);
                $this->modelDetailData[$i]['model_id'] = $match[1];
                $this->modelDetailData[$i]['fields'] = str_replace("'", "\\'", json_encode($res, true));
            }
            $i++;
        }
        $this->importData();
    }

    private function getModelIdList() {
        $this->getDbInstance();
        $sql = "select model_id from $this->modelTable";
        $query = self::$dbInstance->query($sql);
        $res = $query->fetchAll(PDO::FETCH_ASSOC);
        $allIdList = array_column($res, 'model_id');
        $detailSql = "select model_id from $this->table";
        $detailQuery = self::$dbInstance->query($detailSql);
        $res = $detailQuery->fetchAll(PDO::FETCH_ASSOC);
        $detailIdList = array_column($res, 'model_id');
        $this->modelIdList = array_diff($allIdList, $detailIdList);
        //将车型id保存到redis
        $this->getRedisInstance();
        $count = count($this->modelIdList);
        if ($count < 1) {
            return true;
        } else {
            self::$redisInstance->delete($this->redisKey);
            if (!empty($this->modelIdList)) {
                foreach ($this->modelIdList as $v) {
                    self::$redisInstance->rpush($this->redisKey, $v);
                }
            }
        }
    }
}

$brandObj = new syncModelDetail();
$brandObj->init();
