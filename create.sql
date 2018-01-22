CREATE TABLE `top_speed_brand` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `brand_id` int(10) NOT NULL COMMENT '极速品牌id',
  `brand_name` varchar(50) CHARACTER SET utf8mb4 NOT NULL COMMENT '品牌名称',
  `initial` char(5) CHARACTER SET utf8mb4 NOT NULL COMMENT '品牌首字母',
  `parent_id` int(10) NOT NULL COMMENT '上级id',
  `logo` varchar(200) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT 'logo图片地址',
  `depth` tinyint(1) NOT NULL COMMENT '深度 1 品牌 2子公司 3车型 4 具体车型',
  `deleted` bigint(20) NOT NULL DEFAULT '0' COMMENT '是否删除 0未删除 删除时对应id字段值',
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `brand_id` (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `top_speed_factory` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `factory_id` int(10) NOT NULL COMMENT '厂商id',
  `factory_name` varchar(50) CHARACTER SET utf8mb4 NOT NULL COMMENT '厂商名称',
  `factory_fullname` varchar(50) CHARACTER SET utf8mb4 NOT NULL COMMENT '厂商名称全称',
  `initial` char(5) CHARACTER SET utf8mb4 NOT NULL COMMENT '品牌首字母',
  `brand_id` int(10) NOT NULL COMMENT '厂商所属品牌id',
  `deleted` bigint(20) NOT NULL DEFAULT '0' COMMENT '是否删除 0未删除 删除时对应id字段值',
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `factory_id` (`factory_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;


CREATE TABLE `top_speed_series` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `series_id` int(10) NOT NULL COMMENT '车系id 对应api列表车系',
  `series_name` char(15) CHARACTER SET utf8mb4 NOT NULL COMMENT '车系名称',
  `series_full_name` varchar(50) CHARACTER SET utf8mb4 NOT NULL COMMENT '车系全称',
  `initial` char(5) CHARACTER SET utf8mb4 NOT NULL COMMENT '首字母',
  `logo` varchar(200) CHARACTER SET utf8mb4 NOT NULL COMMENT 'logo图片地址',
  `salestate` char(10) CHARACTER SET utf8mb4 NOT NULL COMMENT '销售状态',
  `depth` tinyint(1) NOT NULL COMMENT '层级',
  `brand_id` int(10) DEFAULT NULL COMMENT '对应品牌id',
  `factory_id` int(10) DEFAULT NULL COMMENT '车系所属品牌id',
  `deleted` bigint(20) NOT NULL DEFAULT '0' COMMENT '是否删除 0未删除 删除时对应id字段值',
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `series_id` (`series_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `top_speed_model` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `model_id` int(10) NOT NULL COMMENT '车型id',
  `model_name` char(15) CHARACTER SET utf8mb4 NOT NULL COMMENT '车系名称',
  `model_full_name` varchar(50) CHARACTER SET utf8mb4 NOT NULL COMMENT '车系全称',
  `initial` char(5) CHARACTER SET utf8mb4 NOT NULL COMMENT '首字母',
  `logo` varchar(200) CHARACTER SET utf8mb4 NOT NULL COMMENT 'logo图片地址',
  `price` char(10) CHARACTER SET utf8mb4 NOT NULL COMMENT '价格',
  `depth` tinyint(1) NOT NULL COMMENT '层级',
  `series_id` int(10) DEFAULT NULL COMMENT '对应车系id',
  `productionstate` char(10) CHARACTER SET utf8mb4 NOT NULL COMMENT '生产状态',
  `yeartype` char(15) CHARACTER SET utf8mb4 NOT NULL COMMENT '年款',
  `salestate` char(10) CHARACTER SET utf8mb4 NOT NULL COMMENT '销售状态',
  `sizetype` char(10) CHARACTER SET utf8mb4 NOT NULL COMMENT '车辆等级',
  `deleted` bigint(20) NOT NULL DEFAULT '0' COMMENT '是否删除 0未删除 删除时对应id字段值',
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_id` (`model_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `top_speed_model_detail` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `model_id` int(10) NOT NULL COMMENT '车型id',
  `fields` text CHARACTER SET utf8mb4 NOT NULL COMMENT '所有字段信息',
  `deleted` bigint(20) NOT NULL DEFAULT '0' COMMENT '是否删除 0未删除 删除时对应id字段值',
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_id` (`model_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;