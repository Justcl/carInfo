<?php
/**
 * Created by PhpStorm.
 * User: CPR007
 * Date: 2017/11/23
 * Time: 13:46
 */
include('./topSpeedBase.php');

class syncBrand extends topSpeedBase {
    const MODULE_NAME = __CLASS__;
    public $apiUrl = 'http://api.jisuapi.com/car/brand';
    public $imgPath = '';
    protected $brandData;
    private $table='top_speed_brand';
    public function __construct() {
        parent::__construct();
        $this->imgPath=__DIR__ . '/img/brand/';
        if (!is_dir($this->imgPath)) {
            mkdir($this->imgPath, 0777, true);
            chmod($this->imgPath, 0777);
        }
    }

    public function importData() {
        $this->_getData();
        /*$this->brandData=[
           ["id"=> "1",
            "name"=> "奥迪",
            "initial"=> "A",
            "parentid"=>"0",
            "logo"=> "http://pic1.jisuapi.cn/car/static/images/logo/300/1.png",
            "depth"=>"1"
           ],
        ];*/
        if(!empty($this->brandData)){
            $imgList=[];
            $this->getDbInstance();
            self::$dbInstance->beginTransaction();
            $sql="insert into $this->table (brand_id,brand_name,initial,parent_id,logo,depth) values";
            foreach ($this->brandData as $v){
                $imgList[]['url']=$v['logo'];
                $v['localLogo']=str_replace('\\','/',$this->changeImgPath($v['logo'],$this->imgPath));
                $sql.="('" . $v['id'] . "','" . $v['name'] . "','" . $v['initial'] . "','" . $v['parentid'] . "','" .
                    $v['localLogo'] . "','" . $v['depth'] . "'),";
            }
            $sql=substr($sql,0,-1);
            $sql .= " on DUPLICATE key update brand_name=VALUES(brand_name)";
            $num = self::$dbInstance->exec($sql);
            if ($num === false) {
                self::$dbInstance->rollBack();
                $errorInfo = self::$dbInstance->errorInfo();
                $this->writeLogs($this->table.'表新增数据失败，错误信息：' . $errorInfo[2], 2);
                return false;
            } else {
                self::$dbInstance->commit();
                $this->writeLogs($this->table.'表新增数据成功，共新增更新' . $num . '条', 3);
                return false;
            }
            //多线程下载图片
            $rs=$this->multiRequest($imgList,0,true,10,[]);
            foreach ($rs as $v){
                $this->saveAsImage($v['url'],$v['data'],$this->imgPath);
            }
        }
    }

    public function changeImgPath($imgUrl,$path){
        $filename = pathinfo($imgUrl,PATHINFO_BASENAME);
        return $path.$filename;
    }

    public function _getData(){
        $params=[
            'appkey'=>APP_KEY
        ];
        $rs=$this->request($this->apiUrl,0,$params);
        if($rs['httpCode'] !=200){
            $this->writeLogs($rs['errMsg'],2);
            exit;
        }else{
            $dataInfo=json_decode($rs['data'],true);
            if($dataInfo['status']!=0){
                $this->writeLogs($dataInfo['msg'],2);
                exit;
            }
            $this->writeLogs('调用接口成功，地址：'.$this->apiUrl.'参数：'.json_encode($params),3);
            $this->brandData=$dataInfo['result'];
        }
    }

}

$brandObj = new syncBrand();
$brandObj->importData();
